<?php

namespace Database\Factories;

use App\Models\DjsnRecord;
use App\Models\SnpRecord;
use App\Models\SuratRekonsiliasi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SuratRekonsiliasi>
 */
class SuratRekonsiliasiFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tabel_sumber' => 'tb_record',
            'sumber_id' => 1,
            'kode' => fake()->uuid(),
            'alasan' => 'Bukti historis perlu diperiksa.',
            'bukti' => ['jenis' => 'fixture_pengujian'],
            'status' => 'terbuka',
        ];
    }

    public function forSurat(SnpRecord|DjsnRecord $surat): static
    {
        return $this->connection($surat->getConnectionName())->state(['record_id' => $surat->id, 'sumber_id' => $surat->id]);
    }
}
