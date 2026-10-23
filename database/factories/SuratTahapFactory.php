<?php

namespace Database\Factories;

use App\Enums\TahapProses;
use App\Models\DjsnRecord;
use App\Models\SnpRecord;
use App\Models\SuratTahap;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SuratTahap>
 */
class SuratTahapFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kunci_asal' => fake()->uuid(),
            'tahap_proses' => TahapProses::TanggapanAwal,
            'nomor_putaran' => 0,
            'asal_pemetaan' => 'aplikasi',
            'status_pemetaan' => 'terpetakan',
        ];
    }

    public function forSurat(SnpRecord|DjsnRecord $surat): static
    {
        return $this->connection($surat->getConnectionName())->state(['record_id' => $surat->id]);
    }

    public function tindakLanjut(int $number): static
    {
        return $this->state(['tahap_proses' => TahapProses::TindakLanjut, 'nomor_putaran' => $number]);
    }
}
