<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProdukHukumJenisPeraturanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jenisPeraturan = [
            ['nama' => 'Undang-undang', 'singkatan' => 'UU'],
            ['nama' => 'Peraturan Pemerintah', 'singkatan' => 'PP'],
            ['nama' => 'Peraturan Presiden', 'singkatan' => 'Perpres'],
            ['nama' => 'Keputusan Presiden', 'singkatan' => 'Keppres'],
            ['nama' => 'Instruksi Presiden', 'singkatan' => 'Inpres'],
            ['nama' => 'Peraturan Menteri', 'singkatan' => 'Permen'],
            ['nama' => 'Perjanjian Kerjasama', 'singkatan' => 'PKS'],
            ['nama' => 'Nota Kesepahaman/MOU', 'singkatan' => null],
            ['nama' => 'Peraturan BPJS Ketenagakerjaan', 'singkatan' => null],
            ['nama' => 'Peraturan Direksi', 'singkatan' => 'Perdir'],
            ['nama' => 'Peraturan Otoritas Jasa Keuangan', 'singkatan' => null],
            ['nama' => 'Keputusan Direksi', 'singkatan' => 'Kepdir'],
            ['nama' => 'Peraturan Dewan Pengawas', 'singkatan' => 'Perdewas'],
            ['nama' => 'Keputusan Dewan Pengawas', 'singkatan' => 'Kepdewas'],
            ['nama' => 'Surat Edaran', 'singkatan' => 'SE'],
        ];

        foreach ($jenisPeraturan as $index => $jenis) {
            DB::connection('mysql_produk_hukum')
                ->table('tb_jenis_peraturan')
                ->updateOrInsert(
                    ['nama' => $jenis['nama']],
                    [
                        'singkatan' => $jenis['singkatan'],
                        'urutan' => $index + 1,
                        'is_active' => true,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
        }
    }
}
