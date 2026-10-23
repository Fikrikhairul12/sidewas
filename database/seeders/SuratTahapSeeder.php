<?php

namespace Database\Seeders;

use App\Services\SuratTahapBackfill;
use Illuminate\Database\Seeder;

class SuratTahapSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['mysql_snp', 'mysql_djsn'] as $connection) {
            (new SuratTahapBackfill)->run($connection);
        }
    }
}
