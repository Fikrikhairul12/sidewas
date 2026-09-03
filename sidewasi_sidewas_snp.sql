-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Sep 03, 2026 at 10:09 PM
-- Server version: 8.0.46-cll-lve
-- PHP Version: 8.4.24

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sidewasi_sidewas_snp`
--

-- --------------------------------------------------------

--
-- Table structure for table `tb_butir_pic`
--

CREATE TABLE `tb_butir_pic` (
  `id` bigint UNSIGNED NOT NULL,
  `id_butir_snp` varchar(70) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit_kerja_id` bigint UNSIGNED DEFAULT NULL,
  `komite_id` bigint UNSIGNED DEFAULT NULL,
  `jenis_pic` enum('utama','pendukung','komite') NOT NULL,
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `updated_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `tb_butir_pic`
--

INSERT INTO `tb_butir_pic` (`id`, `id_butir_snp`, `unit_kerja_id`, `komite_id`, `jenis_pic`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 'B/169/062026-SNP.01', 9, NULL, 'utama', 2, 2, '2026-07-09 08:22:10', '2026-07-09 08:22:10'),
(2, 'B/169/062026-SNP.01', NULL, 3, 'komite', 2, 2, '2026-07-09 08:22:10', '2026-07-09 08:22:10'),
(3, 'B/169/062026-SNP.02', 9, NULL, 'utama', 2, 2, '2026-07-09 08:23:14', '2026-07-09 08:23:14'),
(4, 'B/169/062026-SNP.02', NULL, 3, 'komite', 2, 2, '2026-07-09 08:23:14', '2026-07-09 08:23:14'),
(5, 'B/169/062026-SNP.03', 9, NULL, 'utama', 2, 2, '2026-07-09 08:24:13', '2026-07-09 08:24:13'),
(6, 'B/169/062026-SNP.03', NULL, 3, 'komite', 2, 2, '2026-07-09 08:24:13', '2026-07-09 08:24:13'),
(7, 'B/169/062026-SNP.04', 9, NULL, 'utama', 2, 2, '2026-07-09 08:25:16', '2026-07-09 08:25:16'),
(8, 'B/169/062026-SNP.04', NULL, 3, 'komite', 2, 2, '2026-07-09 08:25:16', '2026-07-09 08:25:16'),
(9, 'B/169/062026-SNP.05', 9, NULL, 'utama', 2, 2, '2026-07-09 08:28:07', '2026-07-09 08:28:07'),
(10, 'B/169/062026-SNP.05', 8, NULL, 'pendukung', 2, 2, '2026-07-09 08:28:07', '2026-07-09 08:28:07'),
(11, 'B/169/062026-SNP.05', 20, NULL, 'pendukung', 2, 2, '2026-07-09 08:28:07', '2026-07-09 08:28:07'),
(12, 'B/169/062026-SNP.05', 19, NULL, 'pendukung', 2, 2, '2026-07-09 08:28:07', '2026-07-09 08:28:07'),
(13, 'B/169/062026-SNP.05', NULL, 3, 'komite', 2, 2, '2026-07-09 08:28:07', '2026-07-09 08:28:07'),
(14, 'B/170/062026-SNP.01', 9, NULL, 'utama', 2, 2, '2026-07-09 08:32:32', '2026-07-09 08:32:32'),
(15, 'B/170/062026-SNP.01', NULL, 3, 'komite', 2, 2, '2026-07-09 08:32:32', '2026-07-09 08:32:32'),
(16, 'B/170/062026-SNP.02', 9, NULL, 'utama', 2, 2, '2026-07-09 08:33:21', '2026-07-09 08:33:21'),
(17, 'B/170/062026-SNP.02', NULL, 3, 'komite', 2, 2, '2026-07-09 08:33:21', '2026-07-09 08:33:21'),
(18, 'B/170/062026-SNP.03', 9, NULL, 'utama', 2, 2, '2026-07-09 08:34:13', '2026-07-09 08:34:13'),
(19, 'B/170/062026-SNP.03', NULL, 3, 'komite', 2, 2, '2026-07-09 08:34:13', '2026-07-09 08:34:13'),
(20, 'B/171/062026-SNP.01', 14, NULL, 'utama', 2, 2, '2026-07-09 08:38:08', '2026-07-09 08:38:08'),
(21, 'B/171/062026-SNP.01', 20, NULL, 'pendukung', 2, 2, '2026-07-09 08:38:08', '2026-07-09 08:38:08'),
(22, 'B/171/062026-SNP.01', NULL, 3, 'komite', 2, 2, '2026-07-09 08:38:08', '2026-07-09 08:38:08'),
(23, 'B/171/062026-SNP.02', 14, NULL, 'utama', 2, 2, '2026-07-09 08:39:31', '2026-07-09 08:39:31'),
(24, 'B/171/062026-SNP.02', NULL, 3, 'komite', 2, 2, '2026-07-09 08:39:31', '2026-07-09 08:39:31'),
(25, 'B/171/062026-SNP.03', 14, NULL, 'utama', 2, 2, '2026-07-09 08:41:07', '2026-07-09 08:41:07'),
(26, 'B/171/062026-SNP.03', NULL, 3, 'komite', 2, 2, '2026-07-09 08:41:07', '2026-07-09 08:41:07'),
(29, 'B/171/062026-SNP.05', 14, NULL, 'utama', 2, 2, '2026-07-09 08:42:47', '2026-07-09 08:42:47'),
(30, 'B/171/062026-SNP.05', NULL, 3, 'komite', 2, 2, '2026-07-09 08:42:47', '2026-07-09 08:42:47'),
(31, 'B/171/062026-SNP.04', 14, NULL, 'utama', 1, 1, '2026-08-21 03:33:31', '2026-08-21 03:33:31'),
(32, 'B/171/062026-SNP.04', 20, NULL, 'pendukung', 1, 1, '2026-08-21 03:33:31', '2026-08-21 03:33:31'),
(33, 'B/171/062026-SNP.04', NULL, 3, 'komite', 1, 1, '2026-08-21 03:33:31', '2026-08-21 03:33:31'),
(34, 'B/10/012026-SNP.01', 11, NULL, 'utama', 1, 1, '2026-08-21 04:18:33', '2026-08-21 04:18:33'),
(35, 'B/10/012026-SNP.01', 17, NULL, 'pendukung', 1, 1, '2026-08-21 04:18:33', '2026-08-21 04:18:33'),
(36, 'B/10/012026-SNP.01', 25, NULL, 'pendukung', 1, 1, '2026-08-21 04:18:33', '2026-08-21 04:18:33'),
(37, 'B/10/012026-SNP.01', NULL, 3, 'komite', 1, 1, '2026-08-21 04:18:33', '2026-08-21 04:18:33'),
(38, 'B/10/012026-SNP.02', 11, NULL, 'utama', 1, 1, '2026-08-21 04:19:22', '2026-08-21 04:19:22'),
(39, 'B/10/012026-SNP.02', 4, NULL, 'pendukung', 1, 1, '2026-08-21 04:19:22', '2026-08-21 04:19:22'),
(40, 'B/10/012026-SNP.02', NULL, 3, 'komite', 1, 1, '2026-08-21 04:19:22', '2026-08-21 04:19:22'),
(41, 'B/10/012026-SNP.03', 11, NULL, 'utama', 1, 1, '2026-08-21 04:20:02', '2026-08-21 04:20:02'),
(42, 'B/10/012026-SNP.03', 4, NULL, 'pendukung', 1, 1, '2026-08-21 04:20:02', '2026-08-21 04:20:02'),
(43, 'B/10/012026-SNP.03', NULL, 3, 'komite', 1, 1, '2026-08-21 04:20:02', '2026-08-21 04:20:02'),
(44, 'B/10/012026-SNP.04', 11, NULL, 'utama', 1, 1, '2026-08-21 04:20:56', '2026-08-21 04:20:56'),
(45, 'B/10/012026-SNP.04', 17, NULL, 'pendukung', 1, 1, '2026-08-21 04:20:56', '2026-08-21 04:20:56'),
(46, 'B/10/012026-SNP.04', NULL, 3, 'komite', 1, 1, '2026-08-21 04:20:56', '2026-08-21 04:20:56'),
(47, 'B/14/012026-SNP.01', 8, NULL, 'utama', 1, 1, '2026-08-21 04:25:21', '2026-08-21 04:25:21'),
(48, 'B/14/012026-SNP.01', 3, NULL, 'pendukung', 1, 1, '2026-08-21 04:25:21', '2026-08-21 04:25:21'),
(49, 'B/14/012026-SNP.01', 4, NULL, 'pendukung', 1, 1, '2026-08-21 04:25:21', '2026-08-21 04:25:21'),
(50, 'B/14/012026-SNP.01', 9, NULL, 'pendukung', 1, 1, '2026-08-21 04:25:21', '2026-08-21 04:25:21'),
(51, 'B/14/012026-SNP.01', 10, NULL, 'pendukung', 1, 1, '2026-08-21 04:25:21', '2026-08-21 04:25:21'),
(52, 'B/14/012026-SNP.01', NULL, 2, 'komite', 1, 1, '2026-08-21 04:25:21', '2026-08-21 04:25:21'),
(53, 'B/14/012026-SNP.02', 8, NULL, 'utama', 1, 1, '2026-08-21 04:25:50', '2026-08-21 04:25:50'),
(54, 'B/14/012026-SNP.02', 4, NULL, 'pendukung', 1, 1, '2026-08-21 04:25:50', '2026-08-21 04:25:50'),
(55, 'B/14/012026-SNP.02', NULL, 2, 'komite', 1, 1, '2026-08-21 04:25:50', '2026-08-21 04:25:50'),
(56, 'B/14/012026-SNP.03', 8, NULL, 'utama', 1, 1, '2026-08-21 04:26:31', '2026-08-21 04:26:31'),
(57, 'B/14/012026-SNP.03', 3, NULL, 'pendukung', 1, 1, '2026-08-21 04:26:31', '2026-08-21 04:26:31'),
(58, 'B/14/012026-SNP.03', NULL, 2, 'komite', 1, 1, '2026-08-21 04:26:31', '2026-08-21 04:26:31'),
(59, 'B/14/012026-SNP.04', 8, NULL, 'utama', 1, 1, '2026-08-21 04:27:07', '2026-08-21 04:27:07'),
(60, 'B/14/012026-SNP.04', 21, NULL, 'pendukung', 1, 1, '2026-08-21 04:27:07', '2026-08-21 04:27:07'),
(61, 'B/14/012026-SNP.04', NULL, 2, 'komite', 1, 1, '2026-08-21 04:27:07', '2026-08-21 04:27:07'),
(62, 'B/14/012026-SNP.05', 8, NULL, 'utama', 1, 1, '2026-08-21 04:27:56', '2026-08-21 04:27:56'),
(63, 'B/14/012026-SNP.05', 9, NULL, 'pendukung', 1, 1, '2026-08-21 04:27:56', '2026-08-21 04:27:56'),
(64, 'B/14/012026-SNP.05', 10, NULL, 'pendukung', 1, 1, '2026-08-21 04:27:56', '2026-08-21 04:27:56'),
(65, 'B/14/012026-SNP.05', 17, NULL, 'pendukung', 1, 1, '2026-08-21 04:27:56', '2026-08-21 04:27:56'),
(66, 'B/14/012026-SNP.05', NULL, 2, 'komite', 1, 1, '2026-08-21 04:27:56', '2026-08-21 04:27:56'),
(67, 'B/34/022026-SNP.01', 15, NULL, 'utama', 1, 1, '2026-08-21 04:28:53', '2026-08-21 04:28:53'),
(68, 'B/34/022026-SNP.01', 17, NULL, 'pendukung', 1, 1, '2026-08-21 04:28:53', '2026-08-21 04:28:53'),
(69, 'B/34/022026-SNP.01', NULL, 2, 'komite', 1, 1, '2026-08-21 04:28:53', '2026-08-21 04:28:53'),
(70, 'B/34/022026-SNP.02', 15, NULL, 'utama', 1, 1, '2026-08-21 04:29:35', '2026-08-21 04:29:35'),
(71, 'B/34/022026-SNP.02', 17, NULL, 'pendukung', 1, 1, '2026-08-21 04:29:35', '2026-08-21 04:29:35'),
(72, 'B/34/022026-SNP.02', 14, NULL, 'pendukung', 1, 1, '2026-08-21 04:29:35', '2026-08-21 04:29:35'),
(73, 'B/34/022026-SNP.02', NULL, 2, 'komite', 1, 1, '2026-08-21 04:29:35', '2026-08-21 04:29:35');

-- --------------------------------------------------------

--
-- Table structure for table `tb_butir_snp`
--

CREATE TABLE `tb_butir_snp` (
  `id` bigint UNSIGNED NOT NULL,
  `id_butir_snp` varchar(70) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_snp` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `butir_snp` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('terbit','dalam_proses','diusulkan_tuntas','selesai_tuntas') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'terbit',
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `updated_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tb_butir_snp`
--

