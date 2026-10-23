<?php

namespace App\Services;

use App\Enums\ReviuStatus;
use App\Enums\SuratStatus;
use App\Enums\TahapProses;
use App\Models\DjsnReview;
use App\Models\LogActivity;
use App\Models\SnpReview;
use App\Models\SuratPutaran;
use App\Models\SuratTahap;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Throwable;

class ReviuTahapService
{
    public function __construct(private IsianUnitTahapService $inputs) {}

    public function label(SuratTahap $stage): string
    {
        return $stage->tahap_proses->label().($stage->tahap_proses === TahapProses::TindakLanjut ? ' Putaran '.$stage->nomor_putaran : '');
    }

    /** The official sources are the frozen copies, never the latest unit inputs. */
    public function hasVerifiedCompilation(SnpReview|DjsnReview $review): bool
    {
        $review->loadMissing(['butir.record', 'tahapProses', 'kompilasiPutaran.bahan']);
        $stage = $review->tahapProses;
        $butir = $review->butir;
        $compilation = $review->kompilasiPutaran;
        $module = $this->inputs->module($review);
        $key = $review->{'id_butir_'.$module};
        $context = $compilation?->salinan_konteks;
        if ($stage === null || $butir?->record === null || $compilation === null || ! is_array($context)
            || $stage->status_pemetaan !== 'terpetakan' || $stage->nomor_putaran === null
            || (int) $stage->record_id !== (int) $butir->record->id
            || (int) $compilation->tahap_id !== (int) $stage->id
            || (int) $compilation->putaran_id !== (int) $stage->putaran_asal_id
            || (int) $review->putaran_id !== (int) $stage->putaran_asal_id
            || $compilation->{'id_butir_'.$module} !== $key
            || $compilation->status !== 'dalam_proses_reviu_dewas'
            || $compilation->status_bahan !== 'terverifikasi' || $compilation->bahan_dicatat_at === null
            || $compilation->kunci_salinan !== hash('sha256', $stage->id.':'.$key)) {
            return false;
        }
        $type = $stage->tahap_proses === TahapProses::TanggapanAwal ? 'tanggapan' : 'tindak_lanjut';
        $committee = collect($context['penugasan'] ?? [])->firstWhere('jenis_pic', 'komite');
        if ($review->tahap_review !== $type || $compilation->tahap_kompilasi !== $type
            || (int) $review->komite_id !== (int) ($committee['komite']['id'] ?? null)
            || ($context['versi_salinan'] ?? null) !== 1
            || (int) data_get($context, 'surat.id') !== (int) $stage->record_id
            || (int) data_get($context, 'butir.id') !== (int) $butir->id || data_get($context, 'butir.key') !== $key
            || ! is_array($context['butir'] ?? null) || ! array_key_exists('isi', $context['butir'])
            || (int) data_get($context, 'tahap.id') !== (int) $stage->id
            || data_get($context, 'tahap.jenis') !== $stage->tahap_proses->value
            || (int) data_get($context, 'tahap.nomor_putaran') !== $stage->nomor_putaran
            || (int) data_get($context, 'tahap.putaran_asal_id') !== (int) $stage->putaran_asal_id
            || ! is_array($context['bahan'] ?? null) || ! is_array($context['kelengkapan_unit'] ?? null)
            || ! is_array($context['kompilasi'] ?? null)
            || ! array_key_exists('isi', $context['kompilasi'] ?? [])
            || ($context['jumlah_bahan'] ?? null) !== $compilation->bahan->count()
            || count($context['bahan']) !== $compilation->bahan->count()
            || $compilation->bahan->pluck('unit_kerja_id')->unique()->count() !== $compilation->bahan->count()) {
            return false;
        }
        foreach ($compilation->bahan as $material) {
            $copy = $material->salinan;
            $reference = collect($context['bahan'])->first(fn ($row): bool => ($row['tabel'] ?? null) === $material->jenis_sumber
                && (int) ($row['id'] ?? 0) === (int) $material->sumber_id);
            if (! is_array($copy) || $reference === null
                || ! in_array($material->jenis_sumber, ['tb_tanggapan', 'tb_tindak_lanjut'], true)
                || (int) $material->tahap_id !== (int) $stage->id || $material->butir_key !== $key
                || (int) ($copy['tahap_id'] ?? 0) !== (int) $stage->id || ($copy['butir_key'] ?? null) !== $key
                || (int) ($copy['record_id'] ?? 0) !== (int) $stage->record_id
                || ($copy['tahap_proses'] ?? null) !== $stage->tahap_proses->value
                || (int) ($copy['nomor_putaran'] ?? -1) !== $stage->nomor_putaran
                || (int) ($copy['putaran_asal_id'] ?? 0) !== (int) $stage->putaran_asal_id
                || data_get($copy, 'sumber.tabel') !== $material->jenis_sumber
                || (int) data_get($copy, 'sumber.id') !== (int) $material->sumber_id
                || (int) data_get($copy, 'unit.id') !== (int) $material->unit_kerja_id
                || ! array_key_exists('isi', $copy) || ! array_key_exists('deliverables', $copy)
                || (int) ($reference['unit_kerja_id'] ?? 0) !== (int) $material->unit_kerja_id
                || empty($copy['versi_sumber']) || ($reference['versi_sumber'] ?? null) !== $copy['versi_sumber']) {
                return false;
            }
        }

        return true;
    }

