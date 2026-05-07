<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PicMasterSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $direktorats = [
            [1, 'Direktorat Utama', null],
            [2, 'Direktorat Kepesertaan', null],
            [3, 'Direktorat Pelayanan', null],
            [4, 'Direktorat Pengembangan Investasi', null],
            [5, 'Direktorat Perencanaan Strategis dan TI', null],
            [6, 'Direktorat Keuangan dan Manajemen Risiko', null],
            [7, 'Direktorat Human Capital dan Umum', null],
            [8, 'Dewan Pengawas', null],
        ];

        foreach ($direktorats as [$id, $nama, $kode]) {
            DB::table('tb_direktorat')->updateOrInsert(
                ['id' => $id],
                [
                    'nama_direktorat' => $nama,
                    'kode_direktorat' => $kode,
                    'keterangan' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $units = [
            [1, 1, 'Deputi Bidang Sekretariat Badan', 'SBD'],
            [2, 1, 'Deputi Bidang Komunikasi', 'KOM'],
            [3, 1, 'Deputi Bidang Kepatuhan dan Hukum', 'KHK'],
            [4, 1, 'Satuan Pengawas Internal', 'SPI'],
            [5, 2, 'Deputi Bidang Kepesertaan Korporasi dan Institusi', 'KSI'],
            [6, 2, 'Deputi Bidang Kepesertaan Program Khusus dan Keagenan', 'KSA'],
            [7, 2, 'Deputi Bidang Pengawasan dan Pemeriksaan', 'WRK'],
            [8, 3, 'Deputi Bidang Kebijakan Pelayanan Program', 'KLP'],
            [9, 3, 'Deputi Bidang Operasional dan Kanal Layanan', 'OKL'],
            [10, 3, 'Deputi Bidang Layanan Digital dan Customer Care', 'LDC'],
            [11, 4, 'Deputi Bidang Analisis Portofolio', 'APF'],
            [12, 4, 'Deputi Bidang Pendapatan Tetap dan Pasar Modal', 'PTM'],
            [13, 4, 'Deputi Bidang Investasi Langsung', 'INL'],
            [14, 5, 'Deputi Bidang Perencanaan Strategis dan Transformasi', 'REN'],
            [15, 5, 'Deputi Bidang Aktuaria dan Riset Jaminan Sosial', 'AKR'],
            [16, 5, 'Deputi Bidang Manajemen Data dan Analitik', 'MDT'],
            [17, 5, 'Deputi Bidang Arsitektur dan Pengembangan TI', 'RPT'],
            [18, 5, 'Deputi Bidang Infrastruktur dan Operasional TI', 'IPT'],
            [19, 6, 'Deputi Bidang Akuntansi', 'AKT'],
            [20, 6, 'Deputi Bidang Keuangan', 'KEU'],
            [21, 6, 'Deputi Bidang Manajemen Risiko', 'MRK'],
            [22, 7, 'Deputi Bidang Human Capital', 'HCP'],
            [23, 7, 'Deputi Bidang Learning and Development', 'LND'],
            [24, 7, 'Deputi Bidang Aset dan Sarana Prasarana', 'ASP'],
            [25, 7, 'Deputi Bidang Pengadaan', 'PDN'],
            [26, 8, 'Sekretariat Dewan Pengawas', 'SDW'],
        ];

        foreach ($units as [$id, $direktoratId, $namaUnit, $kodeUnit]) {
            DB::table('tb_unit_kerja')->updateOrInsert(
                ['id' => $id],
                [
                    'direktorat_id' => $direktoratId,
                    'nama_unit' => $namaUnit,
                    'kode_unit' => $kodeUnit,
                    'keterangan' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $komites = [
            [1, 'Komite Pengawasan Kinerja Badan', 'KPKB'],
            [2, 'Komite Pengawasan Manajemen Risiko', 'KPMR'],
            [3, 'Komite Audit, Anggaran dan Investasi', 'KAAI'],
        ];

        foreach ($komites as [$id, $nama, $kode]) {
            DB::table('tb_komite')->updateOrInsert(
                ['id' => $id],
                [
                    'nama_komite' => $nama,
                    'kode_komite' => $kode,
                    'keterangan' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
