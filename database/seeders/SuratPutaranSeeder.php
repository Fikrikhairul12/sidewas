<?php

namespace Database\Seeders;

use App\Services\SuratPutaranBackfill;
use Illuminate\Database\Seeder;

class SuratPutaranSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['mysql_snp', 'mysql_djsn'] as $connection) {
            (new SuratPutaranBackfill)->run($connection);
        }
    }
}
