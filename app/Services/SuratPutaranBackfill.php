<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SuratPutaranBackfill
{
    public function assertSourceRelations(string $connection): void
    {
        $db = DB::connection($connection);
        $module = $this->module($connection);
        $butirColumn = 'id_butir_'.$module;
        $recordColumn = 'id_'.$module;

        if ($db->table('tb_butir_'.$module.' as b')->leftJoin('tb_record as r', 'b.'.$recordColumn, '=', 'r.'.$recordColumn)
            ->whereNull('r.id')->exists()) {
            throw new RuntimeException("{$connection}: ada butir tanpa surat; perbaiki hubungan sumber sebelum migrasi putaran.");
        }

        foreach ($this->sourceTables($connection) as $table) {
            if ($db->table($table.' as s')->leftJoin('tb_butir_'.$module.' as b', 's.'.$butirColumn, '=', 'b.'.$butirColumn)
                ->whereNull('b.id')->exists()) {
                throw new RuntimeException("{$connection}.{$table}: ada riwayat tanpa butir; migrasi tidak boleh menebak pemilik surat.");
            }
        }
    }

    /** @return array<string, int> */
    public function run(string $connection): array
    {
        $this->assertSourceRelations($connection);
        $db = DB::connection($connection);
        $module = $this->module($connection);
        $counts = ['putaran_lama' => 0];

        foreach ($this->sourceTables($connection) as $table) {
            if ($db->table($table.' as s')
                ->join('tb_butir_'.$module.' as b', 's.id_butir_'.$module, '=', 'b.id_butir_'.$module)
                ->join('tb_record as r', 'b.id_'.$module, '=', 'r.id_'.$module)
                ->whereNull('s.putaran_id')->whereNotNull('r.putaran_aktif_id')->exists()) {
                throw new RuntimeException("{$connection}.{$table}: ada data tanpa putaran pada surat yang sudah memakai alur baru; periksa sumber sebelum backfill.");
            }
        }

        $db->transaction(function () use ($db, $connection, $module, &$counts): void {
            $db->table('tb_record')->orderBy('id')->chunkById(100, function ($records) use ($db, &$counts): void {
                foreach ($records as $record) {
                    $counts['putaran_lama'] += $db->table('tb_surat_putaran')->insertOrIgnore($this->legacyAttributes((array) $record));
                }
            });

            foreach ($this->sourceTables($connection) as $table) {
                $counts[$table] = 0;
                $db->table($table.' as s')
                    ->join('tb_butir_'.$module.' as b', 's.id_butir_'.$module, '=', 'b.id_butir_'.$module)
                    ->join('tb_record as r', 'b.id_'.$module, '=', 'r.id_'.$module)
                    ->join('tb_surat_putaran as p', function ($join): void {
                        $join->on('p.record_id', '=', 'r.id')->where('p.nomor_putaran', 0);
                    })
                    ->whereNull('s.putaran_id')
                    ->select(['s.id', 'p.id as legacy_id'])
                    ->orderBy('s.id')
                    ->chunkById(100, function ($rows) use ($db, $table, &$counts): void {
                        foreach ($rows as $row) {
                            $counts[$table] += $db->table($table)->where('id', $row->id)->whereNull('putaran_id')
                                ->update(['putaran_id' => $row->legacy_id]);
                        }
                    }, 's.id', 'id');
            }

            if ($module === 'snp') {
                $this->linkLegacyUnits($db);
                $this->linkLegacyCompilations($db);
            }
        });

        return $counts;
    }

    /** @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    public function legacyAttributes(array $record): array
    {
        return [
            'record_id' => $record['id'],
            'nomor_putaran' => 0,
            'nomor_surat_lama' => $record['nomor_surat'],
            'status_lama' => $record['status'],
            'opened_at' => $record['created_at'],
            'created_by' => $record['created_by'],
            'created_at' => $record['created_at'],
            'updated_at' => $record['updated_at'],
        ];
    }

    /** @return list<string> */
    public function sourceTables(string $connection): array
    {
        $tables = ['tb_tanggapan', 'tb_tindak_lanjut', 'tb_review'];

        if (DB::connection($connection)->getSchemaBuilder()->hasTable('tb_kompilasi')) {
            $tables[] = 'tb_kompilasi';
        }

        return $tables;
    }

    private function module(string $connection): string
    {
        return match ($connection) {
            'mysql_snp' => 'snp',
            'mysql_djsn' => 'djsn',
            default => throw new RuntimeException('Backfill hanya tersedia untuk SNP dan DJSN.'),
        };
    }

    private function linkLegacyUnits(Connection $db): void
    {
        $db->table('tb_tanggapan as t')
            ->join('tb_surat_putaran as p', 't.putaran_id', '=', 'p.id')
            ->join('tb_butir_pic as pic', function ($join): void {
                $join->on('t.butir_pic_id', '=', 'pic.id')->on('t.id_butir_snp', '=', 'pic.id_butir_snp');
            })
            ->where('p.nomor_putaran', 0)->whereNull('t.unit_kerja_id')->whereNotNull('pic.unit_kerja_id')
            ->select(['t.id', 'pic.unit_kerja_id'])->orderBy('t.id')
            ->chunkById(100, function ($rows) use ($db): void {
                foreach ($rows as $row) {
                    $db->table('tb_tanggapan')->where('id', $row->id)->whereNull('unit_kerja_id')
                        ->update(['unit_kerja_id' => $row->unit_kerja_id]);
                }
            }, 't.id', 'id');
    }

    private function linkLegacyCompilations(Connection $db): void
    {
        $db->table('tb_review as r')->join('tb_surat_putaran as p', 'r.putaran_id', '=', 'p.id')
            ->where('p.nomor_putaran', 0)->whereNull('r.kompilasi_id')->select('r.*')->orderBy('r.id')
            ->chunkById(100, function ($reviews) use ($db): void {
                foreach ($reviews as $review) {
                    $compilations = $db->table('tb_kompilasi')->where('putaran_id', $review->putaran_id)
                        ->where('id_butir_snp', $review->id_butir_snp)->where('tahap_kompilasi', $review->tahap_review)
                        ->where('putaran_tl', $review->putaran_tl)->pluck('id');

                    if ($compilations->count() === 1) {
                        $db->table('tb_review')->where('id', $review->id)->whereNull('kompilasi_id')
                            ->update(['kompilasi_id' => $compilations->first()]);
                    }
                }
            }, 'r.id', 'id');
    }
}
