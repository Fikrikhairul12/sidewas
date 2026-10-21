<?php

namespace Database\Factories;

use App\Models\SuratPutaran;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SuratPutaran>
 */
class SuratPutaranFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nomor_putaran' => 1,
            'opened_at' => now(),
            'archived_at' => null,
            'created_by' => null,
        ];
    }

    public function legacy(): static
    {
        return $this->state(['nomor_putaran' => 0]);
    }
}
