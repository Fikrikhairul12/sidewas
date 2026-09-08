<?php

namespace App\Console\Commands;

use App\Services\Identity\SidewasEmployeeSynchronizer;
use Illuminate\Console\Command;

class SyncSidewasEmployeesCommand extends Command
{
    protected $signature = 'employees:sync-sidewas';

    protected $description = 'Sinkronkan direktori pegawai modul dengan master user SIDEWASI';

    public function handle(SidewasEmployeeSynchronizer $synchronizer): int
    {
        $count = $synchronizer->syncAll();

        $this->info("{$count} user SIDEWASI berhasil disinkronkan.");

        return self::SUCCESS;
    }
}
