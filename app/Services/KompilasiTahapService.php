<?php

namespace App\Services;

use App\Enums\ReviuStatus;
use App\Enums\TahapProses;
use App\Models\DjsnButir;
use App\Models\DjsnKompilasi;
use App\Models\LogActivity;
use App\Models\SnpButir;
use App\Models\SnpKompilasi;
use App\Models\SuratTahap;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class KompilasiTahapService
{
    public function __construct(private IsianUnitTahapService $inputs, private KompilasiSalinanService $snapshots, private KompilasiBerkasService $files) {}

    public function stage(SnpButir|DjsnButir $butir): ?SuratTahap
    {
        return $butir->record?->tahapAktif
            ?? ($this->inputs->canStartInitial($butir) ? $butir->record->tahapanProses->firstWhere('tahap_proses', TahapProses::TanggapanAwal) : null);
    }

    public function label(SnpButir|DjsnButir $butir): string
    {
        $stage = $this->stage($butir);
        if ($stage === null) {
            return $this->inputs->canStartInitial($butir) ? 'Tanggapan Awal' : 'Tahap perlu rekonsiliasi';
        }

        return $stage->tahap_proses->label().($stage->tahap_proses === TahapProses::TindakLanjut ? ' Putaran '.($stage->nomor_putaran ?? '?') : '');
    }

    public function sources(SnpButir|DjsnButir $butir): Collection
    {
        $stage = $this->stage($butir);

        return $stage === null ? collect() : $this->inputs->sources($butir, $stage);
    }

    public function compilation(SnpButir|DjsnButir $butir): ?Model
    {
        $stage = $this->stage($butir);

        return $stage === null ? null : $butir->kompilasis->firstWhere('tahap_id', $stage->id);
    }

    public function canCompile(User $user, SnpButir|DjsnButir $butir): bool
    {
        $permission = 'canCreate'.ucfirst($this->inputs->module($butir)).'Kompilasi';
        $record = $butir->record;
        $stage = $this->stage($butir);
        if (! $user->{$permission}() || $record === null || $record->isWorkflowClosed() || $record->status === 'tuntas'
            || $butir->kompilasis->contains(fn ($row): bool => $row->tahap_id === null)
            || $butir->reviews->contains(fn ($row): bool => $row->tahap_id === null)
            || ! ($stage !== null && $this->inputs->stageIsCurrent($butir, $stage) || $this->inputs->canStartInitial($butir))) {
            return false;
        }
        if ($stage === null) {
            return true;
        }
        $sources = $this->sources($butir);
        $unitIds = $sources->map(fn ($source) => $this->inputs->sourceUnit($source, $butir));

        return ! $unitIds->contains(null) && $unitIds->unique()->count() === $unitIds->count()
            && ! $butir->kompilasis->where('tahap_id', $stage->id)->contains(fn ($row): bool => $row->salinan_konteks !== null
                || filled($row->hasil_kompilasi) || $row->status !== 'belum_dikompilasi' || $row->bahan()->exists())
            && $butir->reviews->where('tahap_id', $stage->id)->isEmpty();
    }

    /** @param array<string, mixed> $data */
    public function compile(SnpButir|DjsnButir $butir, User $user, array $data, Request $request): SnpKompilasi|DjsnKompilasi
    {
        abort_unless(IsianUnitTahapService::enabled(), 404);
        $permission = 'canCreate'.ucfirst($this->inputs->module($butir)).'Kompilasi';
        abort_unless($user->{$permission}(), 403);
        $copies = [];
        $upload = null;
        try {
            return $butir->getConnection()->transaction(function () use ($butir, $user, $data, $request, &$copies, &$upload): SnpKompilasi|DjsnKompilasi {
                $this->inputs->lockButir($butir);
                $stage = $this->inputs->activeStageForWrite($butir, $user);
                $this->assertContext($butir, $user, $stage, $data);
                $module = $this->inputs->module($butir);
                $class = $stage->relatedClass('Kompilasi');
                $compilation = $this->compilation($butir) ?? new $class;
                $old = $compilation->exists ? $compilation->getRawOriginal() : null;
                $capturedAt = now();
                $upload = $request->file('dokumen')?->store('dokumen/kompilasi-'.$module, 'public');
                if ($request->hasFile('dokumen') && ! $upload) {
                    throw ValidationException::withMessages(['dokumen' => 'Dokumen kompilasi gagal disimpan. Silakan unggah ulang.']);
                }
                $compilation->forceFill([
                    'id_butir_'.$module => $butir->{'id_butir_'.$module}, 'tahap_id' => $stage->id,
                    'putaran_id' => $stage->putaran_asal_id, 'putaran_tl' => max(1, $stage->nomor_putaran),
                    'tahap_kompilasi' => $stage->tahap_proses === TahapProses::TanggapanAwal ? 'tanggapan' : 'tindak_lanjut',
                    'hasil_kompilasi' => app(SnpButirContent::class)->normalize($data['hasil_kompilasi'], $butir->record->id, $module, 'hasil_kompilasi', 'Hasil kompilasi'),
                    'deliverables' => $data['deliverables'] ?? null, 'dokumen' => $upload ?? $compilation->dokumen,
                    'ubah_tgl' => $data['ubah_tgl'] ?? null, 'status_pengajuan_tgl' => empty($data['ubah_tgl']) ? null : 'pending',
                    'status' => 'dalam_proses_reviu_dewas', 'status_bahan' => 'menyiapkan_salinan',
                    'created_by' => $compilation->created_by ?? $user->id, 'updated_by' => $user->id,
                ])->save();
                $snapshot = $this->snapshots->capture($butir, $stage, $compilation, $user, $this->sources($butir), $capturedAt, $copies);
                foreach ($snapshot['materials'] as $material) {
                    $compilation->bahan()->create($material);
                }
                $compilation->forceFill([
                    'salinan_konteks' => $snapshot['context'], 'status_bahan' => 'terverifikasi', 'bahan_dicatat_at' => $capturedAt,
                    'kunci_salinan' => hash('sha256', $stage->id.':'.$butir->{'id_butir_'.$module}),
                ])->save();
                $reviewClass = $stage->relatedClass('Review');
                $review = new $reviewClass;
                $review->forceFill([
                    'id_butir_'.$module => $butir->{'id_butir_'.$module}, 'tahap_id' => $stage->id, 'putaran_id' => $stage->putaran_asal_id,
                    'tahap_review' => $compilation->tahap_kompilasi,
                    'kompilasi_id' => $compilation->id, 'komite_id' => $butir->butirPics->firstWhere('jenis_pic', 'komite')?->komite_id,
                    'status' => ReviuStatus::BelumDitanggapi->value, 'created_by' => $user->id, 'updated_by' => $user->id,
                ]);
                if ($review->getConnection()->getSchemaBuilder()->hasColumn($review->getTable(), 'putaran_tl')) {
                    $review->setAttribute('putaran_tl', max(1, $stage->nomor_putaran));
                }
                $review->save();
                LogActivity::create([
                    'user_id' => $user->id, 'type_code' => $module, 'database_name' => 'sidewas_'.$module, 'table_name' => 'tb_kompilasi',
                    'record_key' => $butir->{'id_butir_'.$module}, 'action' => 'kompilasi_salinan_bahan',
                    'description' => 'Kompilasi dan salinan bahan tahap '.$stage->tahap_proses->value.' putaran '.$stage->nomor_putaran.' disimpan bersamaan.',
                    'old_values' => $old, 'new_values' => ['kompilasi_id' => $compilation->id, 'review_id' => $review->id, 'tahap_id' => $stage->id,
                        'jumlah_bahan' => count($snapshot['materials']), 'kunci_salinan' => $compilation->kunci_salinan],
                    'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
                ]);

                return $compilation;
            });
        } catch (Throwable $exception) {
            $this->files->discard($copies);
            if ($upload) {
                Storage::disk('public')->delete($upload);
            }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $data */
    private function assertContext(SnpButir|DjsnButir $butir, User $user, ?SuratTahap $stage, array $data): void
    {
        abort_unless($stage !== null && $stage->putaran_asal_id !== null && $this->canCompile($user, $butir), 422, 'Kompilasi sudah dikirim, tahap terkunci, atau sumber perlu rekonsiliasi.');
        $type = $stage->tahap_proses === TahapProses::TanggapanAwal ? 'tanggapan' : 'tindak_lanjut';
        abort_if(($data['tahap_kompilasi'] ?? null) !== $type
            || isset($data['tahap_id']) && (int) $data['tahap_id'] !== (int) $stage->id
            || isset($data['putaran_id']) && (int) $data['putaran_id'] !== (int) $stage->putaran_asal_id
            || $stage->tahap_proses === TahapProses::TindakLanjut && empty($data['tahap_id']), 422, 'Tahap atau putaran telah berubah. Muat ulang halaman kompilasi.');
        $round = $butir->record->putaran->firstWhere('id', $stage->putaran_asal_id);
        abort_unless($round !== null && $round->archived_at === null && ($round->isLegacy() || (int) $butir->record->putaran_aktif_id === (int) $round->id), 422, 'Putaran asal sudah terkunci atau tidak sesuai.');
    }

    /** @param array<string, mixed> $data */
    public function assertCanUpload(User $user, SnpButir|DjsnButir $butir, array $data): void
    {
        $butir->getConnection()->transaction(function () use ($user, $butir, $data): void {
            $this->inputs->lockButir($butir);
            $stage = $this->stage($butir);
            if ($stage === null && $this->inputs->canStartInitial($butir)) {
                abort_unless($this->canCompile($user, $butir) && ($data['tahap_kompilasi'] ?? null) === 'tanggapan'
                    && empty($data['tahap_id']), 422, 'Tahap kompilasi tidak sesuai.');

                return;
            }
            $this->assertContext($butir, $user, $stage, $data);
        });
    }
}
