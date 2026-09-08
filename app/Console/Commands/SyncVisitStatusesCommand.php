<?php

namespace App\Console\Commands;

use App\Services\Kunjungan\VisitWorkflowService;
use Illuminate\Console\Command;

class SyncVisitStatusesCommand extends Command
{
    protected $signature = 'visits:sync-status';

    protected $description = 'Ubah kunjungan APPROVED yang sudah mulai menjadi ONGOING';

    public function handle(VisitWorkflowService $workflow): int
    {
        $count = $workflow->startDueVisits();
        $this->components->info("{$count} kunjungan diperbarui menjadi sedang berlangsung.");

        return self::SUCCESS;
    }
}
