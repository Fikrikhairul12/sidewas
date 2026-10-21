<?php

namespace Database\Seeders;

use App\Services\SuratPutaranBackfill;
use Illuminate\Database\Seeder;

class DjsnKompilasiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        (new SuratPutaranBackfill)->run('mysql_djsn');
    }
}
