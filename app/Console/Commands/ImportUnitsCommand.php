<?php

namespace App\Console\Commands;

use App\Services\Kunjungan\UnitImportService;
use Illuminate\Console\Command;
use Throwable;

class ImportUnitsCommand extends Command
{
    protected $signature = 'units:import {path? : Path file XLSX, relatif dari root aplikasi atau absolut}';

    protected $description = 'Impor master unit kerja BPJS Ketenagakerjaan dari XLSX';

    public function handle(UnitImportService $service): int
    {
        $input = $this->argument('path') ?: 'public/Koordinat Unit Kerja BPJS Ketenagakerjaan se-Indonesia.xlsx';
        $path = str_contains($input, ':') || str_starts_with($input, DIRECTORY_SEPARATOR) ? $input : base_path($input);

        try {
            $result = $service->import($path);
            $this->components->info('Import unit kerja selesai.');
            $this->table(['Berhasil', 'Diperbarui', 'Invalid/dilewati'], [[
                $result['created'], $result['updated'], $result['skipped'],
            ]]);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
