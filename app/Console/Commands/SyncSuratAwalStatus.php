<?php

namespace App\Console\Commands;

use App\Services\SuratAwalStatusService;
use Illuminate\Console\Command;

class SyncSuratAwalStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'workflow:sync-status {--module=* : snp atau djsn; default keduanya}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronkan status awal surat SNP/DJSN berdasarkan butir, tanggapan, dan batas 14 hari kalender';

    /**
     * Execute the console command.
     */
    public function handle(SuratAwalStatusService $service): int
    {
        $modules = $this->option('module') ?: ['snp', 'djsn'];
        if (array_diff($modules, ['snp', 'djsn']) !== []) {
            $this->error('Modul harus snp atau djsn.');

            return self::FAILURE;
        }
        foreach (array_unique($modules) as $module) {
            if (! $service->enabled('mysql_'.$module)) {
                $this->error("{$module}: migrasi status awal belum terpasang.");

                return self::FAILURE;
            }
            $this->info(strtoupper($module).': '.$service->synchronizeModule($module).' surat diperbarui.');
        }

        return self::SUCCESS;
    }
}
