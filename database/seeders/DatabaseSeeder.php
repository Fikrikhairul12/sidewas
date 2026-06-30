<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SnpMasterSeeder::class,
            AccessControlSeeder::class,
            PicMasterSeeder::class,
            RagabMasterSeeder::class,
            RawasMasterSeeder::class,
            DjsnMasterSeeder::class,
            EksternalMasterSeeder::class,
            ProdukHukumJenisPeraturanSeeder::class,
        ]);
    }
}
