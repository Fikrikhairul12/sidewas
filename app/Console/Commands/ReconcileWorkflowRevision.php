<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\SuratRekonsiliasiService;
use Illuminate\Console\Command;

class ReconcileWorkflowRevision extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'workflow:rekonsiliasi-revisi {file : Rencana JSON beserta referensi bukti arsip} {--user= : ID Super Admin pemeriksa bukti} {--apply : Terapkan rencana yang sudah diperiksa; tanpa opsi ini hanya validasi}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validasi atau terapkan rekonsiliasi historis dengan bukti, fingerprint, dan audit R7';

    /**
     * Execute the console command.
     */
    public function handle(SuratRekonsiliasiService $service): int
    {
        try {
            $path = $this->argument('file');
            if (! is_file($path) || filesize($path) > 10 * 1024 * 1024 || ! ctype_digit((string) $this->option('user'))) {
                throw new \InvalidArgumentException('Rencana harus berupa file JSON maksimal 10 MB dan --user=ID pemeriksa wajib diisi.');
            }
            $input = json_decode(file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
            $actor = User::findOrFail((int) $this->option('user'));
            $result = $service->run($input, $actor, (bool) $this->option('apply'));
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            if (! $result['applied']) {
                $this->info('Validasi saja. Tidak ada data, berkas, status, atau putaran yang diubah.');
            }

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