    public function canReview(User $user, SnpReview|DjsnReview $review): bool
    {
        $permission = 'canReview'.ucfirst($this->inputs->module($review)).'ByKomite';
        if (! $user->{$permission}($review->komite_id) || $review->status !== ReviuStatus::BelumDitanggapi->value
            || ! $this->hasVerifiedCompilation($review)) {
            return false;
        }
        $stage = $review->tahapProses;
        $record = $review->butir->record;
        $record->loadMissing('putaran');
        $round = $record->putaran->firstWhere('id', $stage->putaran_asal_id);

        return ! $record->isWorkflowClosed() && $record->status !== 'tuntas'
            && (int) $record->tahap_aktif_id === (int) $stage->id && $stage->archived_at === null
            && $round !== null && $round->archived_at === null
            && ($round->isLegacy() ? $record->putaran_aktif_id === null : (int) $record->putaran_aktif_id === (int) $round->id);
    }

    /** Called under the letter lock by the shared history model guard. */
    public function prepareForSave(SnpReview|DjsnReview $review, object $record): void
    {
        if ($review->exists) {
            $stored = $review->newQuery()->lockForUpdate()->findOrFail($review->id);
            foreach (['tahap_id', 'putaran_id', 'kompilasi_id', 'tahap_review', 'putaran_tl', 'id_butir_snp', 'id_butir_djsn',
                'komite_id', 'id_tanggapan', 'id_tindak_lanjut', 'created_by', 'created_at'] as $field) {
                if ($review->isDirty($field) && $review->getAttribute($field) != $stored->getAttribute($field)) {
                    throw new LogicException('Identitas, komite, dan sumber reviu resmi tidak boleh diganti.');
                }
            }
            if ($stored->status !== ReviuStatus::BelumDitanggapi->value) {
                throw new LogicException('Keputusan reviu yang sudah disimpan terkunci sebagai riwayat.');
            }
        }
        if ($review->status !== ReviuStatus::BelumDitanggapi->value && (! $review->exists || ! $review->isSavingWorkflowDecision())) {
            throw new LogicException('Keputusan reviu harus disimpan bersamaan dengan transisi tahap surat.');
        }
        $review->unsetRelation('tahapProses')->unsetRelation('kompilasiPutaran');
        $review->setRelation('tahapProses', $review->tahapProses()->lockForUpdate()->first());
        $compilation = $review->kompilasiPutaran()->lockForUpdate()->first();
        if ($compilation !== null) {
            $compilation->setRelation('bahan', $compilation->bahan()->lockForUpdate()->get());
        }
        $review->setRelation('kompilasiPutaran', $compilation);
        $stage = $review->tahapProses;
        $round = $stage?->putaranAsal()->lockForUpdate()->first();
        if (! $this->hasVerifiedCompilation($review) || (int) $record->tahap_aktif_id !== (int) $stage?->id
            || $stage?->archived_at !== null || $round === null || $round->archived_at !== null
            || ($round->isLegacy() ? $record->putaran_aktif_id !== null : (int) $record->putaran_aktif_id !== (int) $round->id)
            || $record->closed_at !== null || $record->status === 'tuntas') {
            throw new LogicException('Reviu harus menggunakan kompilasi terverifikasi pada tahap aktif surat yang masih terbuka.');
        }
        if (! $review->exists && $review->newQuery()->where('tahap_id', $stage->id)
            ->where('id_butir_'.$this->inputs->module($review), $review->{'id_butir_'.$this->inputs->module($review)})
            ->lockForUpdate()->first(['id']) !== null) {
            throw new LogicException('Reviu pada butir dan tahap ini sudah tersedia.');
        }
    }

