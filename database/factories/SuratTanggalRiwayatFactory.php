<?php

namespace Database\Factories;

use App\Models\SuratTahap;
use App\Models\SuratTanggalRiwayat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SuratTanggalRiwayat>
 */
class SuratTanggalRiwayatFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kunci' => fake()->uuid(), 'jenis' => 'rekonsiliasi', 'alasan' => fake()->sentence(),
            'diputuskan_at' => now(), 'bukti' => ['jenis' => 'fixture_pengujian'],
        ];
    }

    public function forTahap(SuratTahap $stage): static
    {
        return $this->connection($stage->getConnectionName())->state(['record_id' => $stage->record_id, 'tahap_id' => $stage->id]);
    }
}
