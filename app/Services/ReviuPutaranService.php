<?php

namespace App\Services;

use App\Enums\ReviuStatus;
use App\Enums\SuratStatus;
use App\Models\DjsnButir;
use App\Models\DjsnKompilasi;
use App\Models\DjsnReview;
use App\Models\LogActivity;
use App\Models\SnpButir;
use App\Models\SnpKompilasi;
use App\Models\SnpReview;
use App\Models\SuratPutaran;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ReviuPutaranService
{
    public static function enabled(): bool
    {
        return (bool) config('sidewas.reviu_putaran_enabled', false);
    }

    /** @return class-string<SnpButir>|class-string<DjsnButir> */
    public function butirClass(string $module): string
    {
        return match ($module) {
            'snp' => SnpButir::class,
            'djsn' => DjsnButir::class,
            default => abort(404),
        };
    }

    /** @return class-string<SnpReview>|class-string<DjsnReview> */
    public function reviewClass(string $module): string
    {
        return $module === 'snp' ? SnpReview::class : DjsnReview::class;
    }

    public function module(Model $model): string
    {
        return $model->getConnectionName() === 'mysql_snp' ? 'snp' : 'djsn';
    }

    public function roundId(SnpButir|DjsnButir $butir): ?int
    {
        return app(TanggapanUnitService::class)->currentRoundId($butir);
    }

    /** @return Collection<int, Model> */
    public function current(Collection $rows, SnpButir|DjsnButir $butir): Collection
    {
        $roundId = $this->roundId($butir);

        return $rows->filter(fn ($row) => ($row->putaran_id === null
            ? $butir->record?->putaran_aktif_id === null
            : (int) $row->putaran_id === $roundId)
            && ($row->getTable() !== 'tb_tanggapan' || $row->submitted_after_close_at === null));
    }

    public function canCompile(User $user, SnpButir|DjsnButir $butir): bool
    {
        $permission = 'canCreate'.ucfirst($this->module($butir)).'Kompilasi';
        if (! $user->{$permission}() || ! $this->roundIsOpen($butir)) {
            return false;
        }
        $submitted = $this->current($butir->kompilasis, $butir)->contains(fn ($row) => $row->tahap_kompilasi === 'tanggapan' && $row->status !== 'belum_dikompilasi');
        $reviewed = $this->current($butir->reviews, $butir)->contains(fn ($row) => filled($row->hasil_review) || $row->status !== 'belum_ditanggapi');
        if ($butir->record->putaran_aktif_id !== null && $this->current($butir->reviews, $butir)->isNotEmpty()) {
            return false;
        }

        return ! $submitted && ! $reviewed;
    }

    public function canReview(User $user, SnpReview|DjsnReview $review): bool
    {
        $permission = 'canReview'.ucfirst($this->module($review)).'ByKomite';
        $compilation = $review->kompilasiPutaran;

        return $user->{$permission}($review->komite_id)
            && $review->butir !== null && $this->roundIsOpen($review->butir)
            && (int) $review->putaran_id === $this->roundId($review->butir)
            && $review->status === ReviuStatus::BelumDitanggapi->value
            && $review->tahap_review === 'tanggapan'
            && $compilation !== null
            && $compilation->tahap_kompilasi === 'tanggapan'
            && (int) $compilation->putaran_id === (int) $review->putaran_id
            && $compilation->{'id_butir_'.$this->module($review)} === $review->{'id_butir_'.$this->module($review)}
            && $compilation->status === 'dalam_proses_reviu_dewas';
    }

    public function assertCanUploadCompilation(User $user, SnpButir|DjsnButir $butir, ?int $expectedRound): void
    {
        $butir->getConnection()->transaction(function () use ($user, $butir, $expectedRound): void {
            $this->lockContext($butir, $expectedRound);
            abort_unless($this->canCompile($user, $butir), 422, 'Kompilasi sudah dikirim, sudah direviu, atau putaran tidak aktif.');
        });
    }

    /** @param array<string, mixed> $data */
    public function compile(SnpButir|DjsnButir $butir, User $user, array $data, Request $request): SnpKompilasi|DjsnKompilasi
    {
        abort_unless(self::enabled(), 404);
        $permission = 'canCreate'.ucfirst($this->module($butir)).'Kompilasi';
        abort_unless($user->{$permission}(), 403);
        $newFiles = [];
        try {
            return $butir->getConnection()->transaction(function () use ($butir, $user, $data, $request, &$newFiles): SnpKompilasi|DjsnKompilasi {
                $round = $this->lockContext($butir, isset($data['putaran_id']) ? (int) $data['putaran_id'] : null);
                abort_unless($this->canCompile($user, $butir), 422, 'Kompilasi sudah dikirim, sudah direviu, atau putaran tidak aktif.');
                $module = $this->module($butir);
                $class = $module === 'snp' ? SnpKompilasi::class : DjsnKompilasi::class;
                $compilation = $this->current($butir->kompilasis, $butir)->firstWhere('tahap_kompilasi', 'tanggapan')
                    ?? new $class(['id_butir_'.$module => $butir->{'id_butir_'.$module}, 'putaran_id' => $round->id, 'tahap_kompilasi' => 'tanggapan', 'created_by' => $user->id]);
                $old = $compilation->exists ? $compilation->getRawOriginal() : null;
                $compilation->fill([
                    'hasil_kompilasi' => app(SnpButirContent::class)->normalize($data['hasil_kompilasi'], $butir->record->id, $module, 'hasil_kompilasi', 'Hasil kompilasi'),
                    'deliverables' => $data['deliverables'] ?? null, 'ubah_tgl' => $data['ubah_tgl'] ?? null,
                    'status_pengajuan_tgl' => empty($data['ubah_tgl']) ? null : 'pending',
                    'status' => 'dalam_proses_reviu_dewas', 'updated_by' => $user->id,
                ]);
                $this->replaceFile($request, $compilation, 'dokumen', 'dokumen/kompilasi-'.$module, $newFiles);
                $compilation->save();
                $reviewClass = $this->reviewClass($module);
                $review = new $reviewClass([
                    'id_butir_'.$module => $butir->{'id_butir_'.$module}, 'putaran_id' => $round->id,
                    'kompilasi_id' => $compilation->id, 'tahap_review' => 'tanggapan',
                    'komite_id' => $butir->butirPics->firstWhere('jenis_pic', 'komite')?->komite_id,
                    'status' => ReviuStatus::BelumDitanggapi->value, 'created_by' => $user->id, 'updated_by' => $user->id,
                ]);
                $review->save();
                $this->log($request, $user, $compilation, 'kompilasi_tanggapan_putaran', $old);

                return $compilation;
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($newFiles);
            throw $exception;
        }
    }

    /**
     * A decision either reopens the entire letter or freezes its final round for late completion.
     *
     * @param  array<string, mixed>  $data
     */
    public function review(SnpReview|DjsnReview $review, User $user, array $data, Request $request): ?SuratPutaran
    {
        abort_unless(self::enabled(), 404);
        $newFiles = [];
        try {
            return $review->getConnection()->transaction(function () use ($review, $user, $data, $request, &$newFiles): ?SuratPutaran {
                $butir = $review->butir;
                abort_unless($butir, 422, 'Butir tidak tersedia.');
                $round = $this->lockContext($butir, $review->putaran_id);
                $review = $review->newQuery()->with(['butir.record.putaran', 'kompilasiPutaran'])->lockForUpdate()->findOrFail($review->id);
                $permission = 'canReview'.ucfirst($this->module($review)).'ByKomite';
                abort_unless($user->{$permission}($review->komite_id), 403);
                abort_unless($this->canReview($user, $review), 422, 'Reviu ini merupakan riwayat, sudah diputuskan, atau belum memiliki kompilasi yang valid.');
                if ($data['status'] !== ReviuStatus::BelumDitanggapi->value) {
                    app(SuratPutaranPenugasan::class)->capture($round);
                }
                $old = $review->getRawOriginal();
                $review->fill([
                    'hasil_review' => app(SnpButirContent::class)->normalize($data['hasil_review'], $butir->record->id, $this->module($review), 'hasil_review', 'Hasil reviu'),
                    'deliverables' => $data['deliverables'] ?? null,
                    'status' => ReviuStatus::from($data['status'])->value, 'updated_by' => $user->id,
                ]);
                foreach ($this->module($review) === 'snp' ? ['dokumen', 'dokumen_memo'] : ['dokumen'] as $field) {
                    $this->replaceFile($request, $review, $field, 'dokumen/reviu-'.$this->module($review), $newFiles);
                }
                $review->save();
                $compilation = $review->kompilasiPutaran;
                if ($compilation->ubah_tgl && isset($data['status_pengajuan_tgl'])) {
                    $compilation->update(['status_pengajuan_tgl' => $data['status_pengajuan_tgl']]);
                    if ($data['status_pengajuan_tgl'] === 'disetujui') {
                        $butir->record->update(['jth_tempo' => $compilation->ubah_tgl]);
                    }
                }

                return DB::connection('mysql')->transaction(function () use ($review, $round, $user, $request, $old): ?SuratPutaran {
                    $nextRound = $review->status === ReviuStatus::TindakLanjutBelumSesuai->value
                        ? $this->reopenForRevision($review, $round, $user, $request)
                        : null;
                    if (SuratStatus::tryFrom($review->status)?->isClosed()) {
                        $this->closeLetter($review, $round, $user, $request);
                    }
                    $this->log($request, $user, $review, 'update_review_putaran', $old);

                    return $nextRound;
                });
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($newFiles);
            throw $exception;
        }
    }

    private function closeLetter(SnpReview|DjsnReview $review, SuratPutaran $round, User $user, Request $request): void
    {
        $record = $review->butir->record;
        $old = $record->getRawOriginal();
        $record->forceFill([
            'status' => $review->status, 'closed_at' => now(), 'closed_by' => $user->id,
            'closed_review_id' => $review->id, 'closed_putaran_id' => $round->id, 'updated_by' => $user->id,
        ])->save();

        LogActivity::create([
            'user_id' => $user->id, 'type_code' => $this->module($review), 'database_name' => 'sidewas_'.$this->module($review),
            'table_name' => 'tb_record', 'record_key' => $record->{'id_'.$this->module($review)},
            'action' => 'close_surat_putaran',
            'description' => 'Surat ditutup: '.SuratStatus::from($review->status)->label().'. Kelengkapan terlambat tidak menjadi bahan keputusan reviu.',
            'old_values' => $old, 'new_values' => $record->getRawOriginal(),
            'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
        ]);
    }

    private function reopenForRevision(SnpReview|DjsnReview $review, SuratPutaran $round, User $user, Request $request): SuratPutaran
    {
        $record = $review->butir->record;
        $old = ['record' => $record->getRawOriginal(), 'putaran' => $round->getRawOriginal()];
        $record->forceFill(['status' => SuratStatus::TindakLanjutBelumSesuai->value, 'updated_by' => $user->id])->save();

        $rounds = app(SuratPutaranService::class);
        if (! $round->isLegacy()) {
            $rounds->arsipkan($round, $user->id);
        }
        $nextRound = $rounds->buka($record, $user->id);

        LogActivity::create([
            'user_id' => $user->id, 'type_code' => $this->module($review), 'database_name' => 'sidewas_'.$this->module($review),
            'table_name' => 'tb_surat_putaran', 'record_key' => $record->{'id_'.$this->module($review)},
            'action' => 'buka_putaran_perbaikan',
            'description' => 'Reviu Tindak Lanjut Belum Sesuai membuka putaran '.$nextRound->nomor_putaran.' untuk seluruh unit pada surat.',
            'old_values' => $old,
            'new_values' => [
                'review_id' => $review->id, 'id_butir' => $review->{'id_butir_'.$this->module($review)},
                'putaran_sebelumnya' => $round->fresh()->toArray(), 'putaran_baru' => $nextRound->toArray(),
                'record' => $record->getRawOriginal(),
            ],
            'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
        ]);

        return $nextRound;
    }

    public function roundLabel(SnpButir|DjsnButir $butir): string
    {
        $number = $butir->record?->putaran->firstWhere('id', $this->roundId($butir))?->nomor_putaran;

        return $number ? 'Putaran '.$number : 'Putaran awal';
    }

    private function roundIsOpen(SnpButir|DjsnButir $butir): bool
    {
        $record = $butir->record;
        if ($record === null || $record->status === 'tuntas' || $record->isWorkflowClosed()) {
            return false;
        }
        $round = $record->putaran->firstWhere('id', $this->roundId($butir));

        return $round ? $round->archived_at === null : $record->putaran_aktif_id === null;
    }

    private function lockContext(SnpButir|DjsnButir $butir, ?int $expectedRound): SuratPutaran
    {
        $record = $butir->record()->lockForUpdate()->firstOrFail();
        $record->setRelation('putaran', $record->putaran()->lockForUpdate()->get());
        $butir->setRelation('record', $record);
        $current = $this->roundId($butir);
        abort_if(($expectedRound !== null && $expectedRound !== $current) || ($record->putaran_aktif_id !== null && $expectedRound === null), 422, 'Putaran telah berubah. Muat ulang halaman.');
        abort_unless($this->roundIsOpen($butir), 422, 'Putaran sudah diarsipkan atau surat sudah ditutup.');
        $round = $current ? $record->putaran->firstWhere('id', $current)
            : app(SuratPutaranService::class)->legacyFor($record->getConnectionName(), $record->getRawOriginal());
        $record->setRelation('putaran', $record->putaran()->lockForUpdate()->get());
        foreach (['kompilasis', 'reviews', 'butirPics'] as $relation) {
            $butir->setRelation($relation, $butir->{$relation}()->lockForUpdate()->get());
        }

        return $round;
    }

    /** @param list<string> $newFiles */
    private function replaceFile(Request $request, Model $model, string $field, string $directory, array &$newFiles): void
    {
        if (! $request->hasFile($field)) {
            return;
        }
        $old = $model->{$field};
        $model->{$field} = $request->file($field)->store($directory, 'public');
        $newFiles[] = $model->{$field};
        if ($old) {
            $model->getConnection()->afterCommit(fn () => Storage::disk('public')->delete($old));
        }
    }

    /** @param array<string, mixed>|null $old */
    private function log(Request $request, User $user, Model $model, string $action, ?array $old): void
    {
        LogActivity::create([
            'user_id' => $user->id, 'type_code' => $this->module($model), 'database_name' => 'sidewas_'.$this->module($model),
            'table_name' => $model->getTable(), 'record_key' => $model->{'id_butir_'.$this->module($model)},
            'action' => $action, 'description' => 'User menyimpan '.$action.'.',
            'old_values' => $old, 'new_values' => $model->fresh()->toArray(),
            'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
        ]);
    }
}
