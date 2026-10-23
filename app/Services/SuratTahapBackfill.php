<?php

namespace App\Services;

use App\Enums\TahapProses;
use App\Models\SuratTahap;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SuratTahapBackfill
{
    /** @var list<string> */
    private const SOURCES = ['tb_tanggapan', 'tb_tindak_lanjut', 'tb_kompilasi', 'tb_review'];

    public function assertSourceRelations(string $connection): void
    {
        $module = SuratTahap::moduleFor($connection);
        $db = DB::connection($connection);
        if (! $db->getSchemaBuilder()->hasTable('tb_surat_putaran')) {
            throw new RuntimeException("{$connection}: fondasi putaran harus tersedia sebelum pemetaan tahap R1.");
        }
        (new SuratPutaranBackfill)->assertSourceRelations($connection);
        foreach (self::SOURCES as $table) {
            if ($db->table($table.' as s')->join('tb_butir_'.$module.' as b', 's.id_butir_'.$module, '=', 'b.id_butir_'.$module)
                ->join('tb_record as r', 'b.id_'.$module, '=', 'r.id_'.$module)
                ->leftJoin('tb_surat_putaran as p', 's.putaran_id', '=', 'p.id')
                ->where(fn ($query) => $query->whereNull('p.id')->orWhereColumn('p.record_id', '!=', 'r.id'))->exists()) {
                throw new RuntimeException("{$connection}.{$table}: sumber tanpa putaran yang sah harus diperiksa sebelum pemetaan R1.");
            }
        }
    }

    /** @return array{tahap_baru: int, sumber_dipetakan: int, rekonsiliasi_baru: int} */
    public function run(string $connection): array
    {
        $this->assertSourceRelations($connection);
        $db = DB::connection($connection);
        $module = SuratTahap::moduleFor($connection);
        $counts = ['tahap_baru' => 0, 'sumber_dipetakan' => 0, 'rekonsiliasi_baru' => 0];
        $db->table('tb_record')->whereNull('tahap_aktif_id')->orderBy('id')->chunkById(100, function ($records) use ($db, $module, &$counts): void {
            foreach ($records as $record) {
                $db->transaction(function () use ($db, $module, $record, &$counts): void {
                    $record = $db->table('tb_record')->where('id', $record->id)->lockForUpdate()->first();
                    if ($record === null || $record->tahap_aktif_id !== null) {
                        return;
                    }
                    $rounds = $db->table('tb_surat_putaran')->where('record_id', $record->id)->lockForUpdate()->get()->keyBy('id');
                    $legacy = $rounds->firstWhere('nomor_putaran', 0);
                    $sources = [];
                    foreach (self::SOURCES as $table) {
                        $sources[$table] = $db->table($table.' as s')
                            ->join('tb_butir_'.$module.' as b', 's.id_butir_'.$module, '=', 'b.id_butir_'.$module)
                            ->where('b.id_'.$module, $record->{'id_'.$module})->select('s.*')->orderBy('s.id')->get();
                    }
                    $hasLegacyFollowUps = collect($sources)->contains(fn ($rows, $table) => $rows->contains(
                        fn ($row) => $rounds->get($row->putaran_id)?->nomor_putaran === 0
                            && ($table === 'tb_tindak_lanjut' || in_array($row->tahap_kompilasi ?? $row->tahap_review ?? '', ['tindak_lanjut'], true)),
                    ));
                    $this->stage($db, $record, $legacy, TahapProses::TanggapanAwal, 0, 'tanggapan_awal', false, $counts);
                    foreach ($rounds as $round) {
                        if ((int) $round->nomor_putaran > 0) {
                            $this->stage($db, $record, $round, TahapProses::TindakLanjut,
                                $hasLegacyFollowUps ? null : (int) $round->nomor_putaran, 'putaran:'.$round->id, $hasLegacyFollowUps, $counts);
                        }
                    }
                    $seenUnits = [];
                    foreach ($sources as $table => $rows) {
                        foreach ($rows as $source) {
                            $round = $rounds->get($source->putaran_id);
                            if ($round === null) {
                                throw new RuntimeException('Putaran sumber berubah selama pemetaan; ulangi setelah sumber diperiksa.');
                            }
                            $stage = $this->resolveStage($db, $module, $record, $round, $source, $table, $hasLegacyFollowUps, $counts);
                            if ($stage === null) {
                                continue;
                            }
                            if ($table === 'tb_review' && ! $this->reviewLinksMatch($db, $module, $source, $stage)) {
                                $this->issue($db, $record->id, $stage->id, $table, $source->id, 'hubungan_reviu_tidak_konsisten',
                                    'Referensi sumber atau kompilasi reviu tidak berada pada butir dan tahap yang sama; hubungan lama tidak diubah.', (array) $source, $counts);

                                continue;
                            }
                            if ($source->tahap_id !== null && (int) $source->tahap_id !== (int) $stage->id) {
                                $this->issue($db, $record->id, $stage->id, $table, $source->id, 'pemetaan_berbeda',
                                    'Sumber sudah memiliki pemetaan berbeda; backfill tidak boleh memindahkannya.', (array) $source, $counts);

                                continue;
                            }
                            $counts['sumber_dipetakan'] += $db->table($table)->where('id', $source->id)->whereNull('tahap_id')->update(['tahap_id' => $stage->id]);
                            if (in_array($table, ['tb_tanggapan', 'tb_tindak_lanjut'], true)) {
                                $unit = $this->checkUnit($db, $module, $record, $stage, $source, $table, $counts);
                                if ($unit !== null) {
                                    $key = $stage->id.':'.$source->{'id_butir_'.$module}.':'.$unit;
                                    if (isset($seenUnits[$key])) {
                                        $first = $seenUnits[$key];
                                        $evidence = ['unit_kerja_id' => $unit, 'sumber' => [$first, ['tabel' => $table, 'id' => $source->id]]];
                                        foreach ([$first, ['tabel' => $table, 'id' => $source->id]] as $duplicate) {
                                            $this->issue($db, $record->id, $stage->id, $duplicate['tabel'], $duplicate['id'], 'isian_unit_ganda',
                                                'Ada lebih dari satu isian untuk unit, butir, dan tahap yang sama. Seluruh sumber dipertahankan untuk rekonsiliasi.', $evidence, $counts);
                                        }
                                    } else {
                                        $seenUnits[$key] = ['tabel' => $table, 'id' => $source->id];
                                    }
                                }
                            }
                            if ($table === 'tb_kompilasi' && $source->status_bahan === 'perlu_rekonsiliasi') {
                                $this->issue($db, $record->id, $stage->id, $table, $source->id, 'bahan_historis_belum_terbukti',
                                    'Belum ada bukti daftar dan versi isi bahan pada saat Simpan Kompilasi. Waktu pembuatan atau perubahan sumber saja tidak membuktikan salinan historis.',
                                    ['kompilasi' => (array) $source, 'salinan_otomatis_dibentuk' => false], $counts);
                            }
                        }
                    }
                });
            }
        });

        return $counts;
    }

    /** @param array<string, int> $counts */
    private function resolveStage(Connection $db, string $module, object $record, object $round, object $source, string $table, bool $hasLegacyFollowUps, array &$counts): ?SuratTahap
    {
        $flag = $source->tahap_kompilasi ?? $source->tahap_review ?? null;
        if ($flag !== null && ! in_array($flag, ['tanggapan', 'tindak_lanjut'], true)) {
            $this->issue($db, $record->id, null, $table, $source->id, 'tahap_sumber_tidak_dikenal',
                'Label tahap lama tidak dikenal; tahap baru tidak ditetapkan melalui perkiraan.', (array) $source, $counts);

            return null;
        }
        if ((int) $round->nomor_putaran > 0) {
            $stage = $this->stage($db, $record, $round, TahapProses::TindakLanjut,
                $hasLegacyFollowUps ? null : (int) $round->nomor_putaran, 'putaran:'.$round->id, $hasLegacyFollowUps, $counts);
            if ($hasLegacyFollowUps) {
                $this->issue($db, $record->id, $stage->id, $table, $source->id, 'nomor_putaran_perlu_rekonsiliasi',
                    'Surat memiliki TL lama dan looping Tanggapan. Urutan TL pada tingkat surat perlu diperiksa tanpa mengubah nomor putaran asal.', (array) $source, $counts);
            }

            return $stage;
        }
        if ($table !== 'tb_tindak_lanjut' && $flag !== 'tindak_lanjut') {
            return $this->stage($db, $record, $round, TahapProses::TanggapanAwal, 0, 'tanggapan_awal', false, $counts);
        }
        $number = isset($source->putaran_tl) && filter_var($source->putaran_tl, FILTER_VALIDATE_INT) !== false && (int) $source->putaran_tl > 0
            ? (int) $source->putaran_tl : null;
        if ($table === 'tb_review' && $number === null && $source->id_tindak_lanjut !== null) {
            $linkedStage = $db->table('tb_tindak_lanjut')->where('id', $source->id_tindak_lanjut)
                ->where('id_butir_'.$module, $source->{'id_butir_'.$module})->where('putaran_id', $round->id)->value('tahap_id');
            if ($linkedStage !== null) {
                return SuratTahap::on($db->getName())->findOrFail($linkedStage);
            }
        }
        $stage = $this->stage($db, $record, $round, TahapProses::TindakLanjut, $number,
            $number === null ? 'tl_lama:'.$table.':'.$source->id : 'tl_lama:'.$number, true, $counts);
        $this->issue($db, $record->id, $stage->id, $table, $source->id, 'nomor_tl_lama_perlu_rekonsiliasi',
            $number === null ? 'Sumber TL lama tidak memiliki nomor putaran yang terbukti; waktu pembuatan tidak digunakan untuk menebaknya.'
                : 'Nomor TL lama dipertahankan dari sumber; kesesuaian penomoran per butir terhadap putaran pada tingkat surat perlu diperiksa.', (array) $source, $counts);

        return $stage;
    }

    /** @param array<string, int> $counts */
    private function stage(Connection $db, object $record, ?object $round, TahapProses $type, ?int $number, string $key, bool $needsReconciliation, array &$counts): SuratTahap
    {
        $stage = SuratTahap::on($db->getName())->firstOrCreate(['record_id' => $record->id, 'kunci_asal' => $key], [
            'putaran_asal_id' => $round?->id, 'tahap_proses' => $type, 'nomor_putaran' => $number,
            'asal_pemetaan' => 'historis', 'status_pemetaan' => $needsReconciliation ? 'perlu_rekonsiliasi' : 'terpetakan',
            'bukti_pemetaan' => ['surat_asal' => (array) $record, 'putaran_asal' => $round === null ? null : (array) $round, 'aturan' => 'BRD_1_4_R1', 'kunci_asal' => $key],
            'penugasan_unit' => isset($round->penugasan_unit) ? json_decode($round->penugasan_unit, true, flags: JSON_THROW_ON_ERROR) : null,
            'mulai_at' => $round !== null && (int) $round->nomor_putaran > 0 ? $round->opened_at : null,
            'bukti_tanggal' => ['tanggal_surat_lama' => $record->tanggal_surat ?? null, 'jatuh_tempo_surat_lama' => $record->jth_tempo ?? null,
                'tanggal_lama_bukan_penetapan_otomatis' => true],
            'archived_at' => $round?->archived_at, 'created_by' => $round?->created_by ?? $record->created_by,
        ]);
        $counts['tahap_baru'] += $stage->wasRecentlyCreated ? 1 : 0;
        if ($stage->archived_at === null && $round?->archived_at !== null) {
            $stage->update(['archived_at' => $round->archived_at]);
        }
        $module = SuratTahap::moduleFor($db->getName());
        $needsDateEvidence = $type === TahapProses::TindakLanjut
            || $db->table('tb_butir_'.$module)->where('id_'.$module, $record->{'id_'.$module})->exists();
        if ($needsDateEvidence && $stage->tanggal_ditetapkan_at === null) {
            $this->issue($db, $record->id, $stage->id, 'tb_surat_tahap', $stage->id, 'tanggal_tahap_belum_terverifikasi',
                'Tanggal mulai Tanggapan atau jatuh tempo TL belum ditetapkan berdasarkan bukti penerbitan/keputusan. Tanggal surat dan tenggat lama tetap disimpan tanpa dijadikan aturan baru secara otomatis.',
                $stage->bukti_tanggal, $counts);
        }
        if ($needsReconciliation) {
            $this->issue($db, $record->id, $stage->id, 'tb_surat_tahap', $stage->id, 'identitas_putaran_perlu_rekonsiliasi',
                'Pemetaan tahap mempertahankan sumber lama; penomoran TL pada tingkat surat perlu diperiksa sebelum aktivasi.', $stage->bukti_pemetaan, $counts);
        }

        return $stage;
    }

    private function reviewLinksMatch(Connection $db, string $module, object $review, SuratTahap $stage): bool
    {
        foreach (['kompilasi_id' => 'tb_kompilasi', 'id_tanggapan' => 'tb_tanggapan', 'id_tindak_lanjut' => 'tb_tindak_lanjut'] as $column => $table) {
            if (($review->{$column} ?? null) !== null && ! $db->table($table)->where('id', $review->{$column})
                ->where('id_butir_'.$module, $review->{'id_butir_'.$module})->where('tahap_id', $stage->id)->exists()) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, int> $counts */
    private function checkUnit(Connection $db, string $module, object $record, SuratTahap $stage, object $source, string $table, array &$counts): ?int
    {
        $pic = isset($source->butir_pic_id) ? $db->table('tb_butir_pic')->where('id', $source->butir_pic_id)
            ->where('id_butir_'.$module, $source->{'id_butir_'.$module})->whereIn('jenis_pic', ['utama', 'pendukung'])->first() : null;
        $unit = $source->unit_kerja_id ?? $pic?->unit_kerja_id;
        if ($unit === null || isset($source->butir_pic_id) && $pic === null
            || $pic !== null && ($pic->unit_kerja_id === null || isset($source->unit_kerja_id) && (int) $source->unit_kerja_id !== (int) $pic->unit_kerja_id)) {
            $this->issue($db, $record->id, $stage->id, $table, $source->id, 'unit_pengisi_belum_terverifikasi',
                'Unit pengisi tidak dapat dipastikan atau bertentangan dengan PIC sumber. Unit tidak ditebak dari keanggotaan pengguna saat ini.', (array) $source, $counts);

            return null;
        }

        return (int) $unit;
    }

    /**
     * @param  array<string, mixed>|null  $evidence
     * @param  array<string, int>  $counts
     */
    private function issue(Connection $db, int $recordId, ?int $stageId, string $table, int $sourceId, string $code, string $reason, ?array $evidence, array &$counts): void
    {
        $counts['rekonsiliasi_baru'] += $db->table('tb_surat_rekonsiliasi')->insertOrIgnore([
            'record_id' => $recordId, 'tahap_id' => $stageId, 'tabel_sumber' => $table, 'sumber_id' => $sourceId,
            'kode' => $code, 'alasan' => $reason, 'bukti' => json_encode($evidence, JSON_THROW_ON_ERROR),
            'status' => 'terbuka', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