INSERT INTO `tb_butir_snp` (`id`, `id_butir_snp`, `id_snp`, `butir_snp`, `status`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 'B/169/062026-SNP.01', 'B/169/062026-SNP', 'Melakukan percepatan penyelesaian klaim pending JKK-PLKK secara tuntas sesuai ketentuan paling lambat 31 Desember 2026', 'terbit', 2, 2, '2026-07-09 08:22:10', '2026-07-09 08:22:10'),
(2, 'B/169/062026-SNP.02', 'B/169/062026-SNP', 'Melakukan kajian atas akar masalah dan alternatif solusi terjadinya klaim pending JKK-PLKK di tingkat Kantor Cabang agar tidak terjadi secara berulang', 'terbit', 2, 2, '2026-07-09 08:23:14', '2026-07-09 08:23:14'),
(3, 'B/169/062026-SNP.03', 'B/169/062026-SNP', 'Mengembangkan dashboard pemantauan status klaim JKK-PLKK secara online dan real-time sebagai salah satu alat pengendalian internal operasional layanan', 'terbit', 2, 2, '2026-07-09 08:24:13', '2026-07-09 08:24:13'),
(4, 'B/169/062026-SNP.04', 'B/169/062026-SNP', 'Menyusun tata kelola/petunjuk teknis pemanfaatan dashboard klaim pending JKK-PLKK, antara lain meliputi langkah penanganan dan pihak yang bertanggungjawab', 'terbit', 2, 2, '2026-07-09 08:25:16', '2026-07-09 08:25:16'),
(5, 'B/169/062026-SNP.05', 'B/169/062026-SNP', 'Melakukan percepatan penyelesaian atas sisa tindak lanjut SNP Dewan Pengawas yang disampaikan sebelumnya melalui surat nomor: 773/DP/112023', 'terbit', 2, 2, '2026-07-09 08:28:07', '2026-07-09 08:28:07'),
(6, 'B/170/062026-SNP.01', 'B/170/062026-SNP', 'Melakukan evaluasi dan penyempurnaan proses bisnis penyelesaian klaim, antara lain:\r\n1). Mengedepankan optimalisasi penggunaan IT dan AI proses verifikasi klaim, baik profil peserta maupun bukti-bukti pendukung.\r\n2). Meningkatkan pengendalian dengan menambahkan IT Control.', 'terbit', 2, 2, '2026-07-09 08:32:32', '2026-07-09 08:32:32'),
(7, 'B/170/062026-SNP.02', 'B/170/062026-SNP', 'Melakukan kalibrasi atas workload analysis dan kecukupan SDM untuk memastikan kesesuaian jumlah SDM dan beban kerja.', 'terbit', 2, 2, '2026-07-09 08:33:21', '2026-07-09 08:33:21'),
(8, 'B/170/062026-SNP.03', 'B/170/062026-SNP', 'Dalam hal diperlukan, melakukan penataan organisasi sesuai dengan hasil evaluasi atas proses bisnis dan perhitungan workload analysis', 'terbit', 2, 2, '2026-07-09 08:34:13', '2026-07-09 08:34:13'),
(9, 'B/171/062026-SNP.01', 'B/171/062026-SNP', 'Melakukan evaluasi atas implementasi RKAT 2026 meliputi:\r\n1). Realisasi anggaran.\r\n2). Realisasi program kerja dan kegiatan.\r\n3). Capaian output program kerja dan kegiatan, dan,\r\n4). Keterkaitan antara realisasi anggaran, program kerja dan kegiatan dengan pencapaian ICK Badan.', 'terbit', 2, 2, '2026-07-09 08:38:08', '2026-07-09 08:38:08'),
(10, 'B/171/062026-SNP.02', 'B/171/062026-SNP', 'Melakukan kajian terhadap RKAT 2026 untuk meningkatkan sinkronisasi, harmonisasi, dan simplifikasi program kerja selaras dengan fokus Direksi pada area coverage, care dan credibility.', 'terbit', 2, 2, '2026-07-09 08:39:31', '2026-07-09 08:39:31'),
(11, 'B/171/062026-SNP.03', 'B/171/062026-SNP', 'Pilar Strategis Coverage, Care dan Credibility dijabarkan secara lebih rinci ke dalam program kerja dan kegiatan pada RKAT, termasuk program kerja dan kegiatan yang menjadi perhatian para pemangku kepentingan eksternal, seperti kegiatan promotif dan preventif.', 'terbit', 2, 2, '2026-07-09 08:41:07', '2026-07-09 08:41:07'),
(12, 'B/171/062026-SNP.04', 'B/171/062026-SNP', 'Menggunakan hasil evaluasi dan kajian sebagaimana pada butir 1 dan 2 di atas, sebagai dasar dalam penyusunan RKAT Tahun 2027', 'terbit', 2, 1, '2026-07-09 08:42:05', '2026-08-21 03:33:31'),
(13, 'B/171/062026-SNP.05', 'B/171/062026-SNP', 'Menyampaikan hasil evaluasi dan hasil kajian sebagaimana pada butir 1 dan butir 2 kepada Dewan Pengawas', 'terbit', 2, 2, '2026-07-09 08:42:47', '2026-07-09 08:42:47'),
(14, 'B/10/012026-SNP.01', 'B/10/012026-SNP', 'Menetapkan standar Go-live Smart Investment System yang dilengkapi checklist baku dan terverifikasi, mencakup aspek fungsional, kualitas, keamanan, serta kesiapan unit pengguna.', 'terbit', 1, 1, '2026-08-21 04:18:33', '2026-08-21 04:18:33'),
(15, 'B/10/012026-SNP.02', 'B/10/012026-SNP', 'Meminta Satuan Pengawas Internal (SPI) untuk melakukan audit atas pekerjaan Smart Investment System guna menilai kecukupan tata kelola, efektivitas pemanfaatan biaya, serta potensi risiko penyimpangan.', 'terbit', 1, 1, '2026-08-21 04:19:22', '2026-08-21 04:19:22'),
(16, 'B/10/012026-SNP.03', 'B/10/012026-SNP', 'Menindaklanjuti seluruh rekomendasi BPK terkait pengadaan Smart Investment System sampai tuntas sesuai perjanjian kerja sama.', 'terbit', 1, 1, '2026-08-21 04:20:02', '2026-08-21 04:20:02'),
(17, 'B/10/012026-SNP.04', 'B/10/012026-SNP', 'Melakukan langkah korektif terstruktur, meliputi:\r\n1) Penguatan tata kelola sistem melalui penetapan PIC lintas fungsi;\r\n2) Peningkatan kapasitas SDM, termasuk penguatan peran Quantitative Analyst\r\n3) Penguatan keamanan sistem dan kepatuhan terhadap standar TI;\r\n4) Penyelesaian seluruh fungsi kritikal sistem secara terukur dan terdokumentasi.', 'terbit', 1, 1, '2026-08-21 04:20:56', '2026-08-21 04:20:56'),
(18, 'B/14/012026-SNP.01', 'B/14/012026-SNP', 'Mempercepat penyelesaian tindak lanjut SNP Dewan Pengawas sebelumnya terkait:\r\na. Efektivitas Penanganan Fraud Karyawan BPJS Ketenagakerjaan\r\nb. Penyempurnaan proses bisnis dalam penyelenggaraan Program Jaminan Kecelakaan Kerja (JKK).', 'terbit', 1, 1, '2026-08-21 04:25:21', '2026-08-21 04:25:21'),
(19, 'B/14/012026-SNP.02', 'B/14/012026-SNP', 'Melakukan Audit untuk memastikan agar kasus serupa tidak terjadi di kantor wilayah lain.', 'terbit', 1, 1, '2026-08-21 04:25:50', '2026-08-21 04:25:50'),
(20, 'B/14/012026-SNP.03', 'B/14/012026-SNP', 'Memastikan proses hukum oleh Aparat Penegak Hukum (APH) kepada para pelaku fraud secara tuntas untuk memberikan efek jera bagi seluruh insan BPJS Ketenagakerjaan agar tidak melakukan tindakan fraud di kemudian hari.', 'terbit', 1, 1, '2026-08-21 04:26:31', '2026-08-21 04:26:31'),
(21, 'B/14/012026-SNP.04', 'B/14/012026-SNP', 'Segera mengimplementasikan Fraud Detection System (FDS) sebagai early warning system untuk memantau pola klaim yang tidak wajar.', 'terbit', 1, 1, '2026-08-21 04:27:07', '2026-08-21 04:27:07'),
(22, 'B/14/012026-SNP.05', 'B/14/012026-SNP', 'Menyempurnakan perbaikan proses bisnis untuk penguatan pengendalian internal pada proses klaim.', 'terbit', 1, 1, '2026-08-21 04:27:56', '2026-08-21 04:27:56'),
(23, 'B/34/022026-SNP.01', 'B/34/022026-SNP', 'Melakukan pengembangan sistem informasi dalam melakukan perhitungan cadangan teknis aktuaria', 'terbit', 1, 1, '2026-08-21 04:28:53', '2026-08-21 04:28:53'),
(24, 'B/34/022026-SNP.02', 'B/34/022026-SNP', 'Melakukan penyelarasan program kerja/kegiatan pengembangan sistem informasi dalam melakukan perhitungan cadangan teknis aktuaria dengan perencanaan strategis pengembangan IT', 'terbit', 1, 1, '2026-08-21 04:29:35', '2026-08-21 04:29:35');

