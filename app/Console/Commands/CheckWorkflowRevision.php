<?php

namespace App\Console\Commands;

use App\Services\WorkflowReadinessService;
use Illuminate\Console\Command;

class CheckWorkflowRevision extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'workflow:check-revisi {--module=*} {--json} {--output=} {--if-enabled} {--with-main : Periksa master, role, dan penugasan pada database utama tanpa mencetak data akun} {--record= : Tampilkan fingerprint satu surat; harus memilih satu modul}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Periksa kesiapan R1 sampai R7 tanpa menulis atau menebak data historis';

    /**
     * Execute the console command.
     */
    public function handle(WorkflowReadinessService $readiness): int
    {
        if ($this->option('if-enabled') && ! config('sidewas.alur_revisi_enabled')) {
            $this->info('Alur revisi masih nonaktif. Pemeriksaan aktivasi dilewati.');

            return self::SUCCESS;
        }
        try {
            $modules = $this->option('module') ?: ['snp', 'djsn'];
            $result = $readiness->inspect($modules, (bool) ($this->option('with-main') || $this->option('if-enabled')));
            if ($this->option('record')) {
                if (count($modules) !== 1 || ! ctype_digit((string) $this->option('record'))) {
                    throw new \InvalidArgumentException('Fingerprint memerlukan --module=snp atau --module=djsn dan --record=ID.');
                }
                $result['record_fingerprint'] = $readiness->fingerprint($modules[0], (int) $this->option('record'));
                $result['record_context'] = $readiness->context($modules[0], (int) $this->option('record'));
            }
            $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            if ($path = $this->option('output')) {
                if (file_exists($path) || ! is_dir(dirname($path))) {
                    throw new \InvalidArgumentException('Gunakan nama laporan baru di direktori yang sudah tersedia.');
                }
                if (file_put_contents($path, $json, LOCK_EX) === false) {
                    throw new \RuntimeException('Laporan gagal disimpan.');
                }
                chmod($path, 0600);
            }
            if ($this->option('json')) {
                $this->line($json);
            } else {
                foreach ($result['modules'] as $module => $status) {
                    $this->info(strtoupper($module).': '.($status['ready'] ? 'siap diperiksa untuk aktivasi' : 'BELUM SIAP').'; '.$status['blockers'].' penghalang.');
                    $this->table(['Jenis', 'Surat', 'Sumber', 'ID', 'Kode'], array_map(fn ($issue): array => [$issue['severity'], $issue['record_id'], $issue['table'], $issue['id'], $issue['code']], $status['issues']));
                }
                if (isset($result['record_fingerprint'])) {
                    $this->line('Fingerprint: '.$result['record_fingerprint']);
                }
            }

            return $result['ready'] ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
