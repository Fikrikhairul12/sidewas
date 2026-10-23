<?php

namespace App\Services;

use App\Enums\TahapProses;
use App\Models\DjsnButir;
use App\Models\DjsnRecord;
use App\Models\DjsnReview;
use App\Models\SnpButir;
use App\Models\SnpRecord;
use App\Models\SnpReview;
use App\Models\SuratTahap;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SuratTanggalService
{
    public function enabled(string $connection): bool
    {
        return IsianUnitTahapService::enabled() && DB::connection($connection)->getSchemaBuilder()->hasTable('tb_surat_tanggal_riwayat');
    }

    public function suggestedDate(): string
    {
        return now('Asia/Jakarta')->addDays(14)->toDateString();
    }

    public function endOfDate(string $date): Carbon
    {
        return Carbon::parse($date, 'Asia/Jakarta')->endOfDay();
    }

    public function deadline(?SuratTahap $stage): ?Carbon
    {
        return $stage?->tanggal_ditetapkan_at !== null && $stage->jatuh_tempo !== null
            ? $this->endOfDate($stage->jatuh_tempo->toDateString()) : null;
    }

    public function recordDeadline(Model $record): ?Carbon
    {
        if (IsianUnitTahapService::enabled() && ($record instanceof SnpRecord || $record instanceof DjsnRecord)) {
            return $this->deadline($record->tahapAktif ?? $record->tahapanProses->firstWhere('tahap_proses', TahapProses::TanggapanAwal));
        }

        return $record->getAttribute('jth_tempo') === null ? null : Carbon::parse($record->jth_tempo);
    }

    /** Prepare a new letter at its first butir creation, before any saved-status synchronization. */
    public function preparePublication(SnpButir|DjsnButir $butir): void
    {
        if (! $this->enabled($butir->getConnectionName())) {
            return;
        }
        $butir->getConnection()->transaction(function () use ($butir): void {
            $record = $butir->record()->lockForUpdate()->firstOrFail();
            if ($record->isWorkflowClosed() || $record->pernah_terbit || $record->putaran_aktif_id !== null) {
                return;
            }
            $module = SuratTahap::moduleFor($butir->getConnectionName());
            if ($butir->newQuery()->where('id_'.$module, $record->{'id_'.$module})->lockForUpdate()->get()->count() !== 1) {
                return;
            }
            $stage = $record->tahapanProses()->where('tahap_proses', TahapProses::TanggapanAwal->value)->lockForUpdate()->first();
            if ($stage?->mulai_at !== null || $stage?->archived_at !== null || $stage?->tanggal_ditetapkan_at !== null) {
                return;
            }
            if ($stage === null) {
                $round = app(SuratPutaranService::class)->legacyFor($butir->getConnectionName(), $record->getRawOriginal());
                $stage = $record->tahapanProses()->create([
                    'putaran_asal_id' => $round->id, 'kunci_asal' => 'tanggapan_awal', 'tahap_proses' => TahapProses::TanggapanAwal,
                    'nomor_putaran' => 0, 'asal_pemetaan' => 'aplikasi', 'status_pemetaan' => 'terpetakan', 'created_by' => $butir->created_by,
                ]);
            }
            $stage->saveTanggal(['bukti_tanggal' => ($stage->bukti_tanggal ?? []) + ['menunggu_publikasi_R5' => true]]);
        });
    }

    /** A unit assignment makes an actually published butir available; opening a page never starts the clock. */
    public function publicationAssigned(Model $pic): void
    {
        if (! $this->enabled($pic->getConnectionName()) || ! in_array($pic->jenis_pic, ['utama', 'pendukung'], true) || $pic->unit_kerja_id === null) {
            return;
        }
        $butir = $pic->butir()->first();
        if ($butir === null || $butir->status === 'draft') {
            return;
        }
        $butir->getConnection()->transaction(function () use ($butir, $pic): void {
            $record = $butir->record()->lockForUpdate()->firstOrFail();
            $stage = $record->tahapanProses()->where('tahap_proses', TahapProses::TanggapanAwal->value)->lockForUpdate()->first();
            if ($record->isWorkflowClosed() || $record->putaran_aktif_id !== null || $stage === null
                || $stage->archived_at !== null || $stage->mulai_at !== null || $stage->tanggal_ditetapkan_at !== null
                || ! ($stage->bukti_tanggal['menunggu_publikasi_R5'] ?? false)) {
                return;
            }
            $start = now('Asia/Jakarta');
            $stage->saveTanggal([
                'mulai_at' => $start, 'jatuh_tempo' => $start->copy()->addDays(14)->toDateString(),
                'tanggal_ditetapkan_at' => $start, 'tanggal_ditetapkan_by' => Auth::id() ?? $pic->created_by,
                'bukti_tanggal' => array_merge($stage->bukti_tanggal ?? [], ['aturan' => 'BRD_1_4_R5', 'menunggu_publikasi_R5' => false,
                    'butir_id' => $butir->id, 'butir_pic_id' => $pic->id, 'tersedia_at' => $start->toISOString()]),
            ]);
            $record->forceFill(['tahap_aktif_id' => $stage->id])->save();
            $this->history($stage, 'publikasi_awal', 'publikasi:'.$stage->id, null, $stage->jatuh_tempo->toDateString(),
                'Batas respons awal 14 hari kalender sejak butir pertama tersedia bagi unit.', Auth::id() ?? $pic->created_by, ['butir_id' => $butir->id, 'butir_pic_id' => $pic->id]);
            app(SuratAwalStatusService::class)->synchronizeRecord($record);
        });
    }

    /** @param array<string, mixed> $data */
    public function validateCompilation(?SuratTahap $stage, array $data): void
    {
        if (empty($data['ubah_tgl'])) {
            return;
        }
        if ($stage?->tahap_proses !== TahapProses::TindakLanjut || $this->deadline($stage) === null) {
            throw ValidationException::withMessages(['ubah_tgl' => 'Pengajuan tanggal hanya untuk putaran Tindak Lanjut dengan jatuh tempo terverifikasi. Batas respons awal tidak dapat diubah.']);
        }
        validator($data, ['ubah_tgl' => ['required', 'date_format:Y-m-d', 'after:'.now('Asia/Jakarta')->toDateString()],
            'alasan_pengajuan_tgl' => ['required', 'string', 'max:2000']])->validate();
    }

    /** @param array<string, mixed> $data */
    public function validateReview(SnpReview|DjsnReview $review, array $data): void
    {
        if ($data['status'] === 'tindak_lanjut_belum_sesuai') {
            validator($data, [
                'jatuh_tempo_tl' => ['required', 'date_format:Y-m-d', 'after:'.now('Asia/Jakarta')->toDateString()],
                'alasan_jatuh_tempo' => [($data['jatuh_tempo_tl'] ?? null) === $this->suggestedDate() ? 'nullable' : 'required', 'string', 'max:2000'],
            ])->validate();
        }
        if (! in_array($data['status_pengajuan_tgl'] ?? 'pending', ['disetujui', 'ditolak'], true)) {
            return;
        }
        if ($review->tahapProses?->tahap_proses !== TahapProses::TindakLanjut || $review->kompilasiPutaran?->ubah_tgl === null) {
            throw ValidationException::withMessages(['status_pengajuan_tgl' => 'Tidak ada pengajuan tanggal Tindak Lanjut untuk diputuskan.']);
        }
        $compilation = $review->kompilasiPutaran;
        if ($compilation->status_pengajuan_tgl !== 'pending') {
            throw ValidationException::withMessages(['status_pengajuan_tgl' => 'Pengajuan tanggal sudah diputuskan dan tidak boleh diubah.']);
        }
        validator($data, ['alasan_keputusan_tgl' => ['required', 'string', 'max:2000']])->validate();
        if ($data['status_pengajuan_tgl'] === 'disetujui' && ! $compilation->ubah_tgl->isAfter(now('Asia/Jakarta')->startOfDay())) {
            throw ValidationException::withMessages(['status_pengajuan_tgl' => 'Tanggal yang disetujui harus sesudah tanggal reviu saat ini.']);
        }
    }

    /** @param array<string, mixed> $data */
    public function assignFollowUp(SuratTahap $stage, SnpReview|DjsnReview $review, User $user, array $data): void
    {
        $stage->saveTanggal([
            'jatuh_tempo' => $data['jatuh_tempo_tl'], 'tanggal_ditetapkan_at' => now(), 'tanggal_ditetapkan_by' => $user->id,
            'bukti_tanggal' => ['aturan' => 'BRD_1_4_R5', 'review_id' => $review->id, 'saran_sistem' => $this->suggestedDate()],
        ]);
        $this->history($stage, 'penetapan_tindak_lanjut', 'penetapan:'.$stage->id, null, $data['jatuh_tempo_tl'],
            filled($data['alasan_jatuh_tempo'] ?? null) ? $data['alasan_jatuh_tempo'] : 'Usulan sistem: tanggal reviu ditambah 14 hari kalender.',
            $user->id, ['review_id' => $review->id, 'saran_sistem' => $this->suggestedDate()], $review);
    }

    /** @param array<string, mixed> $data */
    public function decideProposal(SnpReview|DjsnReview $review, User $user, array $data): void
    {
        $decision = $data['status_pengajuan_tgl'] ?? 'pending';
        if (! in_array($decision, ['disetujui', 'ditolak'], true)) {
            return;
        }
        $stage = $review->tahapProses;
        $compilation = $review->kompilasiPutaran;
        $oldDate = $stage->jatuh_tempo?->toDateString();
        $compilation->forceFill(['status_pengajuan_tgl' => $decision, 'updated_by' => $user->id])->save();
        if ($decision === 'disetujui') {
            $stage->saveTanggal(['jatuh_tempo' => $compilation->ubah_tgl, 'tanggal_ditetapkan_at' => now(), 'tanggal_ditetapkan_by' => $user->id]);
        }
        $this->history($stage, 'pengajuan_'.$decision, 'pengajuan:'.$compilation->id, $oldDate, $compilation->ubah_tgl->toDateString(),
            $data['alasan_keputusan_tgl'], $user->id, ['status' => $decision, 'alasan_pengajuan' => $compilation->alasan_pengajuan_tgl], $review);
    }

    public function markSubmission(Model $source, SuratTahap $stage): void
    {
        if (! $this->enabled($source->getConnectionName())) {
            return;
        }
        $deadline = $this->deadline($stage);
        $source->forceFill(['jatuh_tempo_saat_pengisian' => $deadline?->toDateString(),
            'submitted_late_at' => $deadline !== null && now('Asia/Jakarta')->greaterThan($deadline) ? now() : null]);
    }

    /** @return array<string, mixed> */
    public function presentation(SnpRecord|DjsnRecord $record, ?SuratTahap $stage = null): array
    {
        $stage ??= $record->tahapAktif ?? $record->tahapanProses->firstWhere('tahap_proses', TahapProses::TanggapanAwal);
        $deadline = $this->deadline($stage);
        $late = $stage !== null && ! $record->isWorkflowClosed() && $record->status !== 'tuntas' && $stage->archived_at === null
            && (int) $record->tahap_aktif_id === (int) $stage->id && $stage->tahap_proses === TahapProses::TindakLanjut
            && $deadline !== null && now('Asia/Jakarta')->greaterThan($deadline) && $this->hasOutstandingOrLate($record, $stage);

        return [
            'label' => $stage === null ? 'Belum ada tahap' : $stage->tahap_proses->label().($stage->tahap_proses === TahapProses::TindakLanjut ? ' Putaran '.$stage->nomor_putaran : ''),
            'mulai' => $stage?->mulai_at === null ? null : $stage->mulai_at->setTimezone('Asia/Jakarta')->format('d/m/Y H:i').' WIB',
            'batas' => $deadline === null ? null : $deadline->format('d/m/Y').' 23.59 WIB', 'verified' => $deadline !== null,
            'late' => $late, 'historis' => $record->jth_tempo?->format('d/m/Y'),
            'history' => $stage === null || ! $this->enabled($record->getConnectionName()) ? [] : $stage->riwayatTanggal->map(fn ($entry): array => [
                'jenis' => str_replace('_', ' ', $entry->jenis), 'lama' => $entry->tanggal_lama?->format('d/m/Y') ?? '-',
                'diterapkan' => $entry->jenis !== 'pengajuan_ditolak',
                'baru' => $entry->tanggal_baru?->format('d/m/Y') ?? '-', 'alasan' => $entry->alasan,
                'pengguna' => $entry->bukti['pengguna_nama'] ?? ($entry->pengguna_id === null ? 'Sistem' : 'Pengguna #'.$entry->pengguna_id),
                'waktu' => $entry->diputuskan_at->setTimezone('Asia/Jakarta')->format('d/m/Y H:i').' WIB',
            ])->all(),
        ];
    }

    private function hasOutstandingOrLate(SnpRecord|DjsnRecord $record, SuratTahap $stage): bool
    {
        $db = $record->getConnection();
        foreach (['tb_tanggapan', 'tb_tindak_lanjut'] as $table) {
            if ($db->table($table)->where('tahap_id', $stage->id)->whereNotNull('submitted_late_at')->exists()) {
                return true;
            }
        }
        $module = SuratTahap::moduleFor($record->getConnectionName());
        $assigned = $db->table('tb_butir_pic as p')->join('tb_butir_'.$module.' as b', 'p.id_butir_'.$module, '=', 'b.id_butir_'.$module)
            ->where('b.id_'.$module, $record->{'id_'.$module})->whereIn('p.jenis_pic', ['utama', 'pendukung'])->whereNotNull('p.unit_kerja_id')
            ->get(['p.id_butir_'.$module.' as butir_key', 'p.unit_kerja_id'])
            ->map(fn ($pic): string => $pic->butir_key.':'.$pic->unit_kerja_id);
        foreach ($stage->penugasan_unit ?? [] as $key => $units) {
            foreach ($units as $unit) {
                $assigned->push($key.':'.$unit['id']);
            }
        }
        $filled = $db->table('tb_isian_unit_tahap')->where('tahap_id', $stage->id)->get(['butir_key', 'unit_kerja_id'])
            ->map(fn ($input): string => $input->butir_key.':'.$input->unit_kerja_id);

        return $assigned->unique()->diff($filled)->isNotEmpty();
    }

    /** @return list<int> */
    public function lateRecordIds(string $module): array
    {
        $class = $module === 'snp' ? SnpRecord::class : DjsnRecord::class;

        return $class::with('tahapAktif')->whereNull('closed_at')->whereNotIn('status', ['tuntas', 'tindak_lanjut_sesuai', 'tidak_dapat_ditindaklanjuti'])
            ->whereHas('tahapAktif', fn ($stage) => $stage
                ->where('tahap_proses', TahapProses::TindakLanjut->value)->whereNull('archived_at')->whereNotNull('tanggal_ditetapkan_at')
                ->where('jatuh_tempo', '<', now('Asia/Jakarta')->toDateString()))->get()
            ->filter(fn ($record): bool => $this->hasOutstandingOrLate($record, $record->tahapAktif))->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    public function applyFilter(Builder $query, string $value): void
    {
        if (! in_array($value, ['terlambat', 'tidak_terlambat'], true)) {
            return;
        }
        $ids = $this->lateRecordIds(SuratTahap::moduleFor($query->getModel()->getConnectionName()));
        $value === 'terlambat' ? $query->whereIn('tb_record.id', $ids) : $query->whereNotIn('tb_record.id', $ids);
    }

    /** @param array<string, mixed> $evidence */
    private function history(SuratTahap $stage, string $kind, string $key, ?string $old, ?string $new, string $reason, ?int $actor, array $evidence, SnpReview|DjsnReview|null $review = null): void
    {
        $stage->riwayatTanggal()->create(['record_id' => $stage->record_id, 'kompilasi_id' => $review?->kompilasi_id, 'review_id' => $review?->id,
            'kunci' => $key, 'jenis' => $kind, 'tanggal_lama' => $old, 'tanggal_baru' => $new, 'alasan' => $reason,
            'pengguna_id' => $actor, 'diputuskan_at' => now(), 'bukti' => $evidence + ['aturan' => 'BRD_1_4_R5',
                'pengguna_nama' => $actor === null ? 'Sistem' : User::find($actor)?->name,
                'surat' => $stage->surat->getRawOriginal()]]);
    }
}