-- --------------------------------------------------------

--
-- Table structure for table `tb_cluster`
--

CREATE TABLE `tb_cluster` (
  `id` bigint UNSIGNED NOT NULL,
  `nama_cluster` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tb_cluster`
--

INSERT INTO `tb_cluster` (`id`, `nama_cluster`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, 'Perencanaan Strategis dan Kinerja Badan', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(2, 'Kepesertaan dan Komunikasi', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(3, 'Tata Kelola Data dan Teknologi Informasi', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(4, 'Regulasi dan Hukum', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(5, 'Manajemen Risiko dan Aktuaria', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(6, 'Kepatuhan dan Good Governance', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(7, 'Kebijakan dan Operasional Layanan', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(8, 'Organisasi dan SDM', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(9, 'Audit dan Pengendalian Internal', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(10, 'Akuntansi dan Keuangan', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(11, 'Pengelolaan Dana dan Hasil Investasi', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(12, 'Aset, SKP dan TJSL', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50');

-- --------------------------------------------------------

--
-- Table structure for table `tb_kompilasi`
--

CREATE TABLE `tb_kompilasi` (
  `id` bigint UNSIGNED NOT NULL,
  `id_butir_snp` varchar(70) COLLATE utf8mb4_unicode_ci NOT NULL,
  `putaran_tl` int UNSIGNED NOT NULL DEFAULT '1',
  `tahap_kompilasi` enum('tanggapan','tindak_lanjut') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'tanggapan',
  `hasil_kompilasi` text COLLATE utf8mb4_unicode_ci,
  `deliverables` text COLLATE utf8mb4_unicode_ci,
  `dokumen` text COLLATE utf8mb4_unicode_ci,
  `ubah_tgl` date DEFAULT NULL,
  `status_pengajuan_tgl` enum('pending','disetujui','ditolak') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('belum_dikompilasi','dalam_proses_reviu_dewas') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'belum_dikompilasi',
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `updated_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tb_kompilasi`
--

INSERT INTO `tb_kompilasi` (`id`, `id_butir_snp`, `putaran_tl`, `tahap_kompilasi`, `hasil_kompilasi`, `deliverables`, `dokumen`, `ubah_tgl`, `status_pengajuan_tgl`, `status`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(2, 'B/34/022026-SNP.02', 1, 'tanggapan', NULL, NULL, NULL, NULL, NULL, 'belum_dikompilasi', 1, 1, '2026-09-03 10:34:12', '2026-09-03 10:34:12');

-- --------------------------------------------------------

--
-- Table structure for table `tb_record`
--

CREATE TABLE `tb_record` (
  `id` bigint UNSIGNED NOT NULL,
  `id_snp` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cluster_id` bigint UNSIGNED DEFAULT NULL,
  `sub_cluster_id` bigint UNSIGNED DEFAULT NULL,
  `nomor_surat` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tanggal_surat` date DEFAULT NULL,
  `perihal_surat` text COLLATE utf8mb4_unicode_ci,
  `dokumen` text COLLATE utf8mb4_unicode_ci,
  `dokumen_memo` text COLLATE utf8mb4_unicode_ci,
  `jth_tempo` date DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `updated_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tb_record`
--

INSERT INTO `tb_record` (`id`, `id_snp`, `cluster_id`, `sub_cluster_id`, `nomor_surat`, `tanggal_surat`, `perihal_surat`, `dokumen`, `dokumen_memo`, `jth_tempo`, `status`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(6, 'B/169/062026-SNP', 9, 28, 'B/169/062026', '2026-06-30', 'Pending Klaim JKK-PLKK di Kantor Cabang', 'dokumen/record-snp/OyIlhPLVVgfgMWMr6co2nz239za0BGG1D260nPAX.pdf', 'dokumen/memo-snp/zAdOjVvfUZnhWzYwQAioZFSvHz22LIT0vcNpDb6G.pdf', '2026-07-20', 'dalam_proses', 2, 2, '2026-07-09 08:16:30', '2026-07-09 08:22:10'),
(7, 'B/170/062026-SNP', 9, 28, 'B/170/062026', '2026-06-30', 'Evaluasi atas Implementasi Segregation of Duties pada Proses Klaim di Kantor Cabang', 'dokumen/record-snp/lMtQlG0deNvulP9GvmgvaCl4I0Mj1QabaqW84KX0.pdf', 'dokumen/memo-snp/1fVR03ZQ9tqEZlqRrV781rkAXuC4pOYbGHPi0F3i.pdf', '2026-07-20', 'dalam_proses', 2, 2, '2026-07-09 08:29:51', '2026-07-09 08:32:32'),
(8, 'B/171/062026-SNP', 10, 32, 'B/171/062026', '2026-06-30', 'Evaluasi Rencana Kerja Anggaran Tahunan (RKAT)', 'dokumen/record-snp/zRUfGSyP8KKr3J1A7AsfFTgfZukzTMKfxqN2VcPP.pdf', 'dokumen/memo-snp/WUQ93RSZdnViHszu3fOC92nGLp8nKGxby5mLNK57.pdf', '2026-07-20', 'dalam_proses', 2, 1, '2026-07-09 08:35:46', '2026-08-21 03:33:31'),
(9, 'B/10/012026-SNP', 9, 30, 'B/10/012026', '2026-01-07', 'Pengembangan Aplikasi Smart Investment System', 'dokumen/record-snp/RpK2CGG1fsvQ5VhIbTPTNR6DblYKLy9RvQtxdv0t.pdf', 'dokumen/memo-snp/tNN9f2WE4fZDWs5f0SxEJBUwVrWNzvgMi1d93zOs.pdf', '2026-01-27', 'dalam_proses', 1, 1, '2026-08-21 04:03:01', '2026-08-21 04:18:33'),
(10, 'B/14/012026-SNP', 5, 17, 'B/14/012026', '2026-01-13', 'Kasus Fraud Klaim Jaminan Kecelakaa Kerja (JKK)', 'dokumen/record-snp/jY3gJsl8Cj1sUA4lGf7R6op9xEzoBDOxea4pWosA.pdf', 'dokumen/memo-snp/4nGRDtE6BnIM50wFlUqE3Tbid52BzlYc4YZVVmNS.pdf', '2026-02-02', 'dalam_proses', 1, 1, '2026-08-21 04:10:01', '2026-08-21 04:25:21'),
(11, 'B/34/022026-SNP', 5, 18, 'B/34/022026', '2026-02-02', 'Pengembangan Sistem Aktuaria', 'dokumen/record-snp/PKUfdAa1lHLv7qffEWIVa2UOR4CS0UgbsBlzCqdX.pdf', 'dokumen/memo-snp/06m145vWXkZSrsxzABDPBLlcPuMwPEUwJ33mY1KH.pdf', '2026-02-20', 'dalam_proses', 1, 1, '2026-08-21 04:13:51', '2026-08-21 04:28:53');

-- --------------------------------------------------------

--
-- Table structure for table `tb_review`
--

CREATE TABLE `tb_review` (
  `id` bigint UNSIGNED NOT NULL,
  `id_butir_snp` varchar(70) COLLATE utf8mb4_unicode_ci NOT NULL,
  `putaran_tl` int UNSIGNED NOT NULL DEFAULT '1',
  `id_tanggapan` bigint UNSIGNED DEFAULT NULL,
  `id_tindak_lanjut` bigint UNSIGNED DEFAULT NULL,
  `tahap_review` enum('tanggapan','tindak_lanjut') COLLATE utf8mb4_unicode_ci NOT NULL,
  `komite_id` bigint UNSIGNED DEFAULT NULL,
  `hasil_review` text COLLATE utf8mb4_unicode_ci,
  `deliverables` text COLLATE utf8mb4_unicode_ci,
  `dokumen` text COLLATE utf8mb4_unicode_ci,
  `dokumen_memo` text COLLATE utf8mb4_unicode_ci,
  `status` enum('belum_ditanggapi','dalam_proses_reviu_dewas','dalam_proses_tindak_lanjut_direksi','selesai_tuntas') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'belum_ditanggapi',
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `updated_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tb_sub_cluster`
--

CREATE TABLE `tb_sub_cluster` (
  `id` bigint UNSIGNED NOT NULL,
  `cluster_id` bigint UNSIGNED NOT NULL,
  `nama_sub_cluster` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tb_sub_cluster`
--

INSERT INTO `tb_sub_cluster` (`id`, `cluster_id`, `nama_sub_cluster`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, 1, 'Perencanaan Strategis', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(2, 1, 'Pengelolaan Sistem Manajemen', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(3, 1, 'Kinerja Kantor Daerah', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(4, 1, 'Project Management', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(5, 2, 'Kepesertaan Penerima Upah', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(6, 2, 'Kepesertaan Bukan Penerima Upah', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(7, 2, 'Kepesertaan Jasa Konstruksi', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(8, 2, 'Kepesertaan Pekerja Migran Indonesia (PMI)', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(9, 2, 'Kepesertaan Penerima Bantuan Iuran (PBI)', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(10, 2, 'Penerimaan Iuran', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(11, 2, 'Pengawasan dan Pemeriksaan', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(12, 3, 'Manajemen dan Tata Kelola Data', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(13, 3, 'Pengembangan Teknologi Informasi', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(14, 3, 'Operasional Teknologi Informasi', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(15, 4, 'Advokasi Hukum', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(16, 4, 'Regulasi', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(17, 5, 'Manajemen Risiko', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(18, 5, 'Aktuaria', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(19, 6, 'Tata Kelola (Good Governance)', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(20, 6, 'Kepatuhan Internal (Compliance)', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(21, 7, 'Pengembangan Program', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(22, 7, 'Operasional Klaim dan Layanan', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(23, 7, 'Layanan Digital dan Contact Center', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(24, 7, 'Layanan Syariah', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(25, 8, 'Organization Development', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(26, 8, 'Pengelolaan SDM', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(27, 9, 'Standar dan Mutu Audit Internal', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(28, 9, 'Pengendalian Internal', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(29, 9, 'Tindak Lanjut Rekomendasi Audit Internal', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(30, 9, 'Tindak Lanjut Rekomendasi Pemeriksaan Eksternal', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(31, 10, 'Standar Akuntansi Keuangan', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(32, 10, 'Pengelolaan Keuangan', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(33, 10, 'Laporan Keuangan dan Laporan Pengelolaan Program', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(34, 10, 'RKAT', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(35, 11, 'Kebijakan Pengelolaan Investasi', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(36, 11, 'Manajemen Risiko Investasi', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(37, 12, 'Pengelolaan Aset Tetap', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(38, 12, 'Sarana Kesejahteraan Peserta (SKP)', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50'),
(39, 12, 'Tanggung Jawab Sosial Lingkungan (TJSL)', NULL, '2026-06-30 07:41:50', '2026-06-30 07:41:50');

-- --------------------------------------------------------

--
-- Table structure for table `tb_tanggapan`
--

CREATE TABLE `tb_tanggapan` (
  `id` bigint UNSIGNED NOT NULL,
  `id_butir_snp` varchar(70) COLLATE utf8mb4_unicode_ci NOT NULL,
  `butir_pic_id` bigint UNSIGNED DEFAULT NULL,
  `tanggapan` text COLLATE utf8mb4_unicode_ci,
  `deliverables` text COLLATE utf8mb4_unicode_ci,
  `dokumen` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `updated_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tb_tanggapan`
--

INSERT INTO `tb_tanggapan` (`id`, `id_butir_snp`, `butir_pic_id`, `tanggapan`, `deliverables`, `dokumen`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(3, 'B/34/022026-SNP.02', 72, 'ME/43/092026\r\nTES', 'TES 1', NULL, 1, 1, '2026-09-03 10:33:06', '2026-09-03 10:33:06'),
(4, 'B/34/022026-SNP.02', 70, 'TES 2', 'TES 2', NULL, 1, 1, '2026-09-03 10:33:56', '2026-09-03 10:33:56'),
(5, 'B/34/022026-SNP.02', 71, 'TES 3', 'TES 3', NULL, 1, 1, '2026-09-03 10:34:12', '2026-09-03 10:34:12');

-- --------------------------------------------------------

--
-- Table structure for table `tb_tindak_lanjut`
--

CREATE TABLE `tb_tindak_lanjut` (
  `id` bigint UNSIGNED NOT NULL,
  `id_butir_snp` varchar(70) COLLATE utf8mb4_unicode_ci NOT NULL,
  `butir_pic_id` bigint UNSIGNED DEFAULT NULL,
  `putaran_tl` int UNSIGNED NOT NULL DEFAULT '1',
  `tindak_lanjut` text COLLATE utf8mb4_unicode_ci,
  `deliverables` text COLLATE utf8mb4_unicode_ci,
  `dokumen` text COLLATE utf8mb4_unicode_ci,
  `jth_tempo` date DEFAULT NULL,
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `updated_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tb_butir_pic`
--
ALTER TABLE `tb_butir_pic`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tb_butir_pic_unique` (`id_butir_snp`,`unit_kerja_id`,`komite_id`,`jenis_pic`),
  ADD KEY `tb_butir_pic_id_butir_snp_index` (`id_butir_snp`),
  ADD KEY `tb_butir_pic_unit_kerja_id_index` (`unit_kerja_id`),
  ADD KEY `tb_butir_pic_komite_id_index` (`komite_id`);

--
-- Indexes for table `tb_butir_snp`
--
ALTER TABLE `tb_butir_snp`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tb_butir_snp_id_butir_snp_unique` (`id_butir_snp`),
  ADD KEY `tb_butir_snp_status_index` (`status`),
  ADD KEY `tb_butir_snp_id_snp_foreign` (`id_snp`);

--
-- Indexes for table `tb_cluster`
--
ALTER TABLE `tb_cluster`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tb_kompilasi`
--
ALTER TABLE `tb_kompilasi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tb_kompilasi_id_butir_snp_index` (`id_butir_snp`),
  ADD KEY `tb_kompilasi_tahap_kompilasi_index` (`tahap_kompilasi`),
  ADD KEY `tb_kompilasi_putaran_tl_index` (`putaran_tl`),
  ADD KEY `tb_kompilasi_id_butir_snp_tahap_kompilasi_putaran_tl_index` (`id_butir_snp`,`tahap_kompilasi`,`putaran_tl`),
  ADD KEY `tb_kompilasi_status_index` (`status`);

--
-- Indexes for table `tb_record`
--
ALTER TABLE `tb_record`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tb_record_id_snp_unique` (`id_snp`),
  ADD KEY `tb_record_cluster_id_foreign` (`cluster_id`),
  ADD KEY `tb_record_sub_cluster_id_foreign` (`sub_cluster_id`);

--
-- Indexes for table `tb_review`
--
ALTER TABLE `tb_review`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tb_review_id_butir_snp_index` (`id_butir_snp`),
  ADD KEY `tb_review_id_tanggapan_index` (`id_tanggapan`),
  ADD KEY `tb_review_id_tindak_lanjut_index` (`id_tindak_lanjut`),
  ADD KEY `tb_review_komite_id_index` (`komite_id`),
  ADD KEY `tb_review_tahap_review_index` (`tahap_review`),
  ADD KEY `tb_review_putaran_tl_index` (`putaran_tl`),
  ADD KEY `tb_review_id_butir_snp_tahap_review_putaran_tl_index` (`id_butir_snp`,`tahap_review`,`putaran_tl`),
  ADD KEY `tb_review_status_index` (`status`);

--
-- Indexes for table `tb_sub_cluster`
--
ALTER TABLE `tb_sub_cluster`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tb_sub_cluster_cluster_id_foreign` (`cluster_id`);

--
-- Indexes for table `tb_tanggapan`
--
ALTER TABLE `tb_tanggapan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tb_tanggapan_butir_pic_unique` (`id_butir_snp`,`butir_pic_id`),
  ADD KEY `tb_tanggapan_id_butir_snp_index` (`id_butir_snp`),
  ADD KEY `tb_tanggapan_butir_pic_id_index` (`butir_pic_id`),
  ADD KEY `tb_tanggapan_id_butir_snp_butir_pic_id_index` (`id_butir_snp`,`butir_pic_id`);

--
-- Indexes for table `tb_tindak_lanjut`
--
ALTER TABLE `tb_tindak_lanjut`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tb_tindak_lanjut_id_butir_snp_index` (`id_butir_snp`),
  ADD KEY `tb_tindak_lanjut_butir_pic_id_index` (`butir_pic_id`),
  ADD KEY `tb_tindak_lanjut_putaran_tl_index` (`putaran_tl`),
  ADD KEY `tb_tindak_lanjut_id_butir_snp_butir_pic_id_putaran_tl_index` (`id_butir_snp`,`butir_pic_id`,`putaran_tl`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tb_butir_pic`
--
ALTER TABLE `tb_butir_pic`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;

--
-- AUTO_INCREMENT for table `tb_butir_snp`
--
ALTER TABLE `tb_butir_snp`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `tb_cluster`
--
ALTER TABLE `tb_cluster`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `tb_kompilasi`
--
ALTER TABLE `tb_kompilasi`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tb_record`
--
ALTER TABLE `tb_record`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `tb_review`
--
ALTER TABLE `tb_review`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tb_sub_cluster`
--
ALTER TABLE `tb_sub_cluster`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `tb_tanggapan`
--
ALTER TABLE `tb_tanggapan`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tb_tindak_lanjut`
--
ALTER TABLE `tb_tindak_lanjut`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tb_butir_pic`
--
ALTER TABLE `tb_butir_pic`
  ADD CONSTRAINT `tb_butir_pic_id_butir_snp_foreign` FOREIGN KEY (`id_butir_snp`) REFERENCES `tb_butir_snp` (`id_butir_snp`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
