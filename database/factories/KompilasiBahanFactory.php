<?php

namespace Database\Factories;

use App\Models\KompilasiBahan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<KompilasiBahan>
 */
class KompilasiBahanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'salinan' => ['isi' => fake()->sentence()],
            'bukti' => ['jenis' => 'fixture_pengujian'],
        ];
    }

    public function forSumber(Model $kompilasi, Model $sumber): static
    {
        $module = $kompilasi->getConnectionName() === 'mysql_snp' ? 'snp' : 'djsn';

        return $this->connection($kompilasi->getConnectionName())->state([
            'kompilasi_id' => $kompilasi->id, 'tahap_id' => $kompilasi->tahap_id,
            'jenis_sumber' => $sumber->getTable(), 'sumber_id' => $sumber->id,
            'butir_key' => $sumber->getAttribute('id_butir_'.$module),
            'unit_kerja_id' => $sumber->getAttribute('unit_kerja_id'),
            'pengisi_id' => $sumber->getAttribute('created_by'),
            'sumber_created_at' => $sumber->getAttribute('created_at'),
            'sumber_updated_at' => $sumber->getAttribute('updated_at'),
        ]);
    }
}
