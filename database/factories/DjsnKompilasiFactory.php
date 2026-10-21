<?php

namespace Database\Factories;

use App\Models\DjsnButir;
use App\Models\DjsnKompilasi;
use App\Models\SuratPutaran;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DjsnKompilasi>
 */
class DjsnKompilasiFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tahap_kompilasi' => 'tanggapan',
            'hasil_kompilasi' => '<p>'.fake()->sentence().'</p>',
            'status' => 'belum_dikompilasi',
        ];
    }

    public function forButir(DjsnButir $butir): static
    {
        return $this->state(['id_butir_djsn' => $butir->id_butir_djsn]);
    }

    public function forPutaran(SuratPutaran $putaran): static
    {
        return $this->connection($putaran->getConnectionName())->state(['putaran_id' => $putaran->id]);
    }
}
