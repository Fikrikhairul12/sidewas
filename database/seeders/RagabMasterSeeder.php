<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RagabMasterSeeder extends Seeder
{
    public function run(): void
    {
        $db = DB::connection('mysql_ragab');
        $now = now();

        $clusters = [
            ['id' => 1, 'nama_cluster' => 'Perencanaan Strategis dan Kinerja Badan'],
            ['id' => 2, 'nama_cluster' => 'Kepesertaan dan Komunikasi'],
            ['id' => 3, 'nama_cluster' => 'Tata Kelola Data dan Teknologi Informasi'],
            ['id' => 4, 'nama_cluster' => 'Regulasi dan Hukum'],
            ['id' => 5, 'nama_cluster' => 'Manajemen Risiko dan Aktuaria'],
            ['id' => 6, 'nama_cluster' => 'Kepatuhan dan Good Governance'],
            ['id' => 7, 'nama_cluster' => 'Kebijakan dan Operasional Layanan'],
            ['id' => 8, 'nama_cluster' => 'Organisasi dan SDM'],
            ['id' => 9, 'nama_cluster' => 'Audit dan Pengendalian Internal'],
            ['id' => 10, 'nama_cluster' => 'Akuntansi dan Keuangan'],
            ['id' => 11, 'nama_cluster' => 'Pengelolaan Dana dan Hasil Investasi'],
            ['id' => 12, 'nama_cluster' => 'Aset, SKP dan TJSL'],
        ];

        foreach ($clusters as $cluster) {
            $db->table('tb_cluster')->updateOrInsert(
                ['id' => $cluster['id']],
                [
                    'nama_cluster' => $cluster['nama_cluster'],
                    'keterangan' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $subClusters = [
            [1, 1, 'Perencanaan Strategis'],
            [2, 1, 'Pengelolaan Sistem Manajemen'],
            [3, 1, 'Kinerja Kantor Daerah'],
            [4, 1, 'Project Management'],
            [5, 2, 'Kepesertaan Penerima Upah'],
            [6, 2, 'Kepesertaan Bukan Penerima Upah'],
            [7, 2, 'Kepesertaan Jasa Konstruksi'],
            [8, 2, 'Kepesertaan Pekerja Migran Indonesia (PMI)'],
            [9, 2, 'Kepesertaan Penerima Bantuan Iuran (PBI)'],
            [10, 2, 'Penerimaan Iuran'],
            [11, 2, 'Pengawasan dan Pemeriksaan'],
            [12, 3, 'Manajemen dan Tata Kelola Data'],
            [13, 3, 'Pengembangan Teknologi Informasi'],
            [14, 3, 'Operasional Teknologi Informasi'],
            [15, 4, 'Advokasi Hukum'],
            [16, 4, 'Regulasi'],
            [17, 5, 'Manajemen Risiko'],
            [18, 5, 'Aktuaria'],
            [19, 6, 'Tata Kelola (Good Governance)'],
            [20, 6, 'Kepatuhan Internal (Compliance)'],
            [21, 7, 'Pengembangan Program'],
            [22, 7, 'Operasional Klaim dan Layanan'],
            [23, 7, 'Layanan Digital dan Contact Center'],
            [24, 7, 'Layanan Syariah'],
            [25, 8, 'Organization Development'],
            [26, 8, 'Pengelolaan SDM'],
            [27, 9, 'Standar dan Mutu Audit Internal'],
            [28, 9, 'Pengendalian Internal'],
            [29, 9, 'Tindak Lanjut Rekomendasi Audit Internal'],
            [30, 9, 'Tindak Lanjut Rekomendasi Pemeriksaan Eksternal'],
            [31, 10, 'Standar Akuntansi Keuangan'],
            [32, 10, 'Pengelolaan Keuangan'],
            [33, 10, 'Laporan Keuangan dan Laporan Pengelolaan Program'],
            [34, 10, 'RKAT'],
            [35, 11, 'Kebijakan Pengelolaan Investasi'],
            [36, 11, 'Manajemen Risiko Investasi'],
            [37, 12, 'Pengelolaan Aset Tetap'],
            [38, 12, 'Sarana Kesejahteraan Peserta (SKP)'],
            [39, 12, 'Tanggung Jawab Sosial Lingkungan (TJSL)'],
        ];

        foreach ($subClusters as [$id, $clusterId, $name]) {
            $db->table('tb_sub_cluster')->updateOrInsert(
                ['id' => $id],
                [
                    'cluster_id' => $clusterId,
                    'nama_sub_cluster' => $name,
                    'keterangan' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