    /** @param array<string, mixed> $data */
    public function review(SnpReview|DjsnReview $review, User $user, array $data, Request $request): ?SuratPutaran
    {
        abort_unless(IsianUnitTahapService::enabled(), 404);
        $permission = 'canReview'.ucfirst($this->inputs->module($review)).'ByKomite';
        abort_unless($user->{$permission}($review->komite_id), 403);
        $files = [];
        try {
            return $review->getConnection()->transaction(function () use ($review, $user, $data, $request, &$files): ?SuratPutaran {
                $butir = $review->butir;
                abort_unless($butir !== null, 422, 'Butir tidak tersedia.');
                $this->inputs->lockButir($butir);
                $review = $review->newQuery()->lockForUpdate()->findOrFail($review->id);
                $review->setRelation('butir', $butir);
                $review->setRelation('tahapProses', $butir->record->tahapanProses->firstWhere('id', $review->tahap_id));
                $compilation = $review->kompilasiPutaran()->lockForUpdate()->first();
                if ($compilation !== null) {
                    $compilation->setRelation('bahan', $compilation->bahan()->lockForUpdate()->get());
                }
                $review->setRelation('kompilasiPutaran', $compilation);
                abort_unless($this->canReview($user, $review), 422, 'Reviu sudah diputuskan, tahap terkunci, atau salinan kompilasi perlu rekonsiliasi.');
                app(SuratTanggalService::class)->validateReview($review, $data);
                $status = ReviuStatus::from($data['status']);
                $stage = $review->tahapProses;
                $round = $butir->record->putaran->firstWhere('id', $stage->putaran_asal_id);
                $old = ['review' => $review->getRawOriginal(), 'record' => $butir->record->getRawOriginal(), 'tahap' => $stage->toArray()];
                if ($status !== ReviuStatus::BelumDitanggapi) {
                    app(SuratPutaranPenugasan::class)->capture($round);
                    if ($stage->penugasan_unit === null) {
                        $stage->forceFill(['penugasan_unit' => $round->penugasan_unit])->save();
                    }
                }
                $review->fill([
                    'hasil_review' => app(SnpButirContent::class)->normalize($data['hasil_review'], $butir->record->id, $this->inputs->module($review), 'hasil_review', 'Hasil reviu'),
                    'deliverables' => $data['deliverables'] ?? null, 'status' => $status->value, 'updated_by' => $user->id,
                ]);
                foreach ($this->inputs->module($review) === 'snp' ? ['dokumen', 'dokumen_memo'] : ['dokumen'] as $field) {
                    if ($request->hasFile($field)) {
                        $review->{$field} = $request->file($field)->store('dokumen/reviu-'.$this->inputs->module($review), 'public');
                        abort_unless($review->{$field}, 422, 'Dokumen reviu gagal disimpan.');
                        $files[] = $review->{$field};
                    }
                }
                $review->saveWorkflowDecision();

                return DB::connection('mysql')->transaction(function () use ($review, $user, $data, $request, $stage, $round, $old): ?SuratPutaran {
                    app(SuratTanggalService::class)->decideProposal($review, $user, $data);
                    $next = null;
                    if ($review->status === ReviuStatus::TindakLanjutBelumSesuai->value) {
                        $next = $this->openFollowUp($review, $stage, $round, $user, $data);
                    } elseif (SuratStatus::tryFrom($review->status)?->isClosed()) {
                        $review->butir->record->forceFill([
                            'status' => $review->status, 'closed_at' => now(), 'closed_by' => $user->id,
                            'closed_review_id' => $review->id, 'closed_putaran_id' => $round->id, 'updated_by' => $user->id,
                        ])->save();
                    }
                    $this->log($review, $user, $request, $old, $next);

                    return $next;
                });
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($files);
            throw $exception;
        }
    }

    /** @param array<string, mixed> $data */
    private function openFollowUp(SnpReview|DjsnReview $review, SuratTahap $stage, SuratPutaran $round, User $user, array $data): SuratPutaran
    {
        $record = $review->butir->record;
        $stages = $record->tahapanProses()->lockForUpdate()->get();
        abort_if($stages->contains(fn ($row): bool => $row->nomor_putaran === null || $row->status_pemetaan !== 'terpetakan'), 422, 'Rekonsiliasi riwayat tahap diperlukan sebelum membuka putaran berikutnya.');
        $number = ((int) $stages->where('tahap_proses', TahapProses::TindakLanjut)->max('nomor_putaran')) + 1;
        $stage->forceFill(['archived_at' => now()])->save();
        $record->forceFill(['status' => SuratStatus::TindakLanjutBelumSesuai->value, 'updated_by' => $user->id])->save();
        $rounds = app(SuratPutaranService::class);
        if (! $round->isLegacy()) {
            $rounds->arsipkan($round, $user->id);
        }
        $nextRound = $rounds->buka($record, $user->id);
        app(SuratPutaranPenugasan::class)->capture($nextRound);
        $nextStage = $record->tahapanProses()->create([
            'putaran_asal_id' => $nextRound->id, 'kunci_asal' => 'reviu:'.$review->id,
            'tahap_proses' => TahapProses::TindakLanjut, 'nomor_putaran' => $number,
            'asal_pemetaan' => 'aplikasi', 'status_pemetaan' => 'terpetakan',
            'bukti_pemetaan' => ['review_id' => $review->id, 'kompilasi_id' => $review->kompilasi_id, 'tahap_sebelumnya_id' => $stage->id],
            'penugasan_unit' => $nextRound->penugasan_unit, 'mulai_at' => now(), 'created_by' => $user->id,
        ]);
        app(SuratTanggalService::class)->assignFollowUp($nextStage, $review, $user, $data);
        $record->forceFill(['tahap_aktif_id' => $nextStage->id])->save();

        return $nextRound;
    }

    /** @param array<string, mixed> $old */
    private function log(SnpReview|DjsnReview $review, User $user, Request $request, array $old, ?SuratPutaran $next): void
    {
        $module = $this->inputs->module($review);
        $closed = $review->butir->record->isWorkflowClosed();
        LogActivity::create([
            'user_id' => $user->id, 'type_code' => $module, 'database_name' => 'sidewas_'.$module,
            'table_name' => 'tb_review', 'record_key' => $review->{'id_butir_'.$module},
            'action' => $next ? 'buka_tahap_tindak_lanjut' : ($closed ? 'close_surat_tahap' : 'update_review_tahap'),
            'description' => $next ? 'Reviu membuka putaran Tindak Lanjut untuk seluruh unit pada surat. Tanggapan Awal tetap terkunci.'
                : ($closed ? 'Surat Close. Kelengkapan susulan tidak mengubah keputusan maupun salinan kompilasi.' : 'Reviu tahap aktif disimpan tanpa membuka putaran.'),
            'old_values' => $old,
            'new_values' => ['review' => $review->getRawOriginal(), 'kompilasi_id' => $review->kompilasi_id,
                'record' => $review->butir->record->getRawOriginal(), 'putaran_baru_id' => $next?->id],
            'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
        ]);
    }
}
