<?php

namespace App\Services\Kunjungan;

use App\Models\Kunjungan\Unit;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Throwable;

class UnitImportService
{
    /** @return array{created:int,updated:int,skipped:int} */
    public function import(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("File Excel tidak ditemukan: {$path}");
        }

        $sheet = IOFactory::load($path)->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);
        $headers = array_map(fn ($value) => strtoupper(trim((string) $value)), array_shift($rows) ?? []);
        $required = ['KODE_WIL', 'NAMA_KAPU_KANDA', 'KODE_UNIT', 'NAMA_UNIT_KERJA', 'PROVINSI', 'KAB_KOTA', 'KOORDINAT'];

        foreach ($required as $header) {
            if (! in_array($header, $headers, true)) {
                throw new RuntimeException("Header {$header} tidak ditemukan pada file Excel.");
            }
        }

        $indices = array_flip($headers);
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($rows as $row) {
            try {
                if (collect($row)->every(fn ($cell) => trim((string) $cell) === '')) {
                    continue;
                }
                $value = fn (string $key) => trim((string) ($row[$indices[$key]] ?? ''));
                $kodeUnit = $value('KODE_UNIT');
                $namaUnit = $value('NAMA_UNIT_KERJA');
                $kodeWil = $value('KODE_WIL');
                if ($kodeUnit === '' || $namaUnit === '' || $kodeWil === '') {
                    $result['skipped']++;

                    continue;
                }

                [$latitude, $longitude] = $this->parseCoordinates($value('KOORDINAT'));
                $exists = Unit::query()->where('kode_unit', $kodeUnit)->exists();
                Unit::query()->updateOrCreate(['kode_unit' => $kodeUnit], [
                    'kode_wil' => $kodeWil,
                    'nama_kapu_kanda' => $value('NAMA_KAPU_KANDA') ?: null,
                    'nama_unit_kerja' => $namaUnit,
                    'provinsi' => $value('PROVINSI') ?: null,
                    'kab_kota' => $value('KAB_KOTA') ?: null,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ]);
                $result[$exists ? 'updated' : 'created']++;
            } catch (Throwable) {
                $result['skipped']++;
            }
        }

        return $result;
    }

    /** @return array{0:?float,1:?float} */
    private function parseCoordinates(string $coordinates): array
    {
        if ($coordinates === '') {
            return [null, null];
        }

        preg_match_all('/-?\d+(?:\.\d+)?/', str_replace(',', ' ', $coordinates), $matches);
        if (count($matches[0]) < 2) {
            return [null, null];
        }

        $latitude = (float) $matches[0][0];
        $longitude = (float) $matches[0][1];

        return abs($latitude) <= 90 && abs($longitude) <= 180
            ? [$latitude, $longitude]
            : [null, null];
    }
}
