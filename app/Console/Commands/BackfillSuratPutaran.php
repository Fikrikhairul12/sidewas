<?php

namespace App\Console\Commands;

use App\Services\SuratPutaranBackfill;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

class BackfillSuratPutaran extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'workflow:backfill-putaran {--module=* : snp atau djsn; default keduanya}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Hubungkan riwayat alur lama SNP/DJSN ke putaran 0 tanpa mengubah isi atau waktu sumber';

    /**
     * Execute the console command.
     */
    public function handle(SuratPutaranBackfill $backfill): int
    {
        $modules = $this->option('module') ?: ['snp', 'djsn'];

        if (array_diff($modules, ['snp', 'djsn']) !== []) {
            $this->error('Modul harus snp atau djsn.');

            return self::FAILURE;
        }

        try {
            foreach (array_unique($modules) as $module) {
                $connection = 'mysql_'.$module;

                if (! Schema::connection($connection)->hasTable('tb_surat_putaran')) {
                    $this->error("{$module}: jalankan migrasi fondasi putaran terlebih dahulu.");

                    return self::FAILURE;
                }

                $counts = $backfill->run($connection);
                $this->info(strtoupper($module).': '.json_encode($counts, JSON_THROW_ON_ERROR));
            }
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
