<?php

namespace App\Console\Commands;

use App\Services\SuratTahapBackfill;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class BackfillSuratTahap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'workflow:backfill-tahap {--module=* : snp atau djsn; default keduanya} {--check : Tampilkan ringkasan pemetaan dan rekonsiliasi tanpa menulis data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Petakan tahap SNP/DJSN sesuai BRD 1.4 R1 tanpa memindahkan sumber atau menebak bahan historis';

    /**
     * Execute the console command.
     */
    public function handle(SuratTahapBackfill $backfill): int
    {
        $modules = $this->option('module') ?: ['snp', 'djsn'];
        if (array_diff($modules, ['snp', 'djsn']) !== []) {
            $this->error('Modul harus snp atau djsn.');

            return self::FAILURE;
        }
        try {
            foreach (array_unique($modules) as $module) {
                $connection = 'mysql_'.$module;
                foreach (['tb_surat_tahap', 'tb_surat_rekonsiliasi', 'tb_kompilasi_bahan'] as $table) {
                    if (! Schema::connection($connection)->hasTable($table)) {
                        $this->error("{$module}: jalankan migrasi fondasi tahap R1 terlebih dahulu.");

                        return self::FAILURE;
                    }
                }
                $backfill->assertSourceRelations($connection);
            }
            foreach (array_unique($modules) as $module) {
                $connection = 'mysql_'.$module;
                if (! $this->option('check')) {
                    $this->info(strtoupper($module).': '.json_encode($backfill->run($connection), JSON_THROW_ON_ERROR));
                }
                $rows = DB::connection($connection)->table('tb_surat_rekonsiliasi')->where('status', 'terbuka')
                    ->selectRaw('kode, COUNT(*) as jumlah')->groupBy('kode')->orderBy('kode')->get();
                $this->info(strtoupper($module).' rekonsiliasi terbuka: '.$rows->sum('jumlah'));
                $this->table(['Kode', 'Jumlah'], $rows->map(fn ($row) => [$row->kode, $row->jumlah])->all());
            }
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
