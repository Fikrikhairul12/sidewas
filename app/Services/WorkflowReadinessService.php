<?php

namespace App\Services;

use App\Enums\ReviuStatus;
use App\Enums\SuratStatus;
use App\Enums\TahapProses;
use App\Models\SuratTahap;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class WorkflowReadinessService
{
    /** @param list<string> $modules
     * @return array<string, mixed>
     */
    public function inspect(array $modules = ['snp', 'djsn'], bool $withMain = false): array
    {
        if ($modules === [] || array_diff($modules, ['snp', 'djsn']) !== []) {
            throw new InvalidArgumentException('Modul harus snp atau djsn.');
        }
        $result = ['version' => 1, 'checked_at' => now('Asia/Jakarta')->toIso8601String(), 'main_checked' => $withMain, 'ready' => true, 'modules' => []];
        foreach (array_unique($modules) as $module) {
            $issues = [];
            $db = DB::connection('mysql_'.$module);
            $schema = $db->getSchemaBuilder();
            $required = ['tb_record' => ['tahap_aktif_id', 'putaran_aktif_id', 'closed_at', 'closed_review_id', 'closed_putaran_id'],
                'tb_butir_'.$module => ['id_'.$module, 'id_butir_'.$module], 'tb_butir_pic' => ['unit_kerja_id', 'komite_id'],
                'tb_surat_putaran' => ['record_id', 'nomor_putaran', 'archived_at'],
                'tb_surat_tahap' => ['bukti_tanggal', 'tanggal_ditetapkan_at'], 'tb_surat_rekonsiliasi' => ['catatan_penyelesaian'],
                'tb_surat_tanggal_riwayat' => ['bukti'], 'tb_isian_unit_tahap' => ['tabel_sumber'],
                'tb_kompilasi' => ['salinan_konteks', 'kunci_salinan', 'alasan_pengajuan_tgl'], 'tb_kompilasi_bahan' => ['salinan'],
                'tb_tanggapan' => ['tahap_id', 'submitted_late_at', 'submitted_after_compilation_at'],
                'tb_tindak_lanjut' => ['tahap_id', 'submitted_after_close_at'], 'tb_review' => ['tahap_id', 'kompilasi_id']];
            foreach ($required as $table => $columns) {
                if (! $schema->hasTable($table) || ! $schema->hasColumns($table, $columns)) {
                    $issues[] = $this->issue('schema_belum_siap', null, $table, null, 'Migrasi R1 sampai R5 belum lengkap.');
                }
            }
            if (config('sidewas.alur_revisi_enabled') && ! config('sidewas.reviu_putaran_enabled')) {
                $issues[] = $this->issue('flag_putaran_belum_aktif', null, null, null, 'SIDEWAS_REVIU_PUTARAN_ENABLED harus tetap true saat alur revisi diaktifkan.');
            }
            if (config('app.timezone') !== 'Asia/Jakarta') {
                $issues[] = $this->issue('timezone_app_tidak_sesuai', null, null, null, 'Zona waktu aplikasi harus Asia/Jakarta untuk batas 23.59 WIB.');
            }
            if ($issues === []) {
                try {
                    app(SuratTahapBackfill::class)->assertSourceRelations('mysql_'.$module);
                } catch (\Throwable $exception) {
                    $issues[] = $this->issue('hubungan_sumber_rusak', null, null, null, $exception->getMessage());
                }
                $class = 'App\\Models\\'.ucfirst($module).'Record';
                $class::query()->with(['tahapanProses', 'putaran', 'tahapAktif', 'rekonsiliasi',
                    'butir'.ucfirst($module).'.kompilasis.bahan', 'butir'.ucfirst($module).'.kompilasis.tahapProses',
                    'butir'.ucfirst($module).'.reviews'])->orderBy('id')->chunkById(100, function ($records) use ($module, &$issues): void {
                        foreach ($records as $record) {
                            $issues = [...$issues, ...$this->recordIssues($module, $record)];
                        }
                    });
                if ($withMain) {
                    $this->checkMain($module, $issues);
                }
            }
            $blockers = count(array_filter($issues, fn ($issue): bool => $issue['severity'] === 'blocker'));
            $result['modules'][$module] = ['ready' => $blockers === 0, 'blockers' => $blockers, 'issues' => $issues];
            $result['ready'] = $result['ready'] && $blockers === 0;
        }

        return $result;
    }

    /** Read only account IDs/status and existing role relations; never select passwords or email. */
    private function checkMain(string $module, array &$issues): void
    {
        try {
            $schema = DB::connection('mysql')->getSchemaBuilder();
            foreach (['users', 'tb_role', 'tb_role_type', 'tb_user_role_type', 'tb_user_unit_kerja', 'tb_user_komite', 'tb_unit_kerja', 'tb_komite'] as $table) {
                if (! $schema->hasTable($table)) {
                    throw new \RuntimeException('Skema utama belum tersedia.');
                }
            }
            $users = User::query()->select(['id', 'status'])->where('status', 'active')->get();
            $permission = 'canCreate'.ucfirst($module).'Kompilasi';
            if (! $users->contains(fn (User $user): bool => $user->{$permission}())) {
                $issues[] = $this->issue('petugas_kompilasi_tidak_tersedia', null, null, null, 'Tidak ada akun aktif yang berwenang mengompilasi pada modul ini.');
            }
            $db = DB::connection('mysql_'.$module);
            $pics = $db->table('tb_butir_pic as p')->join('tb_butir_'.$module.' as b', 'p.id_butir_'.$module, '=', 'b.id_butir_'.$module)
                ->join('tb_record as r', 'b.id_'.$module, '=', 'r.id_'.$module)->whereNull('r.closed_at')
                ->whereNotIn('r.status', ['tuntas', 'tindak_lanjut_sesuai', 'tidak_dapat_ditindaklanjuti'])
                ->select(['p.unit_kerja_id', 'p.komite_id'])->get();
            foreach (['unit_kerja_id' => 'tb_unit_kerja', 'komite_id' => 'tb_komite'] as $field => $table) {
                $ids = $pics->pluck($field)->filter()->unique()->map(fn ($id): int => (int) $id)->all();
                $known = DB::connection('mysql')->table($table)->whereIn('id', $ids)->pluck('id')->map(fn ($id): int => (int) $id)->all();
                foreach (array_diff($ids, $known) as $id) {
                    $issues[] = $this->issue('referensi_master_tidak_ada', null, $table, $id, 'Penugasan aktif merujuk ID master yang tidak tersedia.');
                }
            }
            $permission = 'canReview'.ucfirst($module).'ByKomite';
            foreach ($pics->pluck('komite_id')->filter()->unique() as $committee) {
                if (! $users->contains(fn (User $user): bool => $user->{$permission}((int) $committee))) {
                    $issues[] = $this->issue('reviewer_komite_tidak_tersedia', null, 'tb_komite', (int) $committee, 'Belum ada akun aktif dengan kewenangan reviu untuk komite ini.');
                }
            }
        } catch (\Throwable) {
            $issues[] = $this->issue('database_utama_belum_diperiksa', null, null, null, 'Periksa koneksi dan skema database utama di server. Database akun tidak perlu diekspor atau dikirim.');
        }
    }

    /** Stable across reads; used to reject a reconciliation plan after the letter changes. */
    public function fingerprint(string $module, int $recordId): string
    {
        $connection = 'mysql_'.$module;
        SuratTahap::moduleFor($connection);
        $db = DB::connection($connection);
        $record = $db->table('tb_record')->find($recordId);
        if ($record === null) {
            throw new InvalidArgumentException('Surat tidak ditemukan.');
        }
        $state = ['record' => (array) $record];
        $butirs = $db->table('tb_butir_'.$module)->where('id_'.$module, $record->{'id_'.$module})->orderBy('id')->get();
        $state['butirs'] = $butirs->all();
        $keys = $butirs->pluck('id_butir_'.$module);
        foreach (['tb_butir_pic', 'tb_tanggapan', 'tb_tindak_lanjut', 'tb_kompilasi', 'tb_review'] as $table) {
            $state[$table] = $db->table($table)->whereIn('id_butir_'.$module, $keys)->orderBy('id')->get()->all();
        }
        foreach (['tb_surat_tahap', 'tb_surat_putaran', 'tb_surat_tanggal_riwayat', 'tb_surat_rekonsiliasi'] as $table) {
            $state[$table] = $db->table($table)->where('record_id', $recordId)->orderBy('id')->get()->all();
        }
        $stageIds = collect($state['tb_surat_tahap'])->pluck('id');
        $state['materials'] = $db->table('tb_kompilasi_bahan')->whereIn('tahap_id', $stageIds)->orderBy('id')->get()->all();

        return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    public function context(string $module, int $recordId): array
    {
        $db = DB::connection('mysql_'.$module);
        SuratTahap::moduleFor($db->getName());
        $record = $db->table('tb_record')->where('id', $recordId)->first(['id', 'id_'.$module, 'status', 'tahap_aktif_id', 'putaran_aktif_id']);
        if ($record === null) {
            throw new InvalidArgumentException('Surat tidak ditemukan.');
        }
        $keys = $db->table('tb_butir_'.$module)->where('id_'.$module, $record->{'id_'.$module})->pluck('id_butir_'.$module);
        $sources = [];
        foreach (['tb_tanggapan', 'tb_tindak_lanjut'] as $table) {
            $sources[$table] = $db->table($table)->whereIn('id_butir_'.$module, $keys)->orderBy('id')
                ->get(['id', 'id_butir_'.$module, 'tahap_id', 'putaran_id', 'unit_kerja_id', 'butir_pic_id', 'created_at'])->all();
        }

        return ['record' => (array) $record, 'stages' => $db->table('tb_surat_tahap')->where('record_id', $recordId)->orderBy('id')
            ->get(['id', 'tahap_proses', 'nomor_putaran', 'putaran_asal_id', 'status_pemetaan', 'mulai_at', 'jatuh_tempo', 'tanggal_ditetapkan_at', 'archived_at'])->all(),
            'sources' => $sources, 'compilations' => $db->table('tb_kompilasi')->whereIn('id_butir_'.$module, $keys)->orderBy('id')->get(['id', 'id_butir_'.$module, 'tahap_id', 'status', 'status_bahan'])->all()];
    }

    /** @return list<array<string, mixed>> */
    private function recordIssues(string $module, Model $record): array
    {
        $issues = [];
        $closed = $record->isWorkflowClosed() || $record->status === 'tuntas';
        $archived = $this->archivalDecision($record, 'tb_record', $record->id);
        $butirs = $record->{'butir'.ucfirst($module)};
        $this->checkSourceFiles($module, $record, $butirs->pluck('id_butir_'.$module)->all(), $issues);
        $active = $record->tahapAktif;
        if (! $closed && $butirs->isNotEmpty() && ($active === null || (int) $active->record_id !== (int) $record->id || $active->archived_at !== null)) {
            $issues[] = $this->issue('tahap_aktif_belum_sah', $record->id, 'tb_record', $record->id, 'Tahap aktif surat perlu dikonfirmasi berdasarkan bukti alur lama.');
        }
        if (! $closed && $active !== null) {
            $round = $record->putaran->firstWhere('id', $active->putaran_asal_id);
            if ($round === null || $round->archived_at !== null || ($record->putaran_aktif_id === null ? ! $round->isLegacy() : (int) $record->putaran_aktif_id !== (int) $round->id)) {
                $issues[] = $this->issue('putaran_aktif_tidak_konsisten', $record->id, 'tb_surat_tahap', $active->id, 'Tahap aktif dan putaran asal tidak sesuai.');
            }
            if ($active->tahap_proses === TahapProses::TindakLanjut && $record->status !== 'proses_tindak_lanjut_belum_sesuai') {
                $issues[] = $this->issue('status_perbaikan_tidak_konsisten', $record->id, 'tb_record', $record->id, 'Surat pada tahap Tindak Lanjut harus mempertahankan status Proses / Tindak Lanjut Belum Sesuai.');
            }
            $this->checkActiveInputs($module, $record, $active, $issues);
        }
        if (! $closed && ! in_array($record->status, array_keys(SuratStatus::options()), true)) {
            $issues[] = $this->issue('status_lama_perlu_diperiksa', $record->id, 'tb_record', $record->id, 'Status lama memerlukan pemeriksaan sebelum aktivasi.');
        }
        if ($closed && $record->status !== 'tuntas' && ($record->closed_at === null || $record->closed_review_id === null || $record->closed_putaran_id === null)) {
            $issues[] = $this->issue('metadata_close_belum_lengkap', $record->id, 'tb_record', $record->id, 'Keputusan Close belum mempunyai waktu dan hubungan reviu/putaran yang lengkap.');
        }
        foreach ($record->tahapanProses as $stage) {
            $readOnly = $archived || $this->archivalDecision($record, 'tb_surat_tahap', $stage->id);
            if ($stage->nomor_putaran === null || $stage->status_pemetaan !== 'terpetakan') {
                $issues[] = $this->issue('identitas_tahap_belum_sah', $record->id, 'tb_surat_tahap', $stage->id, 'Nomor atau pemetaan tahap belum terverifikasi.', $readOnly ? 'warning' : 'blocker');
            }
            if ($butirs->isNotEmpty() && ($stage->tanggal_ditetapkan_at === null || $stage->jatuh_tempo === null)) {
                $issues[] = $this->issue('tanggal_tahap_belum_terverifikasi', $record->id, 'tb_surat_tahap', $stage->id, 'Tanggal memerlukan bukti penerbitan atau keputusan reviewer.', $readOnly ? 'warning' : 'blocker');
            }
            if ($stage->tanggal_ditetapkan_at !== null && $stage->tahap_proses === TahapProses::TanggapanAwal
                && ($stage->mulai_at === null || $stage->jatuh_tempo?->toDateString() !== $stage->mulai_at->copy()->setTimezone('Asia/Jakarta')->addDays(14)->toDateString())) {
                $issues[] = $this->issue('batas_awal_tidak_konsisten', $record->id, 'tb_surat_tahap', $stage->id, 'Batas respons awal tidak sesuai 14 hari kalender sejak tersedia.');
            }
        }
        foreach ($butirs as $butir) {
            $butir->setRelation('record', $record);
            foreach ($butir->reviews as $review) {
                if (! $closed && $active !== null && (int) $review->tahap_id === (int) $active->id && ReviuStatus::tryFrom($review->status) === null) {
                    $issues[] = $this->issue('reviu_aktif_masih_alur_lama', $record->id, 'tb_review', $review->id, 'Keputusan dan tahap reviu lama perlu diperiksa sebelum dilanjutkan; status reviu tidak direset otomatis.');
                }
            }
            foreach ($butir->kompilasis as $compilation) {
                if (! filled($compilation->hasil_kompilasi) && $compilation->status === 'belum_dikompilasi' && $compilation->salinan_konteks === null) {
                    continue;
                }
                $compilation->setRelation('butir', $butir);
                try {
                    $valid = app(KompilasiBahanReader::class)->verified($compilation);
                } catch (\Throwable) {
                    $valid = false;
                }
                $readOnly = $archived || $this->archivalDecision($record, 'tb_kompilasi', $compilation->id);
                if (! $valid) {
                    $official = $compilation->salinan_konteks !== null;
                    $issues[] = $this->issue($official ? 'salinan_resmi_tidak_sah' : 'bahan_historis_belum_terbukti', $record->id, 'tb_kompilasi', $compilation->id, 'Salinan bahan kompilasi belum dapat dijadikan bahan resmi.', $readOnly && ! $official ? 'warning' : 'blocker');

                    continue;
                }
                $context = $compilation->salinan_konteks;
                $this->checkFiles($context, $record->id, $compilation->id, $issues);
                foreach ($compilation->bahan as $material) {
                    $this->checkFiles($material->salinan, $record->id, $compilation->id, $issues);
                }
                if (! $butir->reviews->contains(fn ($review): bool => app(KompilasiBahanReader::class)->matchesReview($compilation, $review))) {
                    $issues[] = $this->issue('reviu_kompilasi_belum_terhubung', $record->id, 'tb_kompilasi', $compilation->id, 'Kompilasi resmi belum mempunyai reviu dengan hubungan yang sah.');
                }
            }
        }
        foreach ($record->rekonsiliasi->where('status', 'terbuka') as $item) {
            if ($item->kode === 'bahan_historis_belum_terbukti' && $item->tabel_sumber === 'tb_kompilasi') {
                $placeholder = $record->getConnection()->table('tb_kompilasi')->where('id', $item->sumber_id)->where('status', 'belum_dikompilasi')->whereNull('salinan_konteks')
                    ->where(fn ($query) => $query->whereNull('hasil_kompilasi')->orWhere('hasil_kompilasi', ''))->exists();
                if ($placeholder && ! $record->getConnection()->table('tb_review')->where('kompilasi_id', $item->sumber_id)->exists()) {
                    $issues[] = $this->issue('placeholder_belum_dikompilasi', $record->id, $item->tabel_sumber, $item->sumber_id, 'Catatan lama merujuk slot kompilasi kosong; slot ini bukan bahan proses resmi.', 'warning');

                    continue;
                }
            }
            if (! $archived && ! $this->archivalDecision($record, $item->tabel_sumber, $item->sumber_id)) {
                $issues[] = $this->issue('rekonsiliasi_terbuka:'.$item->kode, $record->id, $item->tabel_sumber, $item->sumber_id, $item->alasan);
            }
        }

        return $issues;
    }

    public function archivalDecision(Model $record, string $table, int $id): bool
    {
        return $record->rekonsiliasi->contains(fn ($item): bool => $item->status === 'selesai'
            && $item->tabel_sumber === $table && (int) $item->sumber_id === $id
            && $item->diselesaikan_by !== null && $item->diselesaikan_at !== null && filled($item->catatan_penyelesaian)
            && ($item->bukti['r7_action'] ?? null) === 'arsip_historis'
            && $this->proofIntact($item->bukti['dokumen'] ?? []));
    }

    /** @param array<string, mixed> $proof */
    private function proofIntact(array $proof): bool
    {
        return ($proof['disk'] ?? null) === 'local' && isset($proof['path'], $proof['sha256']) && str_starts_with($proof['path'], 'rekonsiliasi-r7/')
            && ! str_contains($proof['path'], '..') && Storage::disk('local')->exists($proof['path'])
            && hash('sha256', Storage::disk('local')->get($proof['path'])) === $proof['sha256'];
    }

    /** @param list<array<string, mixed>> $issues */
    private function checkActiveInputs(string $module, Model $record, SuratTahap $stage, array &$issues): void
    {
        $db = $record->getConnection();
        $seen = [];
        foreach (['tb_tanggapan', 'tb_tindak_lanjut'] as $table) {
            foreach ($db->table($table)->where('tahap_id', $stage->id)->get() as $source) {
                $pic = $source->butir_pic_id === null ? null : $db->table('tb_butir_pic')->where('id', $source->butir_pic_id)->where('id_butir_'.$module, $source->{'id_butir_'.$module})->first();
                $unit = $source->unit_kerja_id ?? $pic?->unit_kerja_id;
                $key = $source->{'id_butir_'.$module}.':'.$unit;
                if ($unit === null || $source->butir_pic_id !== null && ($pic === null || (int) $pic->unit_kerja_id !== (int) $unit)) {
                    $issues[] = $this->issue('unit_pengisi_belum_terverifikasi', $record->id, $table, $source->id, 'Unit pengisi tahap aktif belum dapat dibuktikan.');

                    continue;
                }
                if (isset($seen[$key])) {
                    $issues[] = $this->issue('isian_unit_ganda', $record->id, $table, $source->id, 'Ada isian ganda lintas sumber pada unit, butir, dan tahap yang sama.');
                }
                $seen[$key] = true;
                if (! $db->table('tb_isian_unit_tahap')->where(['tahap_id' => $stage->id, 'butir_key' => $source->{'id_butir_'.$module}, 'unit_kerja_id' => $unit, 'tabel_sumber' => $table, 'sumber_id' => $source->id])->exists()) {
                    $issues[] = $this->issue('registrasi_unit_belum_sah', $record->id, $table, $source->id, 'Registrasi satu isian per unit belum terhubung ke sumber yang benar.');
                }
            }
        }
    }

    /** @param array<string, mixed> $value
     * @param  list<array<string, mixed>>  $issues
     */
    private function checkFiles(array $value, int $recordId, int $compilationId, array &$issues): void
    {
        if (isset($value['disk'], $value['path'], $value['sha256'])) {
            if (! in_array($value['disk'], ['local', 'public'], true) || str_contains($value['path'], '..') || str_starts_with($value['path'], '/')
                || ! Storage::disk($value['disk'])->exists($value['path']) || hash('sha256', Storage::disk($value['disk'])->get($value['path'])) !== $value['sha256']) {
                $issues[] = $this->issue('berkas_salinan_tidak_utuh', $recordId, 'tb_kompilasi', $compilationId, 'Berkas salinan hilang atau berbeda dari hash saat kompilasi.');
            }
        }
        foreach ($value as $child) {
            if (is_array($child)) {
                $this->checkFiles($child, $recordId, $compilationId, $issues);
            }
        }
    }

    /**
     * @param  list<string>  $butirKeys
     * @param  list<array<string, mixed>>  $issues
     */
    private function checkSourceFiles(string $module, Model $record, array $butirKeys, array &$issues): void
    {
        $rows = ['tb_record' => [(object) $record->getRawOriginal()]];
        foreach (['tb_butir_'.$module, 'tb_tanggapan', 'tb_tindak_lanjut', 'tb_kompilasi', 'tb_review'] as $table) {
            $rows[$table] = $record->getConnection()->table($table)->whereIn('id_butir_'.$module, $butirKeys)->get();
        }
        foreach ($rows as $table => $sources) {
            foreach ($sources as $source) {
                $assets = [];
                foreach (['dokumen', 'dokumen_memo'] as $field) {
                    if (filled($source->{$field} ?? null)) {
                        $assets[] = ['disk' => 'public', 'path' => $source->{$field}, 'field' => $field];
                    }
                }
                foreach (['butir_'.$module, 'tanggapan', 'tindak_lanjut', 'hasil_kompilasi', 'hasil_review'] as $field) {
                    foreach (app(SnpButirContent::class)->images($source->{$field} ?? '') as $image) {
                        $assets[] = ['disk' => 'local', 'path' => $image['path'], 'field' => $field];
                    }
                }
                foreach ($assets as $asset) {
                    $path = $asset['path'];
                    if (str_contains($path, '..') || str_contains($path, ':') || str_contains($path, '\\') || str_starts_with($path, '/')
                        || ! Storage::disk($asset['disk'])->exists($path)) {
                        $issues[] = $this->issue('berkas_sumber_tidak_tersedia', $record->id, $table, $source->id,
                            'Berkas yang dirujuk kolom '.$asset['field'].' belum tersedia pada storage aplikasi.');
                    }
                }
            }
        }
    }

    /** @return array<string, mixed> */
    private function issue(string $code, ?int $recordId, ?string $table, ?int $id, string $message, string $severity = 'blocker'): array
    {
        return ['code' => $code, 'severity' => $severity, 'record_id' => $recordId, 'table' => $table, 'id' => $id, 'message' => $message];
    }
}
