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
-- Database: `sidewasi_sidewas`
--

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cache`
--

INSERT INTO `cache` (`key`, `value`, `expiration`) VALUES
('sidewas-cache-krisna.adriyanto@bpjsketenagakerjaan.go.id|36.64.21.139', 'i:1;', 1782812494),
('sidewas-cache-krisna.adriyanto@bpjsketenagakerjaan.go.id|36.64.21.139:timer', 'i:1782812494;', 1782812494),
('sidewas-cache-pepd.sekdewas@bpjsketenagakerjaan.ac.id|180.245.186.206', 'i:1;', 1783581962),
('sidewas-cache-pepd.sekdewas@bpjsketenagakerjaan.ac.id|180.245.186.206:timer', 'i:1783581962;', 1783581962),
('sidewas-cache-pepd.sekdewas@bpjsketenagakerjaan.go.id|180.245.186.206', 'i:1;', 1783582008),
('sidewas-cache-pepd.sekdewas@bpjsketenagakerjaan.go.id|180.245.186.206:timer', 'i:1783582008;', 1783582008),
('sidewas-cache-sekdewas@bpjsketenagakerjaan.go.id|36.64.21.139', 'i:2;', 1782812966),
('sidewas-cache-sekdewas@bpjsketenagakerjaan.go.id|36.64.21.139:timer', 'i:1782812966;', 1782812966);

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint UNSIGNED NOT NULL,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint UNSIGNED NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint UNSIGNED NOT NULL,
  `reserved_at` int UNSIGNED DEFAULT NULL,
  `available_at` int UNSIGNED NOT NULL,
  `created_at` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int UNSIGNED NOT NULL,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2026_05_06_101519_create_snp_tables', 1),
(5, '2026_05_07_000000_create_main_custom_tables', 1),
(6, '2026_05_22_110928_create_ragab_tables', 1),
(7, '2026_06_02_103031_create_rawas_tables', 1),
(8, '2026_06_03_093422_create_djsn_tables', 1),
(9, '2026_06_23_093422_create_produk_hukum_tables', 1),
(10, '2026_06_23_134204_create_external_tables', 1);

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('IPsRVcGHtb540usTpX18PuCcHjZUANJb3Oh54EVA', NULL, '31.220.97.173', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/93.0.4577.0 Safari/537.36', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoiUjNFNGdkR0dOb3NCSkFnWktQUzM1MlpzRjFnWk4yN3V1dkVySWJCZiI7czozOiJ1cmwiO2E6MTp7czo4OiJpbnRlbmRlZCI7czoxODoiaHR0cHM6Ly9zaWRld2FzLmlkIjt9czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTg6Imh0dHBzOi8vc2lkZXdhcy5pZCI7czo1OiJyb3V0ZSI7czo5OiJkYXNoYm9hcmQiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1788441369),
('mKx9dawtDaeMEt89gP59AKOU1BZ2yvEOhRPThdN3', NULL, '31.220.97.173', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/93.0.4577.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoicGVEaXZzemlVOWxWbWtzYTRjWnh3TXlzRWdvUUhUWmdWWGFtRGNHNiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjQ6Imh0dHBzOi8vc2lkZXdhcy5pZC9sb2dpbiI7czo1OiJyb3V0ZSI7czo1OiJsb2dpbiI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1788441369),
('raqQL90k4aY60fg4mcU7uhoHpORBIctdoGAxaSfj', NULL, '207.46.13.87', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm) Chrome/116.0.1938.76 Safari/537.36', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoiME1RaUlqd2tFU04wNWVIUmp0TmJ5OGJJU0pjemhjNWhDM3l2ZDRpOSI7czozOiJ1cmwiO2E6MTp7czo4OiJpbnRlbmRlZCI7czoyMjoiaHR0cHM6Ly93d3cuc2lkZXdhcy5pZCI7fXM6OToiX3ByZXZpb3VzIjthOjI6e3M6MzoidXJsIjtzOjI4OiJodHRwczovL3d3dy5zaWRld2FzLmlkL2xvZ2luIjtzOjU6InJvdXRlIjtzOjU6ImxvZ2luIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1788438367),
('THbPxSyvtkq1JdWLyOiCApdsTj2nEHOxQ7GWYU0l', NULL, '31.220.97.173', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/93.0.4577.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiNUZxVHk3QTVFdGxXOTJmUDRxcFhNdTB5bkxwUkhwajN5b2k0OUQwdCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjQ6Imh0dHBzOi8vc2lkZXdhcy5pZC9sb2dpbiI7czo1OiJyb3V0ZSI7czo1OiJsb2dpbiI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1788444954),
('UowC8WE2hVvhBeOfPHKSjnpOC3pDQFba113XbTkh', NULL, '31.220.97.173', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/93.0.4577.0 Safari/537.36', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoibGtBUDJ5OXZCRmt1ZHJOVDFTTEQzRW1KTWRFN2JhQXdSTHd1ZUlXRSI7czozOiJ1cmwiO2E6MTp7czo4OiJpbnRlbmRlZCI7czoxODoiaHR0cHM6Ly9zaWRld2FzLmlkIjt9czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTg6Imh0dHBzOi8vc2lkZXdhcy5pZCI7czo1OiJyb3V0ZSI7czo5OiJkYXNoYm9hcmQiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1788444953),
('WxUR0o8lcbHgApRnK4bCrCOmgbCTvZU0D7DlUiDr', 1, '103.178.88.66', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoiODVwMTFKaTV4em9HZjJiWnZ1N0xvclNqVUxNZDNxdUFaazRmRlJVMCI7czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTtzOjk6Il9wcmV2aW91cyI7YToyOntzOjM6InVybCI7czozMjoiaHR0cHM6Ly9zaWRld2FzLmlkL3NucC9rb21waWxhc2kiO3M6NToicm91dGUiO3M6MTk6InNucC5rb21waWxhc2kuaW5kZXgiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1788447980);

-- --------------------------------------------------------

--
-- Table structure for table `tb_delete_requests`
--

CREATE TABLE `tb_delete_requests` (
  `id` bigint UNSIGNED NOT NULL,
  `type_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `database_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `table_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `record_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `record_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `requested_by` bigint UNSIGNED DEFAULT NULL,
  `verified_by` bigint UNSIGNED DEFAULT NULL,
  `approved_by` bigint UNSIGNED DEFAULT NULL,
  `rejected_by` bigint UNSIGNED DEFAULT NULL,
  `status` enum('pending_admin_verification','pending_super_admin_approval','approved','rejected','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_super_admin_approval',
  `requested_at` timestamp NULL DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tb_direktorat`
--

CREATE TABLE `tb_direktorat` (
  `id` bigint UNSIGNED NOT NULL,
  `nama_direktorat` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kode_direktorat` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tb_direktorat`
--

INSERT INTO `tb_direktorat` (`id`, `nama_direktorat`, `kode_direktorat`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, 'Direktorat Utama', NULL, NULL, '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(2, 'Direktorat Kepesertaan', NULL, NULL, '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(3, 'Direktorat Pelayanan', NULL, NULL, '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(4, 'Direktorat Pengembangan Investasi', NULL, NULL, '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(5, 'Direktorat Perencanaan Strategis dan TI', NULL, NULL, '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(6, 'Direktorat Keuangan dan Manajemen Risiko', NULL, NULL, '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(7, 'Direktorat Human Capital dan Umum', NULL, NULL, '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(8, 'Dewan Pengawas', NULL, NULL, '2026-06-30 07:41:51', '2026-06-30 07:41:51');

-- --------------------------------------------------------

--
-- Table structure for table `tb_komite`
--

CREATE TABLE `tb_komite` (
  `id` bigint UNSIGNED NOT NULL,
  `nama_komite` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kode_komite` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tb_komite`
--

INSERT INTO `tb_komite` (`id`, `nama_komite`, `kode_komite`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, 'Komite Pengawasan Kinerja Badan', 'KPKB', NULL, '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(2, 'Komite Pengawasan Manajemen Risiko', 'KPMR', NULL, '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(3, 'Komite Audit, Anggaran dan Investasi', 'KAAI', NULL, '2026-06-30 07:41:51', '2026-06-30 07:41:51');

-- --------------------------------------------------------

--
-- Table structure for table `tb_log_activity`
--

CREATE TABLE `tb_log_activity` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `type_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `database_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `table_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `record_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `ip_address` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ;

--
-- Dumping data for table `tb_log_activity`
--

INSERT INTO `tb_log_activity` (`id`, `user_id`, `type_code`, `database_name`, `table_name`, `record_key`, `action`, `description`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`, `updated_at`) VALUES
(1, 1, 'administrasi', 'sidewas', 'users', '2', 'update_user', 'User mengubah akses user melalui Manajemen User.', '{\"name\":\"Sekretariat Dewan Pengawas\",\"email\":\"sekdewas@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[],\"unit_kerja_ids\":[],\"komite_ids\":[]}', '{\"name\":\"Sekretariat Dewan Pengawas\",\"email\":\"sekdewas@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[1],\"direktorat_id\":\"8\",\"assignment\":{\"type\":\"unit\",\"id\":26}}', '103.168.122.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 08:39:20', '2026-06-30 08:39:20'),
(2, 2, 'administrasi', 'sidewas', 'users', '1', 'update_user', 'User mengubah akses user melalui Manajemen User.', '{\"name\":\"Bagian Perencanaan dan Evaluasi Pengawasan Dewan Pengawas\",\"email\":\"pepd.sekdewas@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[1],\"unit_kerja_ids\":[],\"komite_ids\":[]}', '{\"name\":\"Bagian Perencanaan dan Evaluasi Pengawasan Dewan Pengawas\",\"email\":\"pepd.sekdewas@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[1],\"direktorat_id\":\"8\",\"assignment\":{\"type\":\"unit\",\"id\":26}}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 08:44:15', '2026-06-30 08:44:15'),
(3, 2, 'administrasi', 'sidewas', 'users', '3', 'create_user', 'Super Admin menambahkan user baru melalui Manajemen User.', NULL, '{\"id\":3,\"email\":\"ppht.sekdewas@bpjsketenagakerjaan.go.id\",\"role_type_id\":\"9\",\"direktorat_id\":\"8\",\"assignment\":{\"type\":\"unit\",\"id\":26}}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 08:49:27', '2026-06-30 08:49:27'),
(4, 2, 'administrasi', 'sidewas', 'users', '3', 'update_user', 'User mengubah akses user melalui Manajemen User.', '{\"name\":\"Bagian Persidangan, Produk Hukum dan Tata Kelola Dewan Pengawas\",\"email\":\"ppht.sekdewas@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[9],\"unit_kerja_ids\":[26],\"komite_ids\":[]}', '{\"name\":\"Bagian Persidangan, Produk Hukum dan Tata Kelola Dewan Pengawas\",\"email\":\"ppht.sekdewas@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[3,4,6,7],\"direktorat_id\":\"8\",\"assignment\":{\"type\":\"unit\",\"id\":26}}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 08:51:19', '2026-06-30 08:51:19'),
(5, 2, 'administrasi', 'sidewas', 'users', '3', 'update_user', 'User mengubah akses user melalui Manajemen User.', '{\"name\":\"Bagian Persidangan, Produk Hukum dan Tata Kelola Dewan Pengawas\",\"email\":\"ppht.sekdewas@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[3,4,6,7],\"unit_kerja_ids\":[26],\"komite_ids\":[]}', '{\"name\":\"Bagian Persidangan, Produk Hukum dan Tata Kelola Dewan Pengawas\",\"email\":\"ppht.sekdewas@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[3,4,6,7],\"direktorat_id\":\"8\",\"assignment\":{\"type\":\"unit\",\"id\":26}}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 08:51:19', '2026-06-30 08:51:19'),
(6, 2, 'administrasi', 'sidewas', 'users', '3', 'update_user', 'User mengubah akses user melalui Manajemen User.', '{\"name\":\"Bagian Persidangan, Produk Hukum dan Tata Kelola Dewan Pengawas\",\"email\":\"ppht.sekdewas@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[3,4,6,7],\"unit_kerja_ids\":[26],\"komite_ids\":[]}', '{\"name\":\"Bagian Persidangan, Produk Hukum dan Tata Kelola Dewan Pengawas\",\"email\":\"ppht.sekdewas@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[3,4,6,7,18,21],\"direktorat_id\":\"8\",\"assignment\":{\"type\":\"unit\",\"id\":26}}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 08:52:21', '2026-06-30 08:52:21'),
(7, 2, 'administrasi', 'sidewas', 'users', '4', 'create_user', 'Super Admin menambahkan user baru melalui Manajemen User.', NULL, '{\"id\":4,\"email\":\"krisna.adriyanto@bpjsketenagakerjaan.go.id\",\"role_type_id\":\"8\",\"direktorat_id\":\"8\",\"assignment\":{\"type\":\"unit\",\"id\":26}}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 09:09:32', '2026-06-30 09:09:32'),
(8, 2, 'administrasi', 'sidewas', 'users', '4', 'update_user', 'User mengubah akses user melalui Manajemen User.', '{\"name\":\"Krisna Adriyanto\",\"email\":\"krisna.adriyanto@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[8],\"unit_kerja_ids\":[26],\"komite_ids\":[]}', '{\"name\":\"Krisna Adriyanto\",\"email\":\"krisna.adriyanto@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[8,11,19,20,22,23],\"direktorat_id\":\"8\",\"assignment\":{\"type\":\"unit\",\"id\":26}}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 09:10:22', '2026-06-30 09:10:22'),
(9, 2, 'administrasi', 'sidewas', 'users', '1', 'update_user', 'User mengubah akses user melalui Manajemen User.', '{\"name\":\"Bagian Perencanaan dan Evaluasi Pengawasan Dewan Pengawas\",\"email\":\"pepd.sekdewas@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[1],\"unit_kerja_ids\":[26],\"komite_ids\":[]}', '{\"name\":\"Bagian Perencanaan dan Evaluasi Pengawasan Dewan Pengawas\",\"email\":\"pepd.sekdewas@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[2,5,19,20,22,23],\"direktorat_id\":\"8\",\"assignment\":{\"type\":\"unit\",\"id\":26}}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 09:11:27', '2026-06-30 09:11:27'),
(10, 2, 'administrasi', 'sidewas', 'users', '5', 'create_user', 'Super Admin menambahkan user baru melalui Manajemen User.', NULL, '{\"id\":5,\"email\":\"sulfadli.muslim@bpjsketenagakerjaan.go.id\",\"role_type_id\":\"8\",\"direktorat_id\":\"8\",\"assignment\":{\"type\":\"unit\",\"id\":26}}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 09:13:09', '2026-06-30 09:13:09'),
(11, 2, 'administrasi', 'sidewas', 'users', '5', 'update_user', 'User mengubah akses user melalui Manajemen User.', '{\"name\":\"Sulfadli Muslim\",\"email\":\"sulfadli.muslim@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[8],\"unit_kerja_ids\":[26],\"komite_ids\":[]}', '{\"name\":\"Sulfadli Muslim\",\"email\":\"sulfadli.muslim@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[8,11,19,20,22,23],\"direktorat_id\":\"8\",\"assignment\":{\"type\":\"unit\",\"id\":26}}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 09:13:46', '2026-06-30 09:13:46'),
(12, 2, 'administrasi', 'sidewas', 'users', '6', 'create_user', 'Super Admin menambahkan user baru melalui Manajemen User.', NULL, '{\"id\":6,\"email\":\"setyo.ardy@bpjsketenagakerjaan.go.id\",\"role_type_id\":\"8\",\"direktorat_id\":\"8\",\"assignment\":{\"type\":\"unit\",\"id\":26}}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 09:14:36', '2026-06-30 09:14:36'),
(13, 2, 'administrasi', 'sidewas', 'users', '6', 'update_user', 'User mengubah akses user melalui Manajemen User.', '{\"name\":\"Setyo Ardy Gunawan\",\"email\":\"setyo.ardy@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[8],\"unit_kerja_ids\":[26],\"komite_ids\":[]}', '{\"name\":\"Setyo Ardy Gunawan\",\"email\":\"setyo.ardy@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[8,11,19,20,22,23],\"direktorat_id\":\"8\",\"assignment\":{\"type\":\"unit\",\"id\":26}}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 09:15:08', '2026-06-30 09:15:08'),
(14, 2, 'administrasi', 'sidewas', 'users', '7', 'create_user', 'Super Admin menambahkan user baru melalui Manajemen User.', NULL, '{\"id\":7,\"email\":\"deni.juandani@bpjsketenagakerjaan.go.id\",\"role_type_id\":\"2\",\"direktorat_id\":\"8\",\"assignment\":{\"type\":\"unit\",\"id\":26}}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 09:16:02', '2026-06-30 09:16:02'),
(15, 2, 'administrasi', 'sidewas', 'users', '7', 'update_user', 'User mengubah akses user melalui Manajemen User.', '{\"name\":\"Deni Juandani\",\"email\":\"deni.juandani@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[2],\"unit_kerja_ids\":[26],\"komite_ids\":[]}', '{\"name\":\"Deni Juandani\",\"email\":\"deni.juandani@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[2,5,19,20,22,23],\"direktorat_id\":\"8\",\"assignment\":{\"type\":\"unit\",\"id\":26}}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 09:16:41', '2026-06-30 09:16:41'),
(16, 2, 'administrasi', 'sidewas', 'users', '8', 'create_user', 'Super Admin menambahkan user baru melalui Manajemen User.', NULL, '{\"id\":8,\"email\":\"fanny.amalul@bpjsketenagakerjaan.go.id\",\"role_type_id\":\"9\",\"direktorat_id\":\"8\",\"assignment\":{\"type\":\"unit\",\"id\":26}}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 09:18:08', '2026-06-30 09:18:08'),
(17, 2, 'administrasi', 'sidewas', 'users', '8', 'update_user', 'User mengubah akses user melalui Manajemen User.', '{\"name\":\"Fanny Amalul Arifin\",\"email\":\"fanny.amalul@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[9],\"unit_kerja_ids\":[26],\"komite_ids\":[]}', '{\"name\":\"Fanny Amalul Arifin\",\"email\":\"fanny.amalul@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[6,9,10,12,18,21],\"direktorat_id\":\"8\",\"assignment\":{\"type\":\"unit\",\"id\":26}}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 09:18:41', '2026-06-30 09:18:41'),
(18, 2, 'administrasi', 'sidewas', 'users', '9', 'create_user', 'Super Admin menambahkan user baru melalui Manajemen User.', NULL, '{\"id\":9,\"email\":\"farid.nuriman@bpjsketenagakerjaan.go.id\",\"role_type_id\":\"9\",\"direktorat_id\":\"8\",\"assignment\":{\"type\":\"unit\",\"id\":26}}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 09:20:06', '2026-06-30 09:20:06'),
(19, 2, 'administrasi', 'sidewas', 'users', '9', 'update_user', 'User mengubah akses user melalui Manajemen User.', '{\"name\":\"Farid Nur Iman\",\"email\":\"farid.nuriman@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[9],\"unit_kerja_ids\":[26],\"komite_ids\":[]}', '{\"name\":\"Farid Nur Iman\",\"email\":\"farid.nuriman@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[9,10,12,18,21,22],\"direktorat_id\":\"8\",\"assignment\":{\"type\":\"unit\",\"id\":26}}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 09:20:38', '2026-06-30 09:20:38'),
(20, 2, 'administrasi', 'sidewas', 'users', '10', 'create_user', 'Super Admin menambahkan user baru melalui Manajemen User.', NULL, '{\"id\":10,\"email\":\"mohamad.rhesa@bpjsketenagakerjaan.go.id\",\"role_type_id\":\"9\",\"direktorat_id\":\"8\",\"assignment\":{\"type\":\"unit\",\"id\":26}}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 09:22:12', '2026-06-30 09:22:12'),
(21, 2, 'administrasi', 'sidewas', 'users', '10', 'update_user', 'User mengubah akses user melalui Manajemen User.', '{\"name\":\"Mohamad Rhesa Adisty\",\"email\":\"mohamad.rhesa@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[9],\"unit_kerja_ids\":[26],\"komite_ids\":[]}', '{\"name\":\"Mohamad Rhesa Adisty\",\"email\":\"mohamad.rhesa@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[9,10,12,18,21,22],\"direktorat_id\":\"8\",\"assignment\":{\"type\":\"unit\",\"id\":26}}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 09:22:37', '2026-06-30 09:22:37'),
(22, 2, 'administrasi', 'sidewas', 'users', '11', 'create_user', 'Super Admin menambahkan user baru melalui Manajemen User.', NULL, '{\"id\":11,\"email\":\"lubis.latif@bpjsketenagakerjaan.go.id\",\"role_type_id\":\"1\",\"direktorat_id\":\"8\",\"assignment\":{\"type\":\"unit\",\"id\":26}}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 09:23:33', '2026-06-30 09:23:33'),
(23, 2, 'administrasi', 'sidewas', 'users', '12', 'create_user', 'Super Admin menambahkan user baru melalui Manajemen User.', NULL, '{\"id\":12,\"email\":\"fitri.piralanasih@bpjsketenagakerjaan.go.id\",\"role_type_id\":\"18\",\"direktorat_id\":\"8\",\"assignment\":{\"type\":\"unit\",\"id\":26}}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 09:24:46', '2026-06-30 09:24:46'),
(24, 2, 'administrasi', 'sidewas', 'users', '12', 'update_user', 'User mengubah akses user melalui Manajemen User.', '{\"name\":\"Fitri Piralanasih\",\"email\":\"fitri.piralanasih@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[18],\"unit_kerja_ids\":[26],\"komite_ids\":[]}', '{\"name\":\"Fitri Piralanasih\",\"email\":\"fitri.piralanasih@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[18,19,20,21,22,23],\"direktorat_id\":\"8\",\"assignment\":{\"type\":\"unit\",\"id\":26}}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 09:25:03', '2026-06-30 09:25:03'),
(25, 2, 'administrasi', 'sidewas', 'users', '13', 'create_user', 'Super Admin menambahkan user baru melalui Manajemen User.', NULL, '{\"id\":13,\"email\":\"risa.purnama@bpjsketenagakerjaan.go.id\",\"role_type_id\":\"18\",\"direktorat_id\":\"8\",\"assignment\":{\"type\":\"unit\",\"id\":26}}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 09:25:48', '2026-06-30 09:25:48'),
(26, 2, 'administrasi', 'sidewas', 'users', '13', 'update_user', 'User mengubah akses user melalui Manajemen User.', '{\"name\":\"Risa Purnama\",\"email\":\"risa.purnama@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[18],\"unit_kerja_ids\":[26],\"komite_ids\":[]}', '{\"name\":\"Risa Purnama\",\"email\":\"risa.purnama@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[18,19,20,21,22,23],\"direktorat_id\":\"8\",\"assignment\":{\"type\":\"unit\",\"id\":26}}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 09:26:04', '2026-06-30 09:26:04'),
(27, 2, 'administrasi', 'sidewas', 'users', '14', 'create_user', 'Super Admin menambahkan user baru melalui Manajemen User.', NULL, '{\"id\":14,\"email\":\"lucky.oktavianto@bpjsketenagakerjaan.go.id\",\"role_type_id\":\"18\",\"direktorat_id\":\"8\",\"assignment\":{\"type\":\"unit\",\"id\":26}}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 09:27:17', '2026-06-30 09:27:17'),
(28, 2, 'administrasi', 'sidewas', 'users', '14', 'update_user', 'User mengubah akses user melalui Manajemen User.', '{\"name\":\"Lucky Oktavianto\",\"email\":\"lucky.oktavianto@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[18],\"unit_kerja_ids\":[26],\"komite_ids\":[]}', '{\"name\":\"Lucky Oktavianto\",\"email\":\"lucky.oktavianto@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[18,19,20,21,22,23],\"direktorat_id\":\"8\",\"assignment\":{\"type\":\"unit\",\"id\":26}}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 09:27:35', '2026-06-30 09:27:35'),
(29, 2, 'administrasi', 'sidewas', 'users', '15', 'create_user', 'Super Admin menambahkan user baru melalui Manajemen User.', NULL, '{\"id\":15,\"email\":\"sefi.dwi@bpjsketenagakerjaan.go.id\",\"role_type_id\":\"18\",\"direktorat_id\":\"8\",\"assignment\":{\"type\":\"unit\",\"id\":26}}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 09:29:34', '2026-06-30 09:29:34'),
(30, 2, 'administrasi', 'sidewas', 'users', '15', 'update_user', 'User mengubah akses user melalui Manajemen User.', '{\"name\":\"Sefi Dwi Prasanti\",\"email\":\"sefi.dwi@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[18],\"unit_kerja_ids\":[26],\"komite_ids\":[]}', '{\"name\":\"Sefi Dwi Prasanti\",\"email\":\"sefi.dwi@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[18,20,21,22,23],\"direktorat_id\":\"8\",\"assignment\":{\"type\":\"unit\",\"id\":26}}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 09:30:08', '2026-06-30 09:30:08'),
(31, 2, 'administrasi', 'sidewas', 'users', '16', 'create_user', 'Super Admin menambahkan user baru melalui Manajemen User.', NULL, '{\"id\":16,\"email\":\"siti.rohani@bpjsketenagakerjaan.go.id\",\"role_type_id\":\"18\",\"direktorat_id\":\"8\",\"assignment\":{\"type\":\"unit\",\"id\":26}}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 09:31:15', '2026-06-30 09:31:15'),
(32, 2, 'administrasi', 'sidewas', 'users', '16', 'update_user', 'User mengubah akses user melalui Manajemen User.', '{\"name\":\"Siti Rohani\",\"email\":\"siti.rohani@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[18],\"unit_kerja_ids\":[26],\"komite_ids\":[]}', '{\"name\":\"Siti Rohani\",\"email\":\"siti.rohani@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[18,20,21,22,23],\"direktorat_id\":\"8\",\"assignment\":{\"type\":\"unit\",\"id\":26}}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 09:31:38', '2026-06-30 09:31:38'),
(33, 2, 'administrasi', 'sidewas', 'users', '17', 'create_user', 'Super Admin menambahkan user baru melalui Manajemen User.', NULL, '{\"id\":17,\"email\":\"kartikarina.widyastuti@bpjsketenagakerjaan.go.id\",\"role_type_id\":\"18\",\"direktorat_id\":\"8\",\"assignment\":{\"type\":\"unit\",\"id\":26}}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 09:32:51', '2026-06-30 09:32:51'),
(34, 2, 'administrasi', 'sidewas', 'users', '17', 'update_user', 'User mengubah akses user melalui Manajemen User.', '{\"name\":\"Kartikarina Widyastuti\",\"email\":\"kartikarina.widyastuti@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[18],\"unit_kerja_ids\":[26],\"komite_ids\":[]}', '{\"name\":\"Kartikarina Widyastuti\",\"email\":\"kartikarina.widyastuti@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[18,19,20,21,22,23],\"direktorat_id\":\"8\",\"assignment\":{\"type\":\"unit\",\"id\":26}}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 09:33:09', '2026-06-30 09:33:09'),
(35, 2, 'administrasi', 'sidewas', 'users', '18', 'create_user', 'Super Admin menambahkan user baru melalui Manajemen User.', NULL, '{\"id\":18,\"email\":\"fauzia.amalia@bpjsketenagakerjaan.go.id\",\"role_type_id\":\"18\",\"direktorat_id\":\"8\",\"assignment\":{\"type\":\"unit\",\"id\":26}}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 09:34:53', '2026-06-30 09:34:53'),
(36, 2, 'administrasi', 'sidewas', 'users', '18', 'update_user', 'User mengubah akses user melalui Manajemen User.', '{\"name\":\"Fauzia Amalia Rachmawatie\",\"email\":\"fauzia.amalia@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[18],\"unit_kerja_ids\":[26],\"komite_ids\":[]}', '{\"name\":\"Fauzia Amalia Rachmawatie\",\"email\":\"fauzia.amalia@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[18,20,21,22,23],\"direktorat_id\":\"8\",\"assignment\":{\"type\":\"unit\",\"id\":26}}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 09:35:10', '2026-06-30 09:35:10'),
(37, 2, 'administrasi', 'sidewas', 'users', '19', 'create_user', 'Super Admin menambahkan user baru melalui Manajemen User.', NULL, '{\"id\":19,\"email\":\"ines.ningrum@bpjsketenagakerjaan.go.id\",\"role_type_id\":\"18\",\"direktorat_id\":\"8\",\"assignment\":{\"type\":\"unit\",\"id\":26}}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 09:36:37', '2026-06-30 09:36:37'),
(38, 2, 'administrasi', 'sidewas', 'users', '19', 'update_user', 'User mengubah akses user melalui Manajemen User.', '{\"name\":\"Ines Kusuma Ningrum\",\"email\":\"ines.ningrum@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[18],\"unit_kerja_ids\":[26],\"komite_ids\":[]}', '{\"name\":\"Ines Kusuma Ningrum\",\"email\":\"ines.ningrum@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[18,20,21,22,23],\"direktorat_id\":\"8\",\"assignment\":{\"type\":\"unit\",\"id\":26}}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 09:36:54', '2026-06-30 09:36:54'),
(39, 2, 'administrasi', 'sidewas', 'users', '13', 'update_user', 'User mengubah akses user melalui Manajemen User.', '{\"name\":\"Risa Purnama\",\"email\":\"risa.purnama@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[18,19,20,21,22,23],\"unit_kerja_ids\":[26],\"komite_ids\":[]}', '{\"name\":\"Risa Purnama\",\"email\":\"risa.purnama@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[18,20,21,22,23],\"direktorat_id\":\"8\",\"assignment\":{\"type\":\"unit\",\"id\":26}}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 09:37:16', '2026-06-30 09:37:16'),
(40, 2, 'administrasi', 'sidewas', 'users', '14', 'update_user', 'User mengubah akses user melalui Manajemen User.', '{\"name\":\"Lucky Oktavianto\",\"email\":\"lucky.oktavianto@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[18,19,20,21,22,23],\"unit_kerja_ids\":[26],\"komite_ids\":[]}', '{\"name\":\"Lucky Oktavianto\",\"email\":\"lucky.oktavianto@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[18,20,21,22,23],\"direktorat_id\":\"8\",\"assignment\":{\"type\":\"unit\",\"id\":26}}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 09:37:47', '2026-06-30 09:37:47'),
(41, 2, 'administrasi', 'sidewas', 'users', '1', 'update_user', 'User mengubah akses user melalui Manajemen User.', '{\"name\":\"Bagian Perencanaan dan Evaluasi Pengawasan Dewan Pengawas\",\"email\":\"pepd.sekdewas@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[2,5,19,20,22,23],\"unit_kerja_ids\":[26],\"komite_ids\":[]}', '{\"name\":\"Bagian Perencanaan dan Evaluasi Pengawasan Dewan Pengawas\",\"email\":\"pepd.sekdewas@bpjsketenagakerjaan.go.id\",\"role_type_ids\":[1],\"direktorat_id\":\"8\",\"assignment\":{\"type\":\"unit\",\"id\":26}}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 09:49:15', '2026-06-30 09:49:15'),
(42, 1, 'snp', 'sidewas_snp', 'tb_record', 'B/09/062026-SNP', 'create', 'User membuat perekaman surat SNP Dewas.', NULL, '{\"cluster_id\":\"10\",\"sub_cluster_id\":\"31\",\"nomor_surat\":\"B\\/09\\/062026\",\"tanggal_surat\":\"2026-07-08T17:00:00.000000Z\",\"perihal_surat\":\"tes\",\"dokumen\":null,\"dokumen_memo\":null,\"status\":\"draft\",\"created_by\":1,\"updated_by\":1,\"id_snp\":\"B\\/09\\/062026-SNP\",\"jth_tempo\":\"2026-07-28T17:00:00.000000Z\",\"updated_at\":\"2026-07-09T07:37:39.000000Z\",\"created_at\":\"2026-07-09T07:37:39.000000Z\",\"id\":1}', '180.245.186.206', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-09 07:37:39', '2026-07-09 07:37:39'),
(43, 1, 'snp', 'sidewas_snp', 'tb_record', 'B/09/062026-SNP', 'delete', 'Super Admin menghapus perekaman SNP secara langsung.', '{\"id\":1,\"id_snp\":\"B\\/09\\/062026-SNP\",\"cluster_id\":\"10\",\"sub_cluster_id\":\"31\",\"nomor_surat\":\"B\\/09\\/062026\",\"tanggal_surat\":\"2026-07-08T17:00:00.000000Z\",\"perihal_surat\":\"tes\",\"dokumen\":null,\"dokumen_memo\":null,\"jth_tempo\":\"2026-07-28T17:00:00.000000Z\",\"status\":\"draft\",\"created_by\":\"1\",\"updated_by\":\"1\",\"created_at\":\"2026-07-09T07:37:39.000000Z\",\"updated_at\":\"2026-07-09T07:37:39.000000Z\",\"butir_snp\":[],\"cluster\":{\"id\":10,\"nama_cluster\":\"Akuntansi dan Keuangan\",\"keterangan\":null,\"created_at\":\"2026-06-30T07:41:50.000000Z\",\"updated_at\":\"2026-06-30T07:41:50.000000Z\"},\"sub_cluster\":{\"id\":31,\"cluster_id\":\"10\",\"nama_sub_cluster\":\"Standar Akuntansi Keuangan\",\"keterangan\":null,\"created_at\":\"2026-06-30T07:41:50.000000Z\",\"updated_at\":\"2026-06-30T07:41:50.000000Z\"}}', NULL, '180.245.186.206', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-09 07:38:43', '2026-07-09 07:38:43'),
(44, 1, 'snp', 'sidewas_snp', 'tb_record', 'B/09/052026-SNP', 'create', 'User membuat perekaman surat SNP Dewas.', NULL, '{\"cluster_id\":\"1\",\"sub_cluster_id\":\"1\",\"nomor_surat\":\"B\\/09\\/052026\",\"tanggal_surat\":\"2026-07-08T17:00:00.000000Z\",\"perihal_surat\":\"tes\",\"dokumen\":null,\"dokumen_memo\":null,\"status\":\"draft\",\"created_by\":1,\"updated_by\":1,\"id_snp\":\"B\\/09\\/052026-SNP\",\"jth_tempo\":\"2026-07-28T17:00:00.000000Z\",\"updated_at\":\"2026-07-09T07:39:07.000000Z\",\"created_at\":\"2026-07-09T07:39:07.000000Z\",\"id\":2}', '180.245.186.206', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-09 07:39:07', '2026-07-09 07:39:07'),
(45, 1, 'snp', 'sidewas_snp', 'tb_record', 'B/09/052026-SNP', 'delete', 'Super Admin menghapus perekaman SNP secara langsung.', '{\"id\":2,\"id_snp\":\"B\\/09\\/052026-SNP\",\"cluster_id\":\"1\",\"sub_cluster_id\":\"1\",\"nomor_surat\":\"B\\/09\\/052026\",\"tanggal_surat\":\"2026-07-08T17:00:00.000000Z\",\"perihal_surat\":\"tes\",\"dokumen\":null,\"dokumen_memo\":null,\"jth_tempo\":\"2026-07-28T17:00:00.000000Z\",\"status\":\"draft\",\"created_by\":\"1\",\"updated_by\":\"1\",\"created_at\":\"2026-07-09T07:39:07.000000Z\",\"updated_at\":\"2026-07-09T07:39:07.000000Z\",\"butir_snp\":[],\"cluster\":{\"id\":1,\"nama_cluster\":\"Perencanaan Strategis dan Kinerja Badan\",\"keterangan\":null,\"created_at\":\"2026-06-30T07:41:50.000000Z\",\"updated_at\":\"2026-06-30T07:41:50.000000Z\"},\"sub_cluster\":{\"id\":1,\"cluster_id\":\"1\",\"nama_sub_cluster\":\"Perencanaan Strategis\",\"keterangan\":null,\"created_at\":\"2026-06-30T07:41:50.000000Z\",\"updated_at\":\"2026-06-30T07:41:50.000000Z\"}}', NULL, '180.245.186.206', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-09 07:44:33', '2026-07-09 07:44:33'),
(46, 1, 'snp', 'sidewas_snp', 'tb_record', 'B/09/052026-SNP', 'create', 'User membuat perekaman surat SNP Dewas.', NULL, '{\"cluster_id\":\"1\",\"sub_cluster_id\":\"1\",\"nomor_surat\":\"B\\/09\\/052026\",\"tanggal_surat\":\"2026-07-08T17:00:00.000000Z\",\"perihal_surat\":\"tes\",\"dokumen\":null,\"dokumen_memo\":null,\"status\":\"draft\",\"created_by\":1,\"updated_by\":1,\"id_snp\":\"B\\/09\\/052026-SNP\",\"jth_tempo\":\"2026-07-28T17:00:00.000000Z\",\"updated_at\":\"2026-07-09T07:45:00.000000Z\",\"created_at\":\"2026-07-09T07:45:00.000000Z\",\"id\":3}', '180.245.186.206', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-09 07:45:00', '2026-07-09 07:45:00'),
(47, 1, 'snp', 'sidewas_snp', 'tb_record', 'B/2893/052026-SNP', 'create', 'User membuat perekaman surat SNP Dewas.', NULL, '{\"cluster_id\":\"1\",\"sub_cluster_id\":\"1\",\"nomor_surat\":\"B\\/2893\\/052026\",\"tanggal_surat\":\"2026-07-08T17:00:00.000000Z\",\"perihal_surat\":\"tes\",\"dokumen\":\"dokumen\\/record-snp\\/wSghKsKpfcTGxTu2Hh6u7NwaEcMPJhSo2dI9pA7S.pdf\",\"dokumen_memo\":null,\"status\":\"draft\",\"created_by\":1,\"updated_by\":1,\"id_snp\":\"B\\/2893\\/052026-SNP\",\"jth_tempo\":\"2026-07-28T17:00:00.000000Z\",\"updated_at\":\"2026-07-09T07:57:19.000000Z\",\"created_at\":\"2026-07-09T07:57:19.000000Z\",\"id\":4}', '114.8.203.58', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-09 07:57:19', '2026-07-09 07:57:19'),
(48, 1, 'snp', 'sidewas_snp', 'tb_record', 'B/2893/052026-SNP', 'delete', 'Super Admin menghapus perekaman SNP secara langsung.', '{\"id\":4,\"id_snp\":\"B\\/2893\\/052026-SNP\",\"cluster_id\":\"1\",\"sub_cluster_id\":\"1\",\"nomor_surat\":\"B\\/2893\\/052026\",\"tanggal_surat\":\"2026-07-08T17:00:00.000000Z\",\"perihal_surat\":\"tes\",\"dokumen\":\"dokumen\\/record-snp\\/wSghKsKpfcTGxTu2Hh6u7NwaEcMPJhSo2dI9pA7S.pdf\",\"dokumen_memo\":null,\"jth_tempo\":\"2026-07-28T17:00:00.000000Z\",\"status\":\"draft\",\"created_by\":\"1\",\"updated_by\":\"1\",\"created_at\":\"2026-07-09T07:57:19.000000Z\",\"updated_at\":\"2026-07-09T07:57:19.000000Z\",\"butir_snp\":[],\"cluster\":{\"id\":1,\"nama_cluster\":\"Perencanaan Strategis dan Kinerja Badan\",\"keterangan\":null,\"created_at\":\"2026-06-30T07:41:50.000000Z\",\"updated_at\":\"2026-06-30T07:41:50.000000Z\"},\"sub_cluster\":{\"id\":1,\"cluster_id\":\"1\",\"nama_sub_cluster\":\"Perencanaan Strategis\",\"keterangan\":null,\"created_at\":\"2026-06-30T07:41:50.000000Z\",\"updated_at\":\"2026-06-30T07:41:50.000000Z\"}}', NULL, '114.8.203.58', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-09 07:58:03', '2026-07-09 07:58:03'),
(49, 1, 'snp', 'sidewas_snp', 'tb_record', 'B/09/052026-SNP', 'delete', 'Super Admin menghapus perekaman SNP secara langsung.', '{\"id\":3,\"id_snp\":\"B\\/09\\/052026-SNP\",\"cluster_id\":\"1\",\"sub_cluster_id\":\"1\",\"nomor_surat\":\"B\\/09\\/052026\",\"tanggal_surat\":\"2026-07-08T17:00:00.000000Z\",\"perihal_surat\":\"tes\",\"dokumen\":null,\"dokumen_memo\":null,\"jth_tempo\":\"2026-07-28T17:00:00.000000Z\",\"status\":\"draft\",\"created_by\":\"1\",\"updated_by\":\"1\",\"created_at\":\"2026-07-09T07:45:00.000000Z\",\"updated_at\":\"2026-07-09T07:45:00.000000Z\",\"butir_snp\":[],\"cluster\":{\"id\":1,\"nama_cluster\":\"Perencanaan Strategis dan Kinerja Badan\",\"keterangan\":null,\"created_at\":\"2026-06-30T07:41:50.000000Z\",\"updated_at\":\"2026-06-30T07:41:50.000000Z\"},\"sub_cluster\":{\"id\":1,\"cluster_id\":\"1\",\"nama_sub_cluster\":\"Perencanaan Strategis\",\"keterangan\":null,\"created_at\":\"2026-06-30T07:41:50.000000Z\",\"updated_at\":\"2026-06-30T07:41:50.000000Z\"}}', NULL, '114.8.203.58', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-09 07:58:08', '2026-07-09 07:58:08'),
(50, 1, 'snp', 'sidewas_snp', 'tb_record', 'B/09/052026-SNP', 'create', 'User membuat perekaman surat SNP Dewas.', NULL, '{\"cluster_id\":\"5\",\"sub_cluster_id\":\"17\",\"nomor_surat\":\"B\\/09\\/052026\",\"tanggal_surat\":\"2026-07-08T17:00:00.000000Z\",\"perihal_surat\":\"tes\",\"dokumen\":\"dokumen\\/record-snp\\/NF1NqoS7dbuLLEn6xyqbOT4GrfDvHSf2LPB3Lnos.pdf\",\"dokumen_memo\":\"dokumen\\/memo-snp\\/LXyKUrEspqHRbzJ1XiXxKfbdfEMMXsH27LnV2K8h.docx\",\"status\":\"draft\",\"created_by\":1,\"updated_by\":1,\"id_snp\":\"B\\/09\\/052026-SNP\",\"jth_tempo\":\"2026-07-28T17:00:00.000000Z\",\"updated_at\":\"2026-07-09T07:58:57.000000Z\",\"created_at\":\"2026-07-09T07:58:57.000000Z\",\"id\":5}', '114.8.203.58', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-09 07:58:57', '2026-07-09 07:58:57'),
(51, 1, 'snp', 'sidewas_snp', 'tb_record', 'B/09/052026-SNP', 'delete', 'Super Admin menghapus perekaman SNP secara langsung.', '{\"id\":5,\"id_snp\":\"B\\/09\\/052026-SNP\",\"cluster_id\":\"5\",\"sub_cluster_id\":\"17\",\"nomor_surat\":\"B\\/09\\/052026\",\"tanggal_surat\":\"2026-07-08T17:00:00.000000Z\",\"perihal_surat\":\"tes\",\"dokumen\":\"dokumen\\/record-snp\\/NF1NqoS7dbuLLEn6xyqbOT4GrfDvHSf2LPB3Lnos.pdf\",\"dokumen_memo\":\"dokumen\\/memo-snp\\/LXyKUrEspqHRbzJ1XiXxKfbdfEMMXsH27LnV2K8h.docx\",\"jth_tempo\":\"2026-07-28T17:00:00.000000Z\",\"status\":\"draft\",\"created_by\":\"1\",\"updated_by\":\"1\",\"created_at\":\"2026-07-09T07:58:57.000000Z\",\"updated_at\":\"2026-07-09T07:58:57.000000Z\",\"butir_snp\":[],\"cluster\":{\"id\":5,\"nama_cluster\":\"Manajemen Risiko dan Aktuaria\",\"keterangan\":null,\"created_at\":\"2026-06-30T07:41:50.000000Z\",\"updated_at\":\"2026-06-30T07:41:50.000000Z\"},\"sub_cluster\":{\"id\":17,\"cluster_id\":\"5\",\"nama_sub_cluster\":\"Manajemen Risiko\",\"keterangan\":null,\"created_at\":\"2026-06-30T07:41:50.000000Z\",\"updated_at\":\"2026-06-30T07:41:50.000000Z\"}}', NULL, '114.8.203.58', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-09 08:07:03', '2026-07-09 08:07:03'),
(52, 2, 'snp', 'sidewas_snp', 'tb_record', 'B/169/062026-SNP', 'create', 'User membuat perekaman surat SNP Dewas.', NULL, '{\"cluster_id\":\"9\",\"sub_cluster_id\":\"28\",\"nomor_surat\":\"B\\/169\\/062026\",\"tanggal_surat\":\"2026-06-29T17:00:00.000000Z\",\"perihal_surat\":\"Pending Klaim JKK-PLKK di Kantor Cabang\",\"dokumen\":\"dokumen\\/record-snp\\/OyIlhPLVVgfgMWMr6co2nz239za0BGG1D260nPAX.pdf\",\"dokumen_memo\":\"dokumen\\/memo-snp\\/zAdOjVvfUZnhWzYwQAioZFSvHz22LIT0vcNpDb6G.pdf\",\"status\":\"draft\",\"created_by\":2,\"updated_by\":2,\"id_snp\":\"B\\/169\\/062026-SNP\",\"jth_tempo\":\"2026-07-19T17:00:00.000000Z\",\"updated_at\":\"2026-07-09T08:16:30.000000Z\",\"created_at\":\"2026-07-09T08:16:30.000000Z\",\"id\":6}', '103.168.122.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-09 08:16:30', '2026-07-09 08:16:30'),
(53, 2, 'snp', 'sidewas_snp', 'tb_butir_snp', 'B/169/062026-SNP.01', 'create', 'User menambahkan butir SNP pada surat B/169/062026-SNP.', NULL, '{\"record\":{\"id\":6,\"id_snp\":\"B\\/169\\/062026-SNP\",\"cluster_id\":\"9\",\"sub_cluster_id\":\"28\",\"nomor_surat\":\"B\\/169\\/062026\",\"tanggal_surat\":\"2026-06-29T17:00:00.000000Z\",\"perihal_surat\":\"Pending Klaim JKK-PLKK di Kantor Cabang\",\"dokumen\":\"dokumen\\/record-snp\\/OyIlhPLVVgfgMWMr6co2nz239za0BGG1D260nPAX.pdf\",\"dokumen_memo\":\"dokumen\\/memo-snp\\/zAdOjVvfUZnhWzYwQAioZFSvHz22LIT0vcNpDb6G.pdf\",\"jth_tempo\":\"2026-07-19T17:00:00.000000Z\",\"status\":\"dalam_proses\",\"created_by\":\"2\",\"updated_by\":\"2\",\"created_at\":\"2026-07-09T08:16:30.000000Z\",\"updated_at\":\"2026-07-09T08:22:10.000000Z\"},\"butir\":{\"id_snp\":\"B\\/169\\/062026-SNP\",\"butir_snp\":\"Melakukan percepatan penyelesaian klaim pending JKK-PLKK secara tuntas sesuai ketentuan paling lambat 31 Desember 2026\",\"status\":\"terbit\",\"created_by\":2,\"updated_by\":2,\"id_butir_snp\":\"B\\/169\\/062026-SNP.01\",\"updated_at\":\"2026-07-09T08:22:10.000000Z\",\"created_at\":\"2026-07-09T08:22:10.000000Z\",\"id\":1},\"input\":{\"butir_snp\":\"Melakukan percepatan penyelesaian klaim pending JKK-PLKK secara tuntas sesuai ketentuan paling lambat 31 Desember 2026\",\"unit_kerja_utama_id\":\"9\",\"komite_id\":\"3\"}}', '103.168.122.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-09 08:22:10', '2026-07-09 08:22:10'),
(54, 2, 'snp', 'sidewas_snp', 'tb_butir_snp', 'B/169/062026-SNP.02', 'create', 'User menambahkan butir SNP pada surat B/169/062026-SNP.', NULL, '{\"record\":{\"id\":6,\"id_snp\":\"B\\/169\\/062026-SNP\",\"cluster_id\":\"9\",\"sub_cluster_id\":\"28\",\"nomor_surat\":\"B\\/169\\/062026\",\"tanggal_surat\":\"2026-06-29T17:00:00.000000Z\",\"perihal_surat\":\"Pending Klaim JKK-PLKK di Kantor Cabang\",\"dokumen\":\"dokumen\\/record-snp\\/OyIlhPLVVgfgMWMr6co2nz239za0BGG1D260nPAX.pdf\",\"dokumen_memo\":\"dokumen\\/memo-snp\\/zAdOjVvfUZnhWzYwQAioZFSvHz22LIT0vcNpDb6G.pdf\",\"jth_tempo\":\"2026-07-19T17:00:00.000000Z\",\"status\":\"dalam_proses\",\"created_by\":\"2\",\"updated_by\":\"2\",\"created_at\":\"2026-07-09T08:16:30.000000Z\",\"updated_at\":\"2026-07-09T08:22:10.000000Z\"},\"butir\":{\"id_snp\":\"B\\/169\\/062026-SNP\",\"butir_snp\":\"Melakukan kajian atas akar masalah dan alternatif solusi terjadinya klaim pending JKK-PLKK di tingkat Kantor Cabang agar tidak terjadi secara berulang\",\"status\":\"terbit\",\"created_by\":2,\"updated_by\":2,\"id_butir_snp\":\"B\\/169\\/062026-SNP.02\",\"updated_at\":\"2026-07-09T08:23:14.000000Z\",\"created_at\":\"2026-07-09T08:23:14.000000Z\",\"id\":2},\"input\":{\"butir_snp\":\"Melakukan kajian atas akar masalah dan alternatif solusi terjadinya klaim pending JKK-PLKK di tingkat Kantor Cabang agar tidak terjadi secara berulang\",\"unit_kerja_utama_id\":\"9\",\"komite_id\":\"3\"}}', '103.168.122.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-09 08:23:14', '2026-07-09 08:23:14'),
(55, 2, 'snp', 'sidewas_snp', 'tb_butir_snp', 'B/169/062026-SNP.03', 'create', 'User menambahkan butir SNP pada surat B/169/062026-SNP.', NULL, '{\"record\":{\"id\":6,\"id_snp\":\"B\\/169\\/062026-SNP\",\"cluster_id\":\"9\",\"sub_cluster_id\":\"28\",\"nomor_surat\":\"B\\/169\\/062026\",\"tanggal_surat\":\"2026-06-29T17:00:00.000000Z\",\"perihal_surat\":\"Pending Klaim JKK-PLKK di Kantor Cabang\",\"dokumen\":\"dokumen\\/record-snp\\/OyIlhPLVVgfgMWMr6co2nz239za0BGG1D260nPAX.pdf\",\"dokumen_memo\":\"dokumen\\/memo-snp\\/zAdOjVvfUZnhWzYwQAioZFSvHz22LIT0vcNpDb6G.pdf\",\"jth_tempo\":\"2026-07-19T17:00:00.000000Z\",\"status\":\"dalam_proses\",\"created_by\":\"2\",\"updated_by\":\"2\",\"created_at\":\"2026-07-09T08:16:30.000000Z\",\"updated_at\":\"2026-07-09T08:22:10.000000Z\"},\"butir\":{\"id_snp\":\"B\\/169\\/062026-SNP\",\"butir_snp\":\"Mengembangkan dashboard pemantauan status klaim JKK-PLKK secara online dan real-time sebagai salah satu alat pengendalian internal operasional layanan\",\"status\":\"terbit\",\"created_by\":2,\"updated_by\":2,\"id_butir_snp\":\"B\\/169\\/062026-SNP.03\",\"updated_at\":\"2026-07-09T08:24:13.000000Z\",\"created_at\":\"2026-07-09T08:24:13.000000Z\",\"id\":3},\"input\":{\"butir_snp\":\"Mengembangkan dashboard pemantauan status klaim JKK-PLKK secara online dan real-time sebagai salah satu alat pengendalian internal operasional layanan\",\"unit_kerja_utama_id\":\"9\",\"komite_id\":\"3\"}}', '103.168.122.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-09 08:24:13', '2026-07-09 08:24:13'),
(56, 2, 'snp', 'sidewas_snp', 'tb_butir_snp', 'B/169/062026-SNP.04', 'create', 'User menambahkan butir SNP pada surat B/169/062026-SNP.', NULL, '{\"record\":{\"id\":6,\"id_snp\":\"B\\/169\\/062026-SNP\",\"cluster_id\":\"9\",\"sub_cluster_id\":\"28\",\"nomor_surat\":\"B\\/169\\/062026\",\"tanggal_surat\":\"2026-06-29T17:00:00.000000Z\",\"perihal_surat\":\"Pending Klaim JKK-PLKK di Kantor Cabang\",\"dokumen\":\"dokumen\\/record-snp\\/OyIlhPLVVgfgMWMr6co2nz239za0BGG1D260nPAX.pdf\",\"dokumen_memo\":\"dokumen\\/memo-snp\\/zAdOjVvfUZnhWzYwQAioZFSvHz22LIT0vcNpDb6G.pdf\",\"jth_tempo\":\"2026-07-19T17:00:00.000000Z\",\"status\":\"dalam_proses\",\"created_by\":\"2\",\"updated_by\":\"2\",\"created_at\":\"2026-07-09T08:16:30.000000Z\",\"updated_at\":\"2026-07-09T08:22:10.000000Z\"},\"butir\":{\"id_snp\":\"B\\/169\\/062026-SNP\",\"butir_snp\":\"Menyusun tata kelola\\/petunjuk teknis pemanfaatan dashboard klaim pending JKK-PLKK, antara lain meliputi langkah penanganan dan pihak yang bertanggungjawab\",\"status\":\"terbit\",\"created_by\":2,\"updated_by\":2,\"id_butir_snp\":\"B\\/169\\/062026-SNP.04\",\"updated_at\":\"2026-07-09T08:25:16.000000Z\",\"created_at\":\"2026-07-09T08:25:16.000000Z\",\"id\":4},\"input\":{\"butir_snp\":\"Menyusun tata kelola\\/petunjuk teknis pemanfaatan dashboard klaim pending JKK-PLKK, antara lain meliputi langkah penanganan dan pihak yang bertanggungjawab\",\"unit_kerja_utama_id\":\"9\",\"komite_id\":\"3\"}}', '103.168.122.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-09 08:25:16', '2026-07-09 08:25:16'),
(57, 2, 'snp', 'sidewas_snp', 'tb_butir_snp', 'B/169/062026-SNP.05', 'create', 'User menambahkan butir SNP pada surat B/169/062026-SNP.', NULL, '{\"record\":{\"id\":6,\"id_snp\":\"B\\/169\\/062026-SNP\",\"cluster_id\":\"9\",\"sub_cluster_id\":\"28\",\"nomor_surat\":\"B\\/169\\/062026\",\"tanggal_surat\":\"2026-06-29T17:00:00.000000Z\",\"perihal_surat\":\"Pending Klaim JKK-PLKK di Kantor Cabang\",\"dokumen\":\"dokumen\\/record-snp\\/OyIlhPLVVgfgMWMr6co2nz239za0BGG1D260nPAX.pdf\",\"dokumen_memo\":\"dokumen\\/memo-snp\\/zAdOjVvfUZnhWzYwQAioZFSvHz22LIT0vcNpDb6G.pdf\",\"jth_tempo\":\"2026-07-19T17:00:00.000000Z\",\"status\":\"dalam_proses\",\"created_by\":\"2\",\"updated_by\":\"2\",\"created_at\":\"2026-07-09T08:16:30.000000Z\",\"updated_at\":\"2026-07-09T08:22:10.000000Z\"},\"butir\":{\"id_snp\":\"B\\/169\\/062026-SNP\",\"butir_snp\":\"Melakukan percepatan penyelesaian atas sisa tindak lanjut SNP Dewan Pengawas yang disampaikan sebelumnya melalui surat nomor: 773\\/DP\\/112023\",\"status\":\"terbit\",\"created_by\":2,\"updated_by\":2,\"id_butir_snp\":\"B\\/169\\/062026-SNP.05\",\"updated_at\":\"2026-07-09T08:28:07.000000Z\",\"created_at\":\"2026-07-09T08:28:07.000000Z\",\"id\":5},\"input\":{\"butir_snp\":\"Melakukan percepatan penyelesaian atas sisa tindak lanjut SNP Dewan Pengawas yang disampaikan sebelumnya melalui surat nomor: 773\\/DP\\/112023\",\"unit_kerja_utama_id\":\"9\",\"unit_kerja_pendukung_id\":[\"8\",\"20\",\"19\"],\"komite_id\":\"3\"}}', '103.168.122.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-09 08:28:07', '2026-07-09 08:28:07'),
(58, 2, 'snp', 'sidewas_snp', 'tb_record', 'B/170/062026-SNP', 'create', 'User membuat perekaman surat SNP Dewas.', NULL, '{\"cluster_id\":\"9\",\"sub_cluster_id\":\"28\",\"nomor_surat\":\"B\\/170\\/062026\",\"tanggal_surat\":\"2026-06-29T17:00:00.000000Z\",\"perihal_surat\":\"Evaluasi atas Implementasi Segregation of Duties pada Proses Klaim di Kantor Cabang\",\"dokumen\":\"dokumen\\/record-snp\\/lMtQlG0deNvulP9GvmgvaCl4I0Mj1QabaqW84KX0.pdf\",\"dokumen_memo\":\"dokumen\\/memo-snp\\/1fVR03ZQ9tqEZlqRrV781rkAXuC4pOYbGHPi0F3i.pdf\",\"status\":\"draft\",\"created_by\":2,\"updated_by\":2,\"id_snp\":\"B\\/170\\/062026-SNP\",\"jth_tempo\":\"2026-07-19T17:00:00.000000Z\",\"updated_at\":\"2026-07-09T08:29:51.000000Z\",\"created_at\":\"2026-07-09T08:29:51.000000Z\",\"id\":7}', '103.168.122.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-09 08:29:51', '2026-07-09 08:29:51'),
(59, 2, 'snp', 'sidewas_snp', 'tb_butir_snp', 'B/170/062026-SNP.01', 'create', 'User menambahkan butir SNP pada surat B/170/062026-SNP.', NULL, '{\"record\":{\"id\":7,\"id_snp\":\"B\\/170\\/062026-SNP\",\"cluster_id\":\"9\",\"sub_cluster_id\":\"28\",\"nomor_surat\":\"B\\/170\\/062026\",\"tanggal_surat\":\"2026-06-29T17:00:00.000000Z\",\"perihal_surat\":\"Evaluasi atas Implementasi Segregation of Duties pada Proses Klaim di Kantor Cabang\",\"dokumen\":\"dokumen\\/record-snp\\/lMtQlG0deNvulP9GvmgvaCl4I0Mj1QabaqW84KX0.pdf\",\"dokumen_memo\":\"dokumen\\/memo-snp\\/1fVR03ZQ9tqEZlqRrV781rkAXuC4pOYbGHPi0F3i.pdf\",\"jth_tempo\":\"2026-07-19T17:00:00.000000Z\",\"status\":\"dalam_proses\",\"created_by\":\"2\",\"updated_by\":\"2\",\"created_at\":\"2026-07-09T08:29:51.000000Z\",\"updated_at\":\"2026-07-09T08:32:32.000000Z\"},\"butir\":{\"id_snp\":\"B\\/170\\/062026-SNP\",\"butir_snp\":\"Melakukan evaluasi dan penyempurnaan proses bisnis penyelesaian klaim, antara lain:\\r\\n1). Mengedepankan optimalisasi penggunaan IT dan AI proses verifikasi klaim, baik profil peserta maupun bukti-bukti pendukung.\\r\\n2). Meningkatkan pengendalian dengan menambahkan IT Control.\",\"status\":\"terbit\",\"created_by\":2,\"updated_by\":2,\"id_butir_snp\":\"B\\/170\\/062026-SNP.01\",\"updated_at\":\"2026-07-09T08:32:32.000000Z\",\"created_at\":\"2026-07-09T08:32:32.000000Z\",\"id\":6},\"input\":{\"butir_snp\":\"Melakukan evaluasi dan penyempurnaan proses bisnis penyelesaian klaim, antara lain:\\r\\n1). Mengedepankan optimalisasi penggunaan IT dan AI proses verifikasi klaim, baik profil peserta maupun bukti-bukti pendukung.\\r\\n2). Meningkatkan pengendalian dengan menambahkan IT Control.\",\"unit_kerja_utama_id\":\"9\",\"komite_id\":\"3\"}}', '103.168.122.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-09 08:32:32', '2026-07-09 08:32:32'),
(60, 2, 'snp', 'sidewas_snp', 'tb_butir_snp', 'B/170/062026-SNP.02', 'create', 'User menambahkan butir SNP pada surat B/170/062026-SNP.', NULL, '{\"record\":{\"id\":7,\"id_snp\":\"B\\/170\\/062026-SNP\",\"cluster_id\":\"9\",\"sub_cluster_id\":\"28\",\"nomor_surat\":\"B\\/170\\/062026\",\"tanggal_surat\":\"2026-06-29T17:00:00.000000Z\",\"perihal_surat\":\"Evaluasi atas Implementasi Segregation of Duties pada Proses Klaim di Kantor Cabang\",\"dokumen\":\"dokumen\\/record-snp\\/lMtQlG0deNvulP9GvmgvaCl4I0Mj1QabaqW84KX0.pdf\",\"dokumen_memo\":\"dokumen\\/memo-snp\\/1fVR03ZQ9tqEZlqRrV781rkAXuC4pOYbGHPi0F3i.pdf\",\"jth_tempo\":\"2026-07-19T17:00:00.000000Z\",\"status\":\"dalam_proses\",\"created_by\":\"2\",\"updated_by\":\"2\",\"created_at\":\"2026-07-09T08:29:51.000000Z\",\"updated_at\":\"2026-07-09T08:32:32.000000Z\"},\"butir\":{\"id_snp\":\"B\\/170\\/062026-SNP\",\"butir_snp\":\"Melakukan kalibrasi atas workload analysis dan kecukupan SDM untuk memastikan kesesuaian jumlah SDM dan beban kerja.\",\"status\":\"terbit\",\"created_by\":2,\"updated_by\":2,\"id_butir_snp\":\"B\\/170\\/062026-SNP.02\",\"updated_at\":\"2026-07-09T08:33:21.000000Z\",\"created_at\":\"2026-07-09T08:33:21.000000Z\",\"id\":7},\"input\":{\"butir_snp\":\"Melakukan kalibrasi atas workload analysis dan kecukupan SDM untuk memastikan kesesuaian jumlah SDM dan beban kerja.\",\"unit_kerja_utama_id\":\"9\",\"komite_id\":\"3\"}}', '103.168.122.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-09 08:33:21', '2026-07-09 08:33:21'),
(61, 2, 'snp', 'sidewas_snp', 'tb_butir_snp', 'B/170/062026-SNP.03', 'create', 'User menambahkan butir SNP pada surat B/170/062026-SNP.', NULL, '{\"record\":{\"id\":7,\"id_snp\":\"B\\/170\\/062026-SNP\",\"cluster_id\":\"9\",\"sub_cluster_id\":\"28\",\"nomor_surat\":\"B\\/170\\/062026\",\"tanggal_surat\":\"2026-06-29T17:00:00.000000Z\",\"perihal_surat\":\"Evaluasi atas Implementasi Segregation of Duties pada Proses Klaim di Kantor Cabang\",\"dokumen\":\"dokumen\\/record-snp\\/lMtQlG0deNvulP9GvmgvaCl4I0Mj1QabaqW84KX0.pdf\",\"dokumen_memo\":\"dokumen\\/memo-snp\\/1fVR03ZQ9tqEZlqRrV781rkAXuC4pOYbGHPi0F3i.pdf\",\"jth_tempo\":\"2026-07-19T17:00:00.000000Z\",\"status\":\"dalam_proses\",\"created_by\":\"2\",\"updated_by\":\"2\",\"created_at\":\"2026-07-09T08:29:51.000000Z\",\"updated_at\":\"2026-07-09T08:32:32.000000Z\"},\"butir\":{\"id_snp\":\"B\\/170\\/062026-SNP\",\"butir_snp\":\"Dalam hal diperlukan, melakukan penataan organisasi sesuai dengan hasil evaluasi atas proses bisnis dan perhitungan workload analysis\",\"status\":\"terbit\",\"created_by\":2,\"updated_by\":2,\"id_butir_snp\":\"B\\/170\\/062026-SNP.03\",\"updated_at\":\"2026-07-09T08:34:13.000000Z\",\"created_at\":\"2026-07-09T08:34:13.000000Z\",\"id\":8},\"input\":{\"butir_snp\":\"Dalam hal diperlukan, melakukan penataan organisasi sesuai dengan hasil evaluasi atas proses bisnis dan perhitungan workload analysis\",\"unit_kerja_utama_id\":\"9\",\"komite_id\":\"3\"}}', '103.168.122.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-09 08:34:13', '2026-07-09 08:34:13');
INSERT INTO `tb_log_activity` (`id`, `user_id`, `type_code`, `database_name`, `table_name`, `record_key`, `action`, `description`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`, `updated_at`) VALUES
(62, 2, 'snp', 'sidewas_snp', 'tb_record', 'B/171/062026-SNP', 'create', 'User membuat perekaman surat SNP Dewas.', NULL, '{\"cluster_id\":\"10\",\"sub_cluster_id\":\"32\",\"nomor_surat\":\"B\\/171\\/062026\",\"tanggal_surat\":\"2026-06-29T17:00:00.000000Z\",\"perihal_surat\":\"Evaluasi Rencana Kerja Anggaran Tahunan (RKAT)\",\"dokumen\":\"dokumen\\/record-snp\\/zRUfGSyP8KKr3J1A7AsfFTgfZukzTMKfxqN2VcPP.pdf\",\"dokumen_memo\":\"dokumen\\/memo-snp\\/WUQ93RSZdnViHszu3fOC92nGLp8nKGxby5mLNK57.pdf\",\"status\":\"draft\",\"created_by\":2,\"updated_by\":2,\"id_snp\":\"B\\/171\\/062026-SNP\",\"jth_tempo\":\"2026-07-19T17:00:00.000000Z\",\"updated_at\":\"2026-07-09T08:35:46.000000Z\",\"created_at\":\"2026-07-09T08:35:46.000000Z\",\"id\":8}', '103.168.122.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-09 08:35:46', '2026-07-09 08:35:46'),
(63, 2, 'snp', 'sidewas_snp', 'tb_butir_snp', 'B/171/062026-SNP.01', 'create', 'User menambahkan butir SNP pada surat B/171/062026-SNP.', NULL, '{\"record\":{\"id\":8,\"id_snp\":\"B\\/171\\/062026-SNP\",\"cluster_id\":\"10\",\"sub_cluster_id\":\"32\",\"nomor_surat\":\"B\\/171\\/062026\",\"tanggal_surat\":\"2026-06-29T17:00:00.000000Z\",\"perihal_surat\":\"Evaluasi Rencana Kerja Anggaran Tahunan (RKAT)\",\"dokumen\":\"dokumen\\/record-snp\\/zRUfGSyP8KKr3J1A7AsfFTgfZukzTMKfxqN2VcPP.pdf\",\"dokumen_memo\":\"dokumen\\/memo-snp\\/WUQ93RSZdnViHszu3fOC92nGLp8nKGxby5mLNK57.pdf\",\"jth_tempo\":\"2026-07-19T17:00:00.000000Z\",\"status\":\"dalam_proses\",\"created_by\":\"2\",\"updated_by\":\"2\",\"created_at\":\"2026-07-09T08:35:46.000000Z\",\"updated_at\":\"2026-07-09T08:38:08.000000Z\"},\"butir\":{\"id_snp\":\"B\\/171\\/062026-SNP\",\"butir_snp\":\"Melakukan evaluasi atas implementasi RKAT 2026 meliputi:\\r\\n1). Realisasi anggaran.\\r\\n2). Realisasi program kerja dan kegiatan.\\r\\n3). Capaian output program kerja dan kegiatan, dan,\\r\\n4). Keterkaitan antara realisasi anggaran, program kerja dan kegiatan dengan pencapaian ICK Badan.\",\"status\":\"terbit\",\"created_by\":2,\"updated_by\":2,\"id_butir_snp\":\"B\\/171\\/062026-SNP.01\",\"updated_at\":\"2026-07-09T08:38:08.000000Z\",\"created_at\":\"2026-07-09T08:38:08.000000Z\",\"id\":9},\"input\":{\"butir_snp\":\"Melakukan evaluasi atas implementasi RKAT 2026 meliputi:\\r\\n1). Realisasi anggaran.\\r\\n2). Realisasi program kerja dan kegiatan.\\r\\n3). Capaian output program kerja dan kegiatan, dan,\\r\\n4). Keterkaitan antara realisasi anggaran, program kerja dan kegiatan dengan pencapaian ICK Badan.\",\"unit_kerja_utama_id\":\"14\",\"unit_kerja_pendukung_id\":[\"20\"],\"komite_id\":\"3\"}}', '103.168.122.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-09 08:38:09', '2026-07-09 08:38:09'),
(64, 2, 'snp', 'sidewas_snp', 'tb_butir_snp', 'B/171/062026-SNP.02', 'create', 'User menambahkan butir SNP pada surat B/171/062026-SNP.', NULL, '{\"record\":{\"id\":8,\"id_snp\":\"B\\/171\\/062026-SNP\",\"cluster_id\":\"10\",\"sub_cluster_id\":\"32\",\"nomor_surat\":\"B\\/171\\/062026\",\"tanggal_surat\":\"2026-06-29T17:00:00.000000Z\",\"perihal_surat\":\"Evaluasi Rencana Kerja Anggaran Tahunan (RKAT)\",\"dokumen\":\"dokumen\\/record-snp\\/zRUfGSyP8KKr3J1A7AsfFTgfZukzTMKfxqN2VcPP.pdf\",\"dokumen_memo\":\"dokumen\\/memo-snp\\/WUQ93RSZdnViHszu3fOC92nGLp8nKGxby5mLNK57.pdf\",\"jth_tempo\":\"2026-07-19T17:00:00.000000Z\",\"status\":\"dalam_proses\",\"created_by\":\"2\",\"updated_by\":\"2\",\"created_at\":\"2026-07-09T08:35:46.000000Z\",\"updated_at\":\"2026-07-09T08:38:08.000000Z\"},\"butir\":{\"id_snp\":\"B\\/171\\/062026-SNP\",\"butir_snp\":\"Melakukan kajian terhadap RKAT 2026 untuk meningkatkan sinkronisasi, harmonisasi, dan simplifikasi program kerja selaras dengan fokus Direksi pada area coverage, care dan credibility.\",\"status\":\"terbit\",\"created_by\":2,\"updated_by\":2,\"id_butir_snp\":\"B\\/171\\/062026-SNP.02\",\"updated_at\":\"2026-07-09T08:39:31.000000Z\",\"created_at\":\"2026-07-09T08:39:31.000000Z\",\"id\":10},\"input\":{\"butir_snp\":\"Melakukan kajian terhadap RKAT 2026 untuk meningkatkan sinkronisasi, harmonisasi, dan simplifikasi program kerja selaras dengan fokus Direksi pada area coverage, care dan credibility.\",\"unit_kerja_utama_id\":\"14\",\"komite_id\":\"3\"}}', '103.168.122.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-09 08:39:31', '2026-07-09 08:39:31'),
(65, 2, 'snp', 'sidewas_snp', 'tb_butir_snp', 'B/171/062026-SNP.03', 'create', 'User menambahkan butir SNP pada surat B/171/062026-SNP.', NULL, '{\"record\":{\"id\":8,\"id_snp\":\"B\\/171\\/062026-SNP\",\"cluster_id\":\"10\",\"sub_cluster_id\":\"32\",\"nomor_surat\":\"B\\/171\\/062026\",\"tanggal_surat\":\"2026-06-29T17:00:00.000000Z\",\"perihal_surat\":\"Evaluasi Rencana Kerja Anggaran Tahunan (RKAT)\",\"dokumen\":\"dokumen\\/record-snp\\/zRUfGSyP8KKr3J1A7AsfFTgfZukzTMKfxqN2VcPP.pdf\",\"dokumen_memo\":\"dokumen\\/memo-snp\\/WUQ93RSZdnViHszu3fOC92nGLp8nKGxby5mLNK57.pdf\",\"jth_tempo\":\"2026-07-19T17:00:00.000000Z\",\"status\":\"dalam_proses\",\"created_by\":\"2\",\"updated_by\":\"2\",\"created_at\":\"2026-07-09T08:35:46.000000Z\",\"updated_at\":\"2026-07-09T08:38:08.000000Z\"},\"butir\":{\"id_snp\":\"B\\/171\\/062026-SNP\",\"butir_snp\":\"Pilar Strategis Coverage, Care dan Credibility dijabarkan secara lebih rinci ke dalam program kerja dan kegiatan pada RKAT, termasuk program kerja dan kegiatan yang menjadi perhatian para pemangku kepentingan eksternal, seperti kegiatan promotif dan preventif.\",\"status\":\"terbit\",\"created_by\":2,\"updated_by\":2,\"id_butir_snp\":\"B\\/171\\/062026-SNP.03\",\"updated_at\":\"2026-07-09T08:41:07.000000Z\",\"created_at\":\"2026-07-09T08:41:07.000000Z\",\"id\":11},\"input\":{\"butir_snp\":\"Pilar Strategis Coverage, Care dan Credibility dijabarkan secara lebih rinci ke dalam program kerja dan kegiatan pada RKAT, termasuk program kerja dan kegiatan yang menjadi perhatian para pemangku kepentingan eksternal, seperti kegiatan promotif dan preventif.\",\"unit_kerja_utama_id\":\"14\",\"komite_id\":\"3\"}}', '103.168.122.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-09 08:41:07', '2026-07-09 08:41:07'),
(66, 2, 'snp', 'sidewas_snp', 'tb_butir_snp', 'B/171/062026-SNP.04', 'create', 'User menambahkan butir SNP pada surat B/171/062026-SNP.', NULL, '{\"record\":{\"id\":8,\"id_snp\":\"B\\/171\\/062026-SNP\",\"cluster_id\":\"10\",\"sub_cluster_id\":\"32\",\"nomor_surat\":\"B\\/171\\/062026\",\"tanggal_surat\":\"2026-06-29T17:00:00.000000Z\",\"perihal_surat\":\"Evaluasi Rencana Kerja Anggaran Tahunan (RKAT)\",\"dokumen\":\"dokumen\\/record-snp\\/zRUfGSyP8KKr3J1A7AsfFTgfZukzTMKfxqN2VcPP.pdf\",\"dokumen_memo\":\"dokumen\\/memo-snp\\/WUQ93RSZdnViHszu3fOC92nGLp8nKGxby5mLNK57.pdf\",\"jth_tempo\":\"2026-07-19T17:00:00.000000Z\",\"status\":\"dalam_proses\",\"created_by\":\"2\",\"updated_by\":\"2\",\"created_at\":\"2026-07-09T08:35:46.000000Z\",\"updated_at\":\"2026-07-09T08:38:08.000000Z\"},\"butir\":{\"id_snp\":\"B\\/171\\/062026-SNP\",\"butir_snp\":\"Menggunakan hasil evaluasi dan kajian sebagaimana pada butir 1 dan 2 di atas, sebagai dasar dalam penyusunan RKAT Tahun 2027\",\"status\":\"terbit\",\"created_by\":2,\"updated_by\":2,\"id_butir_snp\":\"B\\/171\\/062026-SNP.04\",\"updated_at\":\"2026-07-09T08:42:05.000000Z\",\"created_at\":\"2026-07-09T08:42:05.000000Z\",\"id\":12},\"input\":{\"butir_snp\":\"Menggunakan hasil evaluasi dan kajian sebagaimana pada butir 1 dan 2 di atas, sebagai dasar dalam penyusunan RKAT Tahun 2027\",\"unit_kerja_utama_id\":\"14\",\"komite_id\":\"3\"}}', '103.168.122.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-09 08:42:05', '2026-07-09 08:42:05'),
(67, 2, 'snp', 'sidewas_snp', 'tb_butir_snp', 'B/171/062026-SNP.05', 'create', 'User menambahkan butir SNP pada surat B/171/062026-SNP.', NULL, '{\"record\":{\"id\":8,\"id_snp\":\"B\\/171\\/062026-SNP\",\"cluster_id\":\"10\",\"sub_cluster_id\":\"32\",\"nomor_surat\":\"B\\/171\\/062026\",\"tanggal_surat\":\"2026-06-29T17:00:00.000000Z\",\"perihal_surat\":\"Evaluasi Rencana Kerja Anggaran Tahunan (RKAT)\",\"dokumen\":\"dokumen\\/record-snp\\/zRUfGSyP8KKr3J1A7AsfFTgfZukzTMKfxqN2VcPP.pdf\",\"dokumen_memo\":\"dokumen\\/memo-snp\\/WUQ93RSZdnViHszu3fOC92nGLp8nKGxby5mLNK57.pdf\",\"jth_tempo\":\"2026-07-19T17:00:00.000000Z\",\"status\":\"dalam_proses\",\"created_by\":\"2\",\"updated_by\":\"2\",\"created_at\":\"2026-07-09T08:35:46.000000Z\",\"updated_at\":\"2026-07-09T08:38:08.000000Z\"},\"butir\":{\"id_snp\":\"B\\/171\\/062026-SNP\",\"butir_snp\":\"Menyampaikan hasil evaluasi dan hasil kajian sebagaimana pada butir 1 dan butir 2 kepada Dewan Pengawas\",\"status\":\"terbit\",\"created_by\":2,\"updated_by\":2,\"id_butir_snp\":\"B\\/171\\/062026-SNP.05\",\"updated_at\":\"2026-07-09T08:42:47.000000Z\",\"created_at\":\"2026-07-09T08:42:47.000000Z\",\"id\":13},\"input\":{\"butir_snp\":\"Menyampaikan hasil evaluasi dan hasil kajian sebagaimana pada butir 1 dan butir 2 kepada Dewan Pengawas\",\"unit_kerja_utama_id\":\"14\",\"komite_id\":\"3\"}}', '103.168.122.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-09 08:42:47', '2026-07-09 08:42:47'),
(68, 8, 'produk_hukum', 'sidewas_produk_hukum', 'tb_produk_hukum', '1', 'create', 'User menambah Produk Hukum.', NULL, '{\"kode_produk_hukum\":\"PH-2011-0001\",\"judul\":\"Badan Penyelenggara Jaminan Sosial\",\"nomor_peraturan_keputusan\":\"24\",\"tahun_peraturan\":\"2011\",\"jenis_bentuk_peraturan\":\"Undang-undang\",\"singkatan_peraturan\":\"UU\",\"tanggal_penetapan\":\"2011-06-16T17:00:00.000000Z\",\"tanggal_diundangkan\":\"2021-08-16T17:00:00.000000Z\",\"sumber_ln_tbn\":null,\"sumber_tln_tbn\":null,\"subjek\":null,\"bidang_pengaturan\":null,\"abstrak\":null,\"keterangan\":null,\"muatan_substansial\":null,\"status_peraturan\":\"berlaku\",\"sifat_dokumen\":\"publik\",\"created_by\":8,\"updated_by\":8,\"updated_at\":\"2026-07-17T10:44:17.000000Z\",\"created_at\":\"2026-07-17T10:44:17.000000Z\",\"id\":1,\"files\":[{\"id\":1,\"produk_hukum_id\":\"1\",\"bentuk_file\":\"link\",\"nama_file\":\"UU 24 Tahun 2011\",\"path_file\":null,\"link_file\":\"https:\\/\\/drive.google.com\\/file\\/d\\/1NNJBPXrkCGNZTB7i4OPv2k8RFUh3Cku5\\/view?usp=sharing\",\"mime_type\":null,\"ukuran_file\":null,\"jenis_file\":\"Batang Tubuh\",\"created_by\":\"8\",\"updated_by\":\"8\",\"created_at\":\"2026-07-17T10:44:17.000000Z\",\"updated_at\":\"2026-07-17T10:44:17.000000Z\"}],\"relasis\":[]}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-17 10:44:17', '2026-07-17 10:44:17'),
(69, 1, 'snp', 'sidewas_snp', 'tb_record', 'B/171/062026-SNP', 'update', 'User memperbarui perekaman SNP.', '{\"id\":8,\"id_snp\":\"B\\/171\\/062026-SNP\",\"cluster_id\":10,\"sub_cluster_id\":32,\"nomor_surat\":\"B\\/171\\/062026\",\"tanggal_surat\":\"2026-06-29T17:00:00.000000Z\",\"perihal_surat\":\"Evaluasi Rencana Kerja Anggaran Tahunan (RKAT)\",\"dokumen\":\"dokumen\\/record-snp\\/zRUfGSyP8KKr3J1A7AsfFTgfZukzTMKfxqN2VcPP.pdf\",\"dokumen_memo\":\"dokumen\\/memo-snp\\/WUQ93RSZdnViHszu3fOC92nGLp8nKGxby5mLNK57.pdf\",\"jth_tempo\":\"2026-07-19T17:00:00.000000Z\",\"status\":\"dalam_proses\",\"created_by\":2,\"updated_by\":2,\"created_at\":\"2026-07-09T08:35:46.000000Z\",\"updated_at\":\"2026-07-09T08:38:08.000000Z\",\"cluster\":{\"id\":10,\"nama_cluster\":\"Akuntansi dan Keuangan\",\"keterangan\":null,\"created_at\":\"2026-06-30T07:41:50.000000Z\",\"updated_at\":\"2026-06-30T07:41:50.000000Z\"},\"sub_cluster\":{\"id\":32,\"cluster_id\":10,\"nama_sub_cluster\":\"Pengelolaan Keuangan\",\"keterangan\":null,\"created_at\":\"2026-06-30T07:41:50.000000Z\",\"updated_at\":\"2026-06-30T07:41:50.000000Z\"},\"butir_snp\":[{\"id\":9,\"id_butir_snp\":\"B\\/171\\/062026-SNP.01\",\"id_snp\":\"B\\/171\\/062026-SNP\",\"butir_snp\":\"Melakukan evaluasi atas implementasi RKAT 2026 meliputi:\\r\\n1). Realisasi anggaran.\\r\\n2). Realisasi program kerja dan kegiatan.\\r\\n3). Capaian output program kerja dan kegiatan, dan,\\r\\n4). Keterkaitan antara realisasi anggaran, program kerja dan kegiatan dengan pencapaian ICK Badan.\",\"status\":\"terbit\",\"created_by\":2,\"updated_by\":2,\"created_at\":\"2026-07-09T08:38:08.000000Z\",\"updated_at\":\"2026-07-09T08:38:08.000000Z\",\"butir_pics\":[{\"id\":20,\"id_butir_snp\":\"B\\/171\\/062026-SNP.01\",\"unit_kerja_id\":14,\"komite_id\":null,\"jenis_pic\":\"utama\",\"created_by\":2,\"updated_by\":2,\"created_at\":\"2026-07-09T08:38:08.000000Z\",\"updated_at\":\"2026-07-09T08:38:08.000000Z\",\"unit_kerja\":{\"id\":14,\"direktorat_id\":5,\"nama_unit\":\"Deputi Bidang Perencanaan Strategis dan Transformasi\",\"kode_unit\":\"REN\",\"keterangan\":null,\"created_at\":\"2026-06-30T07:41:51.000000Z\",\"updated_at\":\"2026-06-30T07:41:51.000000Z\"},\"komite\":null},{\"id\":21,\"id_butir_snp\":\"B\\/171\\/062026-SNP.01\",\"unit_kerja_id\":20,\"komite_id\":null,\"jenis_pic\":\"pendukung\",\"created_by\":2,\"updated_by\":2,\"created_at\":\"2026-07-09T08:38:08.000000Z\",\"updated_at\":\"2026-07-09T08:38:08.000000Z\",\"unit_kerja\":{\"id\":20,\"direktorat_id\":6,\"nama_unit\":\"Deputi Bidang Keuangan\",\"kode_unit\":\"KEU\",\"keterangan\":null,\"created_at\":\"2026-06-30T07:41:51.000000Z\",\"updated_at\":\"2026-06-30T07:41:51.000000Z\"},\"komite\":null},{\"id\":22,\"id_butir_snp\":\"B\\/171\\/062026-SNP.01\",\"unit_kerja_id\":null,\"komite_id\":3,\"jenis_pic\":\"komite\",\"created_by\":2,\"updated_by\":2,\"created_at\":\"2026-07-09T08:38:08.000000Z\",\"updated_at\":\"2026-07-09T08:38:08.000000Z\",\"unit_kerja\":null,\"komite\":{\"id\":3,\"nama_komite\":\"Komite Audit, Anggaran dan Investasi\",\"kode_komite\":\"KAAI\",\"keterangan\":null,\"created_at\":\"2026-06-30T07:41:51.000000Z\",\"updated_at\":\"2026-06-30T07:41:51.000000Z\"}}]},{\"id\":10,\"id_butir_snp\":\"B\\/171\\/062026-SNP.02\",\"id_snp\":\"B\\/171\\/062026-SNP\",\"butir_snp\":\"Melakukan kajian terhadap RKAT 2026 untuk meningkatkan sinkronisasi, harmonisasi, dan simplifikasi program kerja selaras dengan fokus Direksi pada area coverage, care dan credibility.\",\"status\":\"terbit\",\"created_by\":2,\"updated_by\":2,\"created_at\":\"2026-07-09T08:39:31.000000Z\",\"updated_at\":\"2026-07-09T08:39:31.000000Z\",\"butir_pics\":[{\"id\":23,\"id_butir_snp\":\"B\\/171\\/062026-SNP.02\",\"unit_kerja_id\":14,\"komite_id\":null,\"jenis_pic\":\"utama\",\"created_by\":2,\"updated_by\":2,\"created_at\":\"2026-07-09T08:39:31.000000Z\",\"updated_at\":\"2026-07-09T08:39:31.000000Z\",\"unit_kerja\":{\"id\":14,\"direktorat_id\":5,\"nama_unit\":\"Deputi Bidang Perencanaan Strategis dan Transformasi\",\"kode_unit\":\"REN\",\"keterangan\":null,\"created_at\":\"2026-06-30T07:41:51.000000Z\",\"updated_at\":\"2026-06-30T07:41:51.000000Z\"},\"komite\":null},{\"id\":24,\"id_butir_snp\":\"B\\/171\\/062026-SNP.02\",\"unit_kerja_id\":null,\"komite_id\":3,\"jenis_pic\":\"komite\",\"created_by\":2,\"updated_by\":2,\"created_at\":\"2026-07-09T08:39:31.000000Z\",\"updated_at\":\"2026-07-09T08:39:31.000000Z\",\"unit_kerja\":null,\"komite\":{\"id\":3,\"nama_komite\":\"Komite Audit, Anggaran dan Investasi\",\"kode_komite\":\"KAAI\",\"keterangan\":null,\"created_at\":\"2026-06-30T07:41:51.000000Z\",\"updated_at\":\"2026-06-30T07:41:51.000000Z\"}}]},{\"id\":11,\"id_butir_snp\":\"B\\/171\\/062026-SNP.03\",\"id_snp\":\"B\\/171\\/062026-SNP\",\"butir_snp\":\"Pilar Strategis Coverage, Care dan Credibility dijabarkan secara lebih rinci ke dalam program kerja dan kegiatan pada RKAT, termasuk program kerja dan kegiatan yang menjadi perhatian para pemangku kepentingan eksternal, seperti kegiatan promotif dan preventif.\",\"status\":\"terbit\",\"created_by\":2,\"updated_by\":2,\"created_at\":\"2026-07-09T08:41:07.000000Z\",\"updated_at\":\"2026-07-09T08:41:07.000000Z\",\"butir_pics\":[{\"id\":25,\"id_butir_snp\":\"B\\/171\\/062026-SNP.03\",\"unit_kerja_id\":14,\"komite_id\":null,\"jenis_pic\":\"utama\",\"created_by\":2,\"updated_by\":2,\"created_at\":\"2026-07-09T08:41:07.000000Z\",\"updated_at\":\"2026-07-09T08:41:07.000000Z\",\"unit_kerja\":{\"id\":14,\"direktorat_id\":5,\"nama_unit\":\"Deputi Bidang Perencanaan Strategis dan Transformasi\",\"kode_unit\":\"REN\",\"keterangan\":null,\"created_at\":\"2026-06-30T07:41:51.000000Z\",\"updated_at\":\"2026-06-30T07:41:51.000000Z\"},\"komite\":null},{\"id\":26,\"id_butir_snp\":\"B\\/171\\/062026-SNP.03\",\"unit_kerja_id\":null,\"komite_id\":3,\"jenis_pic\":\"komite\",\"created_by\":2,\"updated_by\":2,\"created_at\":\"2026-07-09T08:41:07.000000Z\",\"updated_at\":\"2026-07-09T08:41:07.000000Z\",\"unit_kerja\":null,\"komite\":{\"id\":3,\"nama_komite\":\"Komite Audit, Anggaran dan Investasi\",\"kode_komite\":\"KAAI\",\"keterangan\":null,\"created_at\":\"2026-06-30T07:41:51.000000Z\",\"updated_at\":\"2026-06-30T07:41:51.000000Z\"}}]},{\"id\":12,\"id_butir_snp\":\"B\\/171\\/062026-SNP.04\",\"id_snp\":\"B\\/171\\/062026-SNP\",\"butir_snp\":\"Menggunakan hasil evaluasi dan kajian sebagaimana pada butir 1 dan 2 di atas, sebagai dasar dalam penyusunan RKAT Tahun 2027\",\"status\":\"terbit\",\"created_by\":2,\"updated_by\":2,\"created_at\":\"2026-07-09T08:42:05.000000Z\",\"updated_at\":\"2026-07-09T08:42:05.000000Z\",\"butir_pics\":[{\"id\":27,\"id_butir_snp\":\"B\\/171\\/062026-SNP.04\",\"unit_kerja_id\":14,\"komite_id\":null,\"jenis_pic\":\"utama\",\"created_by\":2,\"updated_by\":2,\"created_at\":\"2026-07-09T08:42:05.000000Z\",\"updated_at\":\"2026-07-09T08:42:05.000000Z\",\"unit_kerja\":{\"id\":14,\"direktorat_id\":5,\"nama_unit\":\"Deputi Bidang Perencanaan Strategis dan Transformasi\",\"kode_unit\":\"REN\",\"keterangan\":null,\"created_at\":\"2026-06-30T07:41:51.000000Z\",\"updated_at\":\"2026-06-30T07:41:51.000000Z\"},\"komite\":null},{\"id\":28,\"id_butir_snp\":\"B\\/171\\/062026-SNP.04\",\"unit_kerja_id\":null,\"komite_id\":3,\"jenis_pic\":\"komite\",\"created_by\":2,\"updated_by\":2,\"created_at\":\"2026-07-09T08:42:05.000000Z\",\"updated_at\":\"2026-07-09T08:42:05.000000Z\",\"unit_kerja\":null,\"komite\":{\"id\":3,\"nama_komite\":\"Komite Audit, Anggaran dan Investasi\",\"kode_komite\":\"KAAI\",\"keterangan\":null,\"created_at\":\"2026-06-30T07:41:51.000000Z\",\"updated_at\":\"2026-06-30T07:41:51.000000Z\"}}]},{\"id\":13,\"id_butir_snp\":\"B\\/171\\/062026-SNP.05\",\"id_snp\":\"B\\/171\\/062026-SNP\",\"butir_snp\":\"Menyampaikan hasil evaluasi dan hasil kajian sebagaimana pada butir 1 dan butir 2 kepada Dewan Pengawas\",\"status\":\"terbit\",\"created_by\":2,\"updated_by\":2,\"created_at\":\"2026-07-09T08:42:47.000000Z\",\"updated_at\":\"2026-07-09T08:42:47.000000Z\",\"butir_pics\":[{\"id\":29,\"id_butir_snp\":\"B\\/171\\/062026-SNP.05\",\"unit_kerja_id\":14,\"komite_id\":null,\"jenis_pic\":\"utama\",\"created_by\":2,\"updated_by\":2,\"created_at\":\"2026-07-09T08:42:47.000000Z\",\"updated_at\":\"2026-07-09T08:42:47.000000Z\",\"unit_kerja\":{\"id\":14,\"direktorat_id\":5,\"nama_unit\":\"Deputi Bidang Perencanaan Strategis dan Transformasi\",\"kode_unit\":\"REN\",\"keterangan\":null,\"created_at\":\"2026-06-30T07:41:51.000000Z\",\"updated_at\":\"2026-06-30T07:41:51.000000Z\"},\"komite\":null},{\"id\":30,\"id_butir_snp\":\"B\\/171\\/062026-SNP.05\",\"unit_kerja_id\":null,\"komite_id\":3,\"jenis_pic\":\"komite\",\"created_by\":2,\"updated_by\":2,\"created_at\":\"2026-07-09T08:42:47.000000Z\",\"updated_at\":\"2026-07-09T08:42:47.000000Z\",\"unit_kerja\":null,\"komite\":{\"id\":3,\"nama_komite\":\"Komite Audit, Anggaran dan Investasi\",\"kode_komite\":\"KAAI\",\"keterangan\":null,\"created_at\":\"2026-06-30T07:41:51.000000Z\",\"updated_at\":\"2026-06-30T07:41:51.000000Z\"}}]}]}', '{\"id\":8,\"id_snp\":\"B\\/171\\/062026-SNP\",\"cluster_id\":10,\"sub_cluster_id\":32,\"nomor_surat\":\"B\\/171\\/062026\",\"tanggal_surat\":\"2026-06-29T17:00:00.000000Z\",\"perihal_surat\":\"Evaluasi Rencana Kerja Anggaran Tahunan (RKAT)\",\"dokumen\":\"dokumen\\/record-snp\\/zRUfGSyP8KKr3J1A7AsfFTgfZukzTMKfxqN2VcPP.pdf\",\"dokumen_memo\":\"dokumen\\/memo-snp\\/WUQ93RSZdnViHszu3fOC92nGLp8nKGxby5mLNK57.pdf\",\"jth_tempo\":\"2026-07-19T17:00:00.000000Z\",\"status\":\"dalam_proses\",\"created_by\":2,\"updated_by\":1,\"created_at\":\"2026-07-09T08:35:46.000000Z\",\"updated_at\":\"2026-08-21T03:33:31.000000Z\",\"cluster\":{\"id\":10,\"nama_cluster\":\"Akuntansi dan Keuangan\",\"keterangan\":null,\"created_at\":\"2026-06-30T07:41:50.000000Z\",\"updated_at\":\"2026-06-30T07:41:50.000000Z\"},\"sub_cluster\":{\"id\":32,\"cluster_id\":10,\"nama_sub_cluster\":\"Pengelolaan Keuangan\",\"keterangan\":null,\"created_at\":\"2026-06-30T07:41:50.000000Z\",\"updated_at\":\"2026-06-30T07:41:50.000000Z\"},\"butir_snp\":[{\"id\":9,\"id_butir_snp\":\"B\\/171\\/062026-SNP.01\",\"id_snp\":\"B\\/171\\/062026-SNP\",\"butir_snp\":\"Melakukan evaluasi atas implementasi RKAT 2026 meliputi:\\r\\n1). Realisasi anggaran.\\r\\n2). Realisasi program kerja dan kegiatan.\\r\\n3). Capaian output program kerja dan kegiatan, dan,\\r\\n4). Keterkaitan antara realisasi anggaran, program kerja dan kegiatan dengan pencapaian ICK Badan.\",\"status\":\"terbit\",\"created_by\":2,\"updated_by\":2,\"created_at\":\"2026-07-09T08:38:08.000000Z\",\"updated_at\":\"2026-07-09T08:38:08.000000Z\",\"butir_pics\":[{\"id\":20,\"id_butir_snp\":\"B\\/171\\/062026-SNP.01\",\"unit_kerja_id\":14,\"komite_id\":null,\"jenis_pic\":\"utama\",\"created_by\":2,\"updated_by\":2,\"created_at\":\"2026-07-09T08:38:08.000000Z\",\"updated_at\":\"2026-07-09T08:38:08.000000Z\",\"unit_kerja\":{\"id\":14,\"direktorat_id\":5,\"nama_unit\":\"Deputi Bidang Perencanaan Strategis dan Transformasi\",\"kode_unit\":\"REN\",\"keterangan\":null,\"created_at\":\"2026-06-30T07:41:51.000000Z\",\"updated_at\":\"2026-06-30T07:41:51.000000Z\"},\"komite\":null},{\"id\":21,\"id_butir_snp\":\"B\\/171\\/062026-SNP.01\",\"unit_kerja_id\":20,\"komite_id\":null,\"jenis_pic\":\"pendukung\",\"created_by\":2,\"updated_by\":2,\"created_at\":\"2026-07-09T08:38:08.000000Z\",\"updated_at\":\"2026-07-09T08:38:08.000000Z\",\"unit_kerja\":{\"id\":20,\"direktorat_id\":6,\"nama_unit\":\"Deputi Bidang Keuangan\",\"kode_unit\":\"KEU\",\"keterangan\":null,\"created_at\":\"2026-06-30T07:41:51.000000Z\",\"updated_at\":\"2026-06-30T07:41:51.000000Z\"},\"komite\":null},{\"id\":22,\"id_butir_snp\":\"B\\/171\\/062026-SNP.01\",\"unit_kerja_id\":null,\"komite_id\":3,\"jenis_pic\":\"komite\",\"created_by\":2,\"updated_by\":2,\"created_at\":\"2026-07-09T08:38:08.000000Z\",\"updated_at\":\"2026-07-09T08:38:08.000000Z\",\"unit_kerja\":null,\"komite\":{\"id\":3,\"nama_komite\":\"Komite Audit, Anggaran dan Investasi\",\"kode_komite\":\"KAAI\",\"keterangan\":null,\"created_at\":\"2026-06-30T07:41:51.000000Z\",\"updated_at\":\"2026-06-30T07:41:51.000000Z\"}}]},{\"id\":10,\"id_butir_snp\":\"B\\/171\\/062026-SNP.02\",\"id_snp\":\"B\\/171\\/062026-SNP\",\"butir_snp\":\"Melakukan kajian terhadap RKAT 2026 untuk meningkatkan sinkronisasi, harmonisasi, dan simplifikasi program kerja selaras dengan fokus Direksi pada area coverage, care dan credibility.\",\"status\":\"terbit\",\"created_by\":2,\"updated_by\":2,\"created_at\":\"2026-07-09T08:39:31.000000Z\",\"updated_at\":\"2026-07-09T08:39:31.000000Z\",\"butir_pics\":[{\"id\":23,\"id_butir_snp\":\"B\\/171\\/062026-SNP.02\",\"unit_kerja_id\":14,\"komite_id\":null,\"jenis_pic\":\"utama\",\"created_by\":2,\"updated_by\":2,\"created_at\":\"2026-07-09T08:39:31.000000Z\",\"updated_at\":\"2026-07-09T08:39:31.000000Z\",\"unit_kerja\":{\"id\":14,\"direktorat_id\":5,\"nama_unit\":\"Deputi Bidang Perencanaan Strategis dan Transformasi\",\"kode_unit\":\"REN\",\"keterangan\":null,\"created_at\":\"2026-06-30T07:41:51.000000Z\",\"updated_at\":\"2026-06-30T07:41:51.000000Z\"},\"komite\":null},{\"id\":24,\"id_butir_snp\":\"B\\/171\\/062026-SNP.02\",\"unit_kerja_id\":null,\"komite_id\":3,\"jenis_pic\":\"komite\",\"created_by\":2,\"updated_by\":2,\"created_at\":\"2026-07-09T08:39:31.000000Z\",\"updated_at\":\"2026-07-09T08:39:31.000000Z\",\"unit_kerja\":null,\"komite\":{\"id\":3,\"nama_komite\":\"Komite Audit, Anggaran dan Investasi\",\"kode_komite\":\"KAAI\",\"keterangan\":null,\"created_at\":\"2026-06-30T07:41:51.000000Z\",\"updated_at\":\"2026-06-30T07:41:51.000000Z\"}}]},{\"id\":11,\"id_butir_snp\":\"B\\/171\\/062026-SNP.03\",\"id_snp\":\"B\\/171\\/062026-SNP\",\"butir_snp\":\"Pilar Strategis Coverage, Care dan Credibility dijabarkan secara lebih rinci ke dalam program kerja dan kegiatan pada RKAT, termasuk program kerja dan kegiatan yang menjadi perhatian para pemangku kepentingan eksternal, seperti kegiatan promotif dan preventif.\",\"status\":\"terbit\",\"created_by\":2,\"updated_by\":2,\"created_at\":\"2026-07-09T08:41:07.000000Z\",\"updated_at\":\"2026-07-09T08:41:07.000000Z\",\"butir_pics\":[{\"id\":25,\"id_butir_snp\":\"B\\/171\\/062026-SNP.03\",\"unit_kerja_id\":14,\"komite_id\":null,\"jenis_pic\":\"utama\",\"created_by\":2,\"updated_by\":2,\"created_at\":\"2026-07-09T08:41:07.000000Z\",\"updated_at\":\"2026-07-09T08:41:07.000000Z\",\"unit_kerja\":{\"id\":14,\"direktorat_id\":5,\"nama_unit\":\"Deputi Bidang Perencanaan Strategis dan Transformasi\",\"kode_unit\":\"REN\",\"keterangan\":null,\"created_at\":\"2026-06-30T07:41:51.000000Z\",\"updated_at\":\"2026-06-30T07:41:51.000000Z\"},\"komite\":null},{\"id\":26,\"id_butir_snp\":\"B\\/171\\/062026-SNP.03\",\"unit_kerja_id\":null,\"komite_id\":3,\"jenis_pic\":\"komite\",\"created_by\":2,\"updated_by\":2,\"created_at\":\"2026-07-09T08:41:07.000000Z\",\"updated_at\":\"2026-07-09T08:41:07.000000Z\",\"unit_kerja\":null,\"komite\":{\"id\":3,\"nama_komite\":\"Komite Audit, Anggaran dan Investasi\",\"kode_komite\":\"KAAI\",\"keterangan\":null,\"created_at\":\"2026-06-30T07:41:51.000000Z\",\"updated_at\":\"2026-06-30T07:41:51.000000Z\"}}]},{\"id\":12,\"id_butir_snp\":\"B\\/171\\/062026-SNP.04\",\"id_snp\":\"B\\/171\\/062026-SNP\",\"butir_snp\":\"Menggunakan hasil evaluasi dan kajian sebagaimana pada butir 1 dan 2 di atas, sebagai dasar dalam penyusunan RKAT Tahun 2027\",\"status\":\"terbit\",\"created_by\":2,\"updated_by\":1,\"created_at\":\"2026-07-09T08:42:05.000000Z\",\"updated_at\":\"2026-08-21T03:33:31.000000Z\",\"butir_pics\":[{\"id\":31,\"id_butir_snp\":\"B\\/171\\/062026-SNP.04\",\"unit_kerja_id\":14,\"komite_id\":null,\"jenis_pic\":\"utama\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-21T03:33:31.000000Z\",\"updated_at\":\"2026-08-21T03:33:31.000000Z\",\"unit_kerja\":{\"id\":14,\"direktorat_id\":5,\"nama_unit\":\"Deputi Bidang Perencanaan Strategis dan Transformasi\",\"kode_unit\":\"REN\",\"keterangan\":null,\"created_at\":\"2026-06-30T07:41:51.000000Z\",\"updated_at\":\"2026-06-30T07:41:51.000000Z\"},\"komite\":null},{\"id\":32,\"id_butir_snp\":\"B\\/171\\/062026-SNP.04\",\"unit_kerja_id\":20,\"komite_id\":null,\"jenis_pic\":\"pendukung\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-21T03:33:31.000000Z\",\"updated_at\":\"2026-08-21T03:33:31.000000Z\",\"unit_kerja\":{\"id\":20,\"direktorat_id\":6,\"nama_unit\":\"Deputi Bidang Keuangan\",\"kode_unit\":\"KEU\",\"keterangan\":null,\"created_at\":\"2026-06-30T07:41:51.000000Z\",\"updated_at\":\"2026-06-30T07:41:51.000000Z\"},\"komite\":null},{\"id\":33,\"id_butir_snp\":\"B\\/171\\/062026-SNP.04\",\"unit_kerja_id\":null,\"komite_id\":3,\"jenis_pic\":\"komite\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-21T03:33:31.000000Z\",\"updated_at\":\"2026-08-21T03:33:31.000000Z\",\"unit_kerja\":null,\"komite\":{\"id\":3,\"nama_komite\":\"Komite Audit, Anggaran dan Investasi\",\"kode_komite\":\"KAAI\",\"keterangan\":null,\"created_at\":\"2026-06-30T07:41:51.000000Z\",\"updated_at\":\"2026-06-30T07:41:51.000000Z\"}}]},{\"id\":13,\"id_butir_snp\":\"B\\/171\\/062026-SNP.05\",\"id_snp\":\"B\\/171\\/062026-SNP\",\"butir_snp\":\"Menyampaikan hasil evaluasi dan hasil kajian sebagaimana pada butir 1 dan butir 2 kepada Dewan Pengawas\",\"status\":\"terbit\",\"created_by\":2,\"updated_by\":2,\"created_at\":\"2026-07-09T08:42:47.000000Z\",\"updated_at\":\"2026-07-09T08:42:47.000000Z\",\"butir_pics\":[{\"id\":29,\"id_butir_snp\":\"B\\/171\\/062026-SNP.05\",\"unit_kerja_id\":14,\"komite_id\":null,\"jenis_pic\":\"utama\",\"created_by\":2,\"updated_by\":2,\"created_at\":\"2026-07-09T08:42:47.000000Z\",\"updated_at\":\"2026-07-09T08:42:47.000000Z\",\"unit_kerja\":{\"id\":14,\"direktorat_id\":5,\"nama_unit\":\"Deputi Bidang Perencanaan Strategis dan Transformasi\",\"kode_unit\":\"REN\",\"keterangan\":null,\"created_at\":\"2026-06-30T07:41:51.000000Z\",\"updated_at\":\"2026-06-30T07:41:51.000000Z\"},\"komite\":null},{\"id\":30,\"id_butir_snp\":\"B\\/171\\/062026-SNP.05\",\"unit_kerja_id\":null,\"komite_id\":3,\"jenis_pic\":\"komite\",\"created_by\":2,\"updated_by\":2,\"created_at\":\"2026-07-09T08:42:47.000000Z\",\"updated_at\":\"2026-07-09T08:42:47.000000Z\",\"unit_kerja\":null,\"komite\":{\"id\":3,\"nama_komite\":\"Komite Audit, Anggaran dan Investasi\",\"kode_komite\":\"KAAI\",\"keterangan\":null,\"created_at\":\"2026-06-30T07:41:51.000000Z\",\"updated_at\":\"2026-06-30T07:41:51.000000Z\"}}]}]}', '103.168.122.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-21 03:33:31', '2026-08-21 03:33:31'),
(70, 1, 'snp', 'sidewas_snp', 'tb_record', 'B/10/012026-SNP', 'create', 'User membuat perekaman surat SNP Dewas.', NULL, '{\"cluster_id\":\"9\",\"sub_cluster_id\":\"30\",\"nomor_surat\":\"B\\/10\\/012026\",\"tanggal_surat\":\"2026-01-06T17:00:00.000000Z\",\"perihal_surat\":\"Pengembangan Aplikasi Smart Investment System\",\"dokumen\":\"dokumen\\/record-snp\\/RpK2CGG1fsvQ5VhIbTPTNR6DblYKLy9RvQtxdv0t.pdf\",\"dokumen_memo\":\"dokumen\\/memo-snp\\/tNN9f2WE4fZDWs5f0SxEJBUwVrWNzvgMi1d93zOs.pdf\",\"status\":\"draft\",\"created_by\":1,\"updated_by\":1,\"id_snp\":\"B\\/10\\/012026-SNP\",\"jth_tempo\":\"2026-01-26T17:00:00.000000Z\",\"updated_at\":\"2026-08-21T04:03:01.000000Z\",\"created_at\":\"2026-08-21T04:03:01.000000Z\",\"id\":9}', '103.168.122.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-21 04:03:01', '2026-08-21 04:03:01'),
(71, 1, 'snp', 'sidewas_snp', 'tb_record', 'B/14/012026-SNP', 'create', 'User membuat perekaman surat SNP Dewas.', NULL, '{\"cluster_id\":\"5\",\"sub_cluster_id\":\"17\",\"nomor_surat\":\"B\\/14\\/012026\",\"tanggal_surat\":\"2026-01-12T17:00:00.000000Z\",\"perihal_surat\":\"Kasus Fraud Klaim Jaminan Kecelakaa Kerja (JKK)\",\"dokumen\":\"dokumen\\/record-snp\\/jY3gJsl8Cj1sUA4lGf7R6op9xEzoBDOxea4pWosA.pdf\",\"dokumen_memo\":\"dokumen\\/memo-snp\\/4nGRDtE6BnIM50wFlUqE3Tbid52BzlYc4YZVVmNS.pdf\",\"status\":\"draft\",\"created_by\":1,\"updated_by\":1,\"id_snp\":\"B\\/14\\/012026-SNP\",\"jth_tempo\":\"2026-02-01T17:00:00.000000Z\",\"updated_at\":\"2026-08-21T04:10:01.000000Z\",\"created_at\":\"2026-08-21T04:10:01.000000Z\",\"id\":10}', '103.168.122.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-21 04:10:01', '2026-08-21 04:10:01'),
(72, 1, 'snp', 'sidewas_snp', 'tb_record', 'B/34/022026-SNP', 'create', 'User membuat perekaman surat SNP Dewas.', NULL, '{\"cluster_id\":\"5\",\"sub_cluster_id\":\"18\",\"nomor_surat\":\"B\\/34\\/022026\",\"tanggal_surat\":\"2026-02-01T17:00:00.000000Z\",\"perihal_surat\":\"Pengembangan Sistem Aktuaria\",\"dokumen\":\"dokumen\\/record-snp\\/PKUfdAa1lHLv7qffEWIVa2UOR4CS0UgbsBlzCqdX.pdf\",\"dokumen_memo\":\"dokumen\\/memo-snp\\/06m145vWXkZSrsxzABDPBLlcPuMwPEUwJ33mY1KH.pdf\",\"status\":\"draft\",\"created_by\":1,\"updated_by\":1,\"id_snp\":\"B\\/34\\/022026-SNP\",\"jth_tempo\":\"2026-02-19T17:00:00.000000Z\",\"updated_at\":\"2026-08-21T04:13:51.000000Z\",\"created_at\":\"2026-08-21T04:13:51.000000Z\",\"id\":11}', '103.168.122.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-21 04:13:51', '2026-08-21 04:13:51'),
(73, 1, 'snp', 'sidewas_snp', 'tb_butir_snp', 'B/10/012026-SNP.01', 'create', 'User menambahkan butir SNP pada surat B/10/012026-SNP.', NULL, '{\"record\":{\"id\":9,\"id_snp\":\"B\\/10\\/012026-SNP\",\"cluster_id\":9,\"sub_cluster_id\":30,\"nomor_surat\":\"B\\/10\\/012026\",\"tanggal_surat\":\"2026-01-06T17:00:00.000000Z\",\"perihal_surat\":\"Pengembangan Aplikasi Smart Investment System\",\"dokumen\":\"dokumen\\/record-snp\\/RpK2CGG1fsvQ5VhIbTPTNR6DblYKLy9RvQtxdv0t.pdf\",\"dokumen_memo\":\"dokumen\\/memo-snp\\/tNN9f2WE4fZDWs5f0SxEJBUwVrWNzvgMi1d93zOs.pdf\",\"jth_tempo\":\"2026-01-26T17:00:00.000000Z\",\"status\":\"dalam_proses\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-21T04:03:01.000000Z\",\"updated_at\":\"2026-08-21T04:18:33.000000Z\"},\"butir\":{\"id_snp\":\"B\\/10\\/012026-SNP\",\"butir_snp\":\"Menetapkan standar Go-live Smart Investment System yang dilengkapi checklist baku dan terverifikasi, mencakup aspek fungsional, kualitas, keamanan, serta kesiapan unit pengguna.\",\"status\":\"terbit\",\"created_by\":1,\"updated_by\":1,\"id_butir_snp\":\"B\\/10\\/012026-SNP.01\",\"updated_at\":\"2026-08-21T04:18:33.000000Z\",\"created_at\":\"2026-08-21T04:18:33.000000Z\",\"id\":14},\"input\":{\"butir_snp\":\"Menetapkan standar Go-live Smart Investment System yang dilengkapi checklist baku dan terverifikasi, mencakup aspek fungsional, kualitas, keamanan, serta kesiapan unit pengguna.\",\"unit_kerja_utama_id\":\"11\",\"unit_kerja_pendukung_id\":[\"17\",\"25\"],\"komite_id\":\"3\"}}', '103.168.122.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-21 04:18:34', '2026-08-21 04:18:34'),
(74, 1, 'snp', 'sidewas_snp', 'tb_butir_snp', 'B/10/012026-SNP.02', 'create', 'User menambahkan butir SNP pada surat B/10/012026-SNP.', NULL, '{\"record\":{\"id\":9,\"id_snp\":\"B\\/10\\/012026-SNP\",\"cluster_id\":9,\"sub_cluster_id\":30,\"nomor_surat\":\"B\\/10\\/012026\",\"tanggal_surat\":\"2026-01-06T17:00:00.000000Z\",\"perihal_surat\":\"Pengembangan Aplikasi Smart Investment System\",\"dokumen\":\"dokumen\\/record-snp\\/RpK2CGG1fsvQ5VhIbTPTNR6DblYKLy9RvQtxdv0t.pdf\",\"dokumen_memo\":\"dokumen\\/memo-snp\\/tNN9f2WE4fZDWs5f0SxEJBUwVrWNzvgMi1d93zOs.pdf\",\"jth_tempo\":\"2026-01-26T17:00:00.000000Z\",\"status\":\"dalam_proses\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-21T04:03:01.000000Z\",\"updated_at\":\"2026-08-21T04:18:33.000000Z\"},\"butir\":{\"id_snp\":\"B\\/10\\/012026-SNP\",\"butir_snp\":\"Meminta Satuan Pengawas Internal (SPI) untuk melakukan audit atas pekerjaan Smart Investment System guna menilai kecukupan tata kelola, efektivitas pemanfaatan biaya, serta potensi risiko penyimpangan.\",\"status\":\"terbit\",\"created_by\":1,\"updated_by\":1,\"id_butir_snp\":\"B\\/10\\/012026-SNP.02\",\"updated_at\":\"2026-08-21T04:19:22.000000Z\",\"created_at\":\"2026-08-21T04:19:22.000000Z\",\"id\":15},\"input\":{\"butir_snp\":\"Meminta Satuan Pengawas Internal (SPI) untuk melakukan audit atas pekerjaan Smart Investment System guna menilai kecukupan tata kelola, efektivitas pemanfaatan biaya, serta potensi risiko penyimpangan.\",\"unit_kerja_utama_id\":\"11\",\"unit_kerja_pendukung_id\":[\"4\"],\"komite_id\":\"3\"}}', '103.168.122.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-21 04:19:22', '2026-08-21 04:19:22'),
(75, 1, 'snp', 'sidewas_snp', 'tb_butir_snp', 'B/10/012026-SNP.03', 'create', 'User menambahkan butir SNP pada surat B/10/012026-SNP.', NULL, '{\"record\":{\"id\":9,\"id_snp\":\"B\\/10\\/012026-SNP\",\"cluster_id\":9,\"sub_cluster_id\":30,\"nomor_surat\":\"B\\/10\\/012026\",\"tanggal_surat\":\"2026-01-06T17:00:00.000000Z\",\"perihal_surat\":\"Pengembangan Aplikasi Smart Investment System\",\"dokumen\":\"dokumen\\/record-snp\\/RpK2CGG1fsvQ5VhIbTPTNR6DblYKLy9RvQtxdv0t.pdf\",\"dokumen_memo\":\"dokumen\\/memo-snp\\/tNN9f2WE4fZDWs5f0SxEJBUwVrWNzvgMi1d93zOs.pdf\",\"jth_tempo\":\"2026-01-26T17:00:00.000000Z\",\"status\":\"dalam_proses\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-21T04:03:01.000000Z\",\"updated_at\":\"2026-08-21T04:18:33.000000Z\"},\"butir\":{\"id_snp\":\"B\\/10\\/012026-SNP\",\"butir_snp\":\"Menindaklanjuti seluruh rekomendasi BPK terkait pengadaan Smart Investment System sampai tuntas sesuai perjanjian kerja sama.\",\"status\":\"terbit\",\"created_by\":1,\"updated_by\":1,\"id_butir_snp\":\"B\\/10\\/012026-SNP.03\",\"updated_at\":\"2026-08-21T04:20:02.000000Z\",\"created_at\":\"2026-08-21T04:20:02.000000Z\",\"id\":16},\"input\":{\"butir_snp\":\"Menindaklanjuti seluruh rekomendasi BPK terkait pengadaan Smart Investment System sampai tuntas sesuai perjanjian kerja sama.\",\"unit_kerja_utama_id\":\"11\",\"unit_kerja_pendukung_id\":[\"4\"],\"komite_id\":\"3\"}}', '103.168.122.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-21 04:20:02', '2026-08-21 04:20:02'),
(76, 1, 'snp', 'sidewas_snp', 'tb_butir_snp', 'B/10/012026-SNP.04', 'create', 'User menambahkan butir SNP pada surat B/10/012026-SNP.', NULL, '{\"record\":{\"id\":9,\"id_snp\":\"B\\/10\\/012026-SNP\",\"cluster_id\":9,\"sub_cluster_id\":30,\"nomor_surat\":\"B\\/10\\/012026\",\"tanggal_surat\":\"2026-01-06T17:00:00.000000Z\",\"perihal_surat\":\"Pengembangan Aplikasi Smart Investment System\",\"dokumen\":\"dokumen\\/record-snp\\/RpK2CGG1fsvQ5VhIbTPTNR6DblYKLy9RvQtxdv0t.pdf\",\"dokumen_memo\":\"dokumen\\/memo-snp\\/tNN9f2WE4fZDWs5f0SxEJBUwVrWNzvgMi1d93zOs.pdf\",\"jth_tempo\":\"2026-01-26T17:00:00.000000Z\",\"status\":\"dalam_proses\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-21T04:03:01.000000Z\",\"updated_at\":\"2026-08-21T04:18:33.000000Z\"},\"butir\":{\"id_snp\":\"B\\/10\\/012026-SNP\",\"butir_snp\":\"Melakukan langkah korektif terstruktur, meliputi:\\r\\n1) Penguatan tata kelola sistem melalui penetapan PIC lintas fungsi;\\r\\n2) Peningkatan kapasitas SDM, termasuk penguatan peran Quantitative Analyst\\r\\n3) Penguatan keamanan sistem dan kepatuhan terhadap standar TI;\\r\\n4) Penyelesaian seluruh fungsi kritikal sistem secara terukur dan terdokumentasi.\",\"status\":\"terbit\",\"created_by\":1,\"updated_by\":1,\"id_butir_snp\":\"B\\/10\\/012026-SNP.04\",\"updated_at\":\"2026-08-21T04:20:56.000000Z\",\"created_at\":\"2026-08-21T04:20:56.000000Z\",\"id\":17},\"input\":{\"butir_snp\":\"Melakukan langkah korektif terstruktur, meliputi:\\r\\n1) Penguatan tata kelola sistem melalui penetapan PIC lintas fungsi;\\r\\n2) Peningkatan kapasitas SDM, termasuk penguatan peran Quantitative Analyst\\r\\n3) Penguatan keamanan sistem dan kepatuhan terhadap standar TI;\\r\\n4) Penyelesaian seluruh fungsi kritikal sistem secara terukur dan terdokumentasi.\",\"unit_kerja_utama_id\":\"11\",\"unit_kerja_pendukung_id\":[\"17\"],\"komite_id\":\"3\"}}', '103.168.122.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-21 04:20:56', '2026-08-21 04:20:56'),
(77, 1, 'snp', 'sidewas_snp', 'tb_butir_snp', 'B/14/012026-SNP.01', 'create', 'User menambahkan butir SNP pada surat B/14/012026-SNP.', NULL, '{\"record\":{\"id\":10,\"id_snp\":\"B\\/14\\/012026-SNP\",\"cluster_id\":5,\"sub_cluster_id\":17,\"nomor_surat\":\"B\\/14\\/012026\",\"tanggal_surat\":\"2026-01-12T17:00:00.000000Z\",\"perihal_surat\":\"Kasus Fraud Klaim Jaminan Kecelakaa Kerja (JKK)\",\"dokumen\":\"dokumen\\/record-snp\\/jY3gJsl8Cj1sUA4lGf7R6op9xEzoBDOxea4pWosA.pdf\",\"dokumen_memo\":\"dokumen\\/memo-snp\\/4nGRDtE6BnIM50wFlUqE3Tbid52BzlYc4YZVVmNS.pdf\",\"jth_tempo\":\"2026-02-01T17:00:00.000000Z\",\"status\":\"dalam_proses\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-21T04:10:01.000000Z\",\"updated_at\":\"2026-08-21T04:25:21.000000Z\"},\"butir\":{\"id_snp\":\"B\\/14\\/012026-SNP\",\"butir_snp\":\"Mempercepat penyelesaian tindak lanjut SNP Dewan Pengawas sebelumnya terkait:\\r\\na. Efektivitas Penanganan Fraud Karyawan BPJS Ketenagakerjaan\\r\\nb. Penyempurnaan proses bisnis dalam penyelenggaraan Program Jaminan Kecelakaan Kerja (JKK).\",\"status\":\"terbit\",\"created_by\":1,\"updated_by\":1,\"id_butir_snp\":\"B\\/14\\/012026-SNP.01\",\"updated_at\":\"2026-08-21T04:25:21.000000Z\",\"created_at\":\"2026-08-21T04:25:21.000000Z\",\"id\":18},\"input\":{\"butir_snp\":\"Mempercepat penyelesaian tindak lanjut SNP Dewan Pengawas sebelumnya terkait:\\r\\na. Efektivitas Penanganan Fraud Karyawan BPJS Ketenagakerjaan\\r\\nb. Penyempurnaan proses bisnis dalam penyelenggaraan Program Jaminan Kecelakaan Kerja (JKK).\",\"unit_kerja_utama_id\":\"8\",\"unit_kerja_pendukung_id\":[\"3\",\"4\",\"9\",\"10\"],\"komite_id\":\"2\"}}', '103.168.122.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-21 04:25:21', '2026-08-21 04:25:21'),
(78, 1, 'snp', 'sidewas_snp', 'tb_butir_snp', 'B/14/012026-SNP.02', 'create', 'User menambahkan butir SNP pada surat B/14/012026-SNP.', NULL, '{\"record\":{\"id\":10,\"id_snp\":\"B\\/14\\/012026-SNP\",\"cluster_id\":5,\"sub_cluster_id\":17,\"nomor_surat\":\"B\\/14\\/012026\",\"tanggal_surat\":\"2026-01-12T17:00:00.000000Z\",\"perihal_surat\":\"Kasus Fraud Klaim Jaminan Kecelakaa Kerja (JKK)\",\"dokumen\":\"dokumen\\/record-snp\\/jY3gJsl8Cj1sUA4lGf7R6op9xEzoBDOxea4pWosA.pdf\",\"dokumen_memo\":\"dokumen\\/memo-snp\\/4nGRDtE6BnIM50wFlUqE3Tbid52BzlYc4YZVVmNS.pdf\",\"jth_tempo\":\"2026-02-01T17:00:00.000000Z\",\"status\":\"dalam_proses\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-21T04:10:01.000000Z\",\"updated_at\":\"2026-08-21T04:25:21.000000Z\"},\"butir\":{\"id_snp\":\"B\\/14\\/012026-SNP\",\"butir_snp\":\"Melakukan Audit untuk memastikan agar kasus serupa tidak terjadi di kantor wilayah lain.\",\"status\":\"terbit\",\"created_by\":1,\"updated_by\":1,\"id_butir_snp\":\"B\\/14\\/012026-SNP.02\",\"updated_at\":\"2026-08-21T04:25:50.000000Z\",\"created_at\":\"2026-08-21T04:25:50.000000Z\",\"id\":19},\"input\":{\"butir_snp\":\"Melakukan Audit untuk memastikan agar kasus serupa tidak terjadi di kantor wilayah lain.\",\"unit_kerja_utama_id\":\"8\",\"unit_kerja_pendukung_id\":[\"4\"],\"komite_id\":\"2\"}}', '103.168.122.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-21 04:25:50', '2026-08-21 04:25:50'),
(79, 1, 'snp', 'sidewas_snp', 'tb_butir_snp', 'B/14/012026-SNP.03', 'create', 'User menambahkan butir SNP pada surat B/14/012026-SNP.', NULL, '{\"record\":{\"id\":10,\"id_snp\":\"B\\/14\\/012026-SNP\",\"cluster_id\":5,\"sub_cluster_id\":17,\"nomor_surat\":\"B\\/14\\/012026\",\"tanggal_surat\":\"2026-01-12T17:00:00.000000Z\",\"perihal_surat\":\"Kasus Fraud Klaim Jaminan Kecelakaa Kerja (JKK)\",\"dokumen\":\"dokumen\\/record-snp\\/jY3gJsl8Cj1sUA4lGf7R6op9xEzoBDOxea4pWosA.pdf\",\"dokumen_memo\":\"dokumen\\/memo-snp\\/4nGRDtE6BnIM50wFlUqE3Tbid52BzlYc4YZVVmNS.pdf\",\"jth_tempo\":\"2026-02-01T17:00:00.000000Z\",\"status\":\"dalam_proses\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-21T04:10:01.000000Z\",\"updated_at\":\"2026-08-21T04:25:21.000000Z\"},\"butir\":{\"id_snp\":\"B\\/14\\/012026-SNP\",\"butir_snp\":\"Memastikan proses hukum oleh Aparat Penegak Hukum (APH) kepada para pelaku fraud secara tuntas untuk memberikan efek jera bagi seluruh insan BPJS Ketenagakerjaan agar tidak melakukan tindakan fraud di kemudian hari.\",\"status\":\"terbit\",\"created_by\":1,\"updated_by\":1,\"id_butir_snp\":\"B\\/14\\/012026-SNP.03\",\"updated_at\":\"2026-08-21T04:26:31.000000Z\",\"created_at\":\"2026-08-21T04:26:31.000000Z\",\"id\":20},\"input\":{\"butir_snp\":\"Memastikan proses hukum oleh Aparat Penegak Hukum (APH) kepada para pelaku fraud secara tuntas untuk memberikan efek jera bagi seluruh insan BPJS Ketenagakerjaan agar tidak melakukan tindakan fraud di kemudian hari.\",\"unit_kerja_utama_id\":\"8\",\"unit_kerja_pendukung_id\":[\"3\"],\"komite_id\":\"2\"}}', '103.168.122.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-21 04:26:31', '2026-08-21 04:26:31'),
(80, 1, 'snp', 'sidewas_snp', 'tb_butir_snp', 'B/14/012026-SNP.04', 'create', 'User menambahkan butir SNP pada surat B/14/012026-SNP.', NULL, '{\"record\":{\"id\":10,\"id_snp\":\"B\\/14\\/012026-SNP\",\"cluster_id\":5,\"sub_cluster_id\":17,\"nomor_surat\":\"B\\/14\\/012026\",\"tanggal_surat\":\"2026-01-12T17:00:00.000000Z\",\"perihal_surat\":\"Kasus Fraud Klaim Jaminan Kecelakaa Kerja (JKK)\",\"dokumen\":\"dokumen\\/record-snp\\/jY3gJsl8Cj1sUA4lGf7R6op9xEzoBDOxea4pWosA.pdf\",\"dokumen_memo\":\"dokumen\\/memo-snp\\/4nGRDtE6BnIM50wFlUqE3Tbid52BzlYc4YZVVmNS.pdf\",\"jth_tempo\":\"2026-02-01T17:00:00.000000Z\",\"status\":\"dalam_proses\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-21T04:10:01.000000Z\",\"updated_at\":\"2026-08-21T04:25:21.000000Z\"},\"butir\":{\"id_snp\":\"B\\/14\\/012026-SNP\",\"butir_snp\":\"Segera mengimplementasikan Fraud Detection System (FDS) sebagai early warning system untuk memantau pola klaim yang tidak wajar.\",\"status\":\"terbit\",\"created_by\":1,\"updated_by\":1,\"id_butir_snp\":\"B\\/14\\/012026-SNP.04\",\"updated_at\":\"2026-08-21T04:27:07.000000Z\",\"created_at\":\"2026-08-21T04:27:07.000000Z\",\"id\":21},\"input\":{\"butir_snp\":\"Segera mengimplementasikan Fraud Detection System (FDS) sebagai early warning system untuk memantau pola klaim yang tidak wajar.\",\"unit_kerja_utama_id\":\"8\",\"unit_kerja_pendukung_id\":[\"21\"],\"komite_id\":\"2\"}}', '103.168.122.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-21 04:27:07', '2026-08-21 04:27:07'),
(81, 1, 'snp', 'sidewas_snp', 'tb_butir_snp', 'B/14/012026-SNP.05', 'create', 'User menambahkan butir SNP pada surat B/14/012026-SNP.', NULL, '{\"record\":{\"id\":10,\"id_snp\":\"B\\/14\\/012026-SNP\",\"cluster_id\":5,\"sub_cluster_id\":17,\"nomor_surat\":\"B\\/14\\/012026\",\"tanggal_surat\":\"2026-01-12T17:00:00.000000Z\",\"perihal_surat\":\"Kasus Fraud Klaim Jaminan Kecelakaa Kerja (JKK)\",\"dokumen\":\"dokumen\\/record-snp\\/jY3gJsl8Cj1sUA4lGf7R6op9xEzoBDOxea4pWosA.pdf\",\"dokumen_memo\":\"dokumen\\/memo-snp\\/4nGRDtE6BnIM50wFlUqE3Tbid52BzlYc4YZVVmNS.pdf\",\"jth_tempo\":\"2026-02-01T17:00:00.000000Z\",\"status\":\"dalam_proses\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-21T04:10:01.000000Z\",\"updated_at\":\"2026-08-21T04:25:21.000000Z\"},\"butir\":{\"id_snp\":\"B\\/14\\/012026-SNP\",\"butir_snp\":\"Menyempurnakan perbaikan proses bisnis untuk penguatan pengendalian internal pada proses klaim.\",\"status\":\"terbit\",\"created_by\":1,\"updated_by\":1,\"id_butir_snp\":\"B\\/14\\/012026-SNP.05\",\"updated_at\":\"2026-08-21T04:27:56.000000Z\",\"created_at\":\"2026-08-21T04:27:56.000000Z\",\"id\":22},\"input\":{\"butir_snp\":\"Menyempurnakan perbaikan proses bisnis untuk penguatan pengendalian internal pada proses klaim.\",\"unit_kerja_utama_id\":\"8\",\"unit_kerja_pendukung_id\":[\"9\",\"10\",\"17\"],\"komite_id\":\"2\"}}', '103.168.122.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-21 04:27:56', '2026-08-21 04:27:56'),
(82, 1, 'snp', 'sidewas_snp', 'tb_butir_snp', 'B/34/022026-SNP.01', 'create', 'User menambahkan butir SNP pada surat B/34/022026-SNP.', NULL, '{\"record\":{\"id\":11,\"id_snp\":\"B\\/34\\/022026-SNP\",\"cluster_id\":5,\"sub_cluster_id\":18,\"nomor_surat\":\"B\\/34\\/022026\",\"tanggal_surat\":\"2026-02-01T17:00:00.000000Z\",\"perihal_surat\":\"Pengembangan Sistem Aktuaria\",\"dokumen\":\"dokumen\\/record-snp\\/PKUfdAa1lHLv7qffEWIVa2UOR4CS0UgbsBlzCqdX.pdf\",\"dokumen_memo\":\"dokumen\\/memo-snp\\/06m145vWXkZSrsxzABDPBLlcPuMwPEUwJ33mY1KH.pdf\",\"jth_tempo\":\"2026-02-19T17:00:00.000000Z\",\"status\":\"dalam_proses\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-21T04:13:51.000000Z\",\"updated_at\":\"2026-08-21T04:28:53.000000Z\"},\"butir\":{\"id_snp\":\"B\\/34\\/022026-SNP\",\"butir_snp\":\"Melakukan pengembangan sistem informasi dalam melakukan perhitungan cadangan teknis aktuaria\",\"status\":\"terbit\",\"created_by\":1,\"updated_by\":1,\"id_butir_snp\":\"B\\/34\\/022026-SNP.01\",\"updated_at\":\"2026-08-21T04:28:53.000000Z\",\"created_at\":\"2026-08-21T04:28:53.000000Z\",\"id\":23},\"input\":{\"butir_snp\":\"Melakukan pengembangan sistem informasi dalam melakukan perhitungan cadangan teknis aktuaria\",\"unit_kerja_utama_id\":\"15\",\"unit_kerja_pendukung_id\":[\"17\"],\"komite_id\":\"2\"}}', '103.168.122.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-21 04:28:53', '2026-08-21 04:28:53');
INSERT INTO `tb_log_activity` (`id`, `user_id`, `type_code`, `database_name`, `table_name`, `record_key`, `action`, `description`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`, `updated_at`) VALUES
(83, 1, 'snp', 'sidewas_snp', 'tb_butir_snp', 'B/34/022026-SNP.02', 'create', 'User menambahkan butir SNP pada surat B/34/022026-SNP.', NULL, '{\"record\":{\"id\":11,\"id_snp\":\"B\\/34\\/022026-SNP\",\"cluster_id\":5,\"sub_cluster_id\":18,\"nomor_surat\":\"B\\/34\\/022026\",\"tanggal_surat\":\"2026-02-01T17:00:00.000000Z\",\"perihal_surat\":\"Pengembangan Sistem Aktuaria\",\"dokumen\":\"dokumen\\/record-snp\\/PKUfdAa1lHLv7qffEWIVa2UOR4CS0UgbsBlzCqdX.pdf\",\"dokumen_memo\":\"dokumen\\/memo-snp\\/06m145vWXkZSrsxzABDPBLlcPuMwPEUwJ33mY1KH.pdf\",\"jth_tempo\":\"2026-02-19T17:00:00.000000Z\",\"status\":\"dalam_proses\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-21T04:13:51.000000Z\",\"updated_at\":\"2026-08-21T04:28:53.000000Z\"},\"butir\":{\"id_snp\":\"B\\/34\\/022026-SNP\",\"butir_snp\":\"Melakukan penyelarasan program kerja\\/kegiatan pengembangan sistem informasi dalam melakukan perhitungan cadangan teknis aktuaria dengan perencanaan strategis pengembangan IT\",\"status\":\"terbit\",\"created_by\":1,\"updated_by\":1,\"id_butir_snp\":\"B\\/34\\/022026-SNP.02\",\"updated_at\":\"2026-08-21T04:29:35.000000Z\",\"created_at\":\"2026-08-21T04:29:35.000000Z\",\"id\":24},\"input\":{\"butir_snp\":\"Melakukan penyelarasan program kerja\\/kegiatan pengembangan sistem informasi dalam melakukan perhitungan cadangan teknis aktuaria dengan perencanaan strategis pengembangan IT\",\"unit_kerja_utama_id\":\"15\",\"unit_kerja_pendukung_id\":[\"17\",\"14\"],\"komite_id\":\"2\"}}', '103.168.122.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-21 04:29:35', '2026-08-21 04:29:35'),
(84, 1, 'snp', 'sidewas_snp', 'tb_record', 'tes buat membaca flow SNP-SNP', 'create', 'User membuat perekaman surat SNP Dewas.', NULL, '{\"cluster_id\":\"3\",\"sub_cluster_id\":\"13\",\"nomor_surat\":\"tes buat membaca flow SNP\",\"tanggal_surat\":\"2026-08-25T17:00:00.000000Z\",\"perihal_surat\":\"tes buat membaca flow SNP\",\"dokumen\":\"dokumen\\/record-snp\\/7B6JXMhv01q9BikZbxdqQQC0aiQqBN8Zp39ZOm5r.pdf\",\"dokumen_memo\":\"dokumen\\/memo-snp\\/JY0A4hun2ZhUw5j4Rm4ArLFlvlnyYdHzIiZfHQOL.pdf\",\"status\":\"draft\",\"created_by\":1,\"updated_by\":1,\"id_snp\":\"tes buat membaca flow SNP-SNP\",\"jth_tempo\":\"2026-09-14T17:00:00.000000Z\",\"updated_at\":\"2026-08-26T02:36:10.000000Z\",\"created_at\":\"2026-08-26T02:36:10.000000Z\",\"id\":12}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-26 02:36:10', '2026-08-26 02:36:10'),
(85, 1, 'snp', 'sidewas_snp', 'tb_butir_snp', 'tes buat membaca flow SNP-SNP.01', 'create', 'User menambahkan butir SNP pada surat tes buat membaca flow SNP-SNP.', NULL, '{\"record\":{\"id\":12,\"id_snp\":\"tes buat membaca flow SNP-SNP\",\"cluster_id\":3,\"sub_cluster_id\":13,\"nomor_surat\":\"tes buat membaca flow SNP\",\"tanggal_surat\":\"2026-08-25T17:00:00.000000Z\",\"perihal_surat\":\"tes buat membaca flow SNP\",\"dokumen\":\"dokumen\\/record-snp\\/7B6JXMhv01q9BikZbxdqQQC0aiQqBN8Zp39ZOm5r.pdf\",\"dokumen_memo\":\"dokumen\\/memo-snp\\/JY0A4hun2ZhUw5j4Rm4ArLFlvlnyYdHzIiZfHQOL.pdf\",\"jth_tempo\":\"2026-09-14T17:00:00.000000Z\",\"status\":\"dalam_proses\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-26T02:36:10.000000Z\",\"updated_at\":\"2026-08-26T02:37:33.000000Z\"},\"butir\":{\"id_snp\":\"tes buat membaca flow SNP-SNP\",\"butir_snp\":\"tes buat membaca flow SNP\",\"status\":\"terbit\",\"created_by\":1,\"updated_by\":1,\"id_butir_snp\":\"tes buat membaca flow SNP-SNP.01\",\"updated_at\":\"2026-08-26T02:37:33.000000Z\",\"created_at\":\"2026-08-26T02:37:33.000000Z\",\"id\":25},\"input\":{\"butir_snp\":\"tes buat membaca flow SNP\",\"unit_kerja_utama_id\":\"26\",\"unit_kerja_pendukung_id\":[\"26\"],\"komite_id\":\"3\"}}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-26 02:37:33', '2026-08-26 02:37:33'),
(86, 1, 'snp', 'sidewas_snp', 'tb_tanggapan', 'tes buat membaca flow SNP-SNP.01', 'create', 'User membuat tanggapan SNP.', NULL, '{\"butir\":{\"id\":25,\"id_butir_snp\":\"tes buat membaca flow SNP-SNP.01\",\"id_snp\":\"tes buat membaca flow SNP-SNP\",\"butir_snp\":\"tes buat membaca flow SNP\",\"status\":\"terbit\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-26T02:37:33.000000Z\",\"updated_at\":\"2026-08-26T02:37:33.000000Z\",\"record\":{\"id\":12,\"id_snp\":\"tes buat membaca flow SNP-SNP\",\"cluster_id\":3,\"sub_cluster_id\":13,\"nomor_surat\":\"tes buat membaca flow SNP\",\"tanggal_surat\":\"2026-08-25T17:00:00.000000Z\",\"perihal_surat\":\"tes buat membaca flow SNP\",\"dokumen\":\"dokumen\\/record-snp\\/7B6JXMhv01q9BikZbxdqQQC0aiQqBN8Zp39ZOm5r.pdf\",\"dokumen_memo\":\"dokumen\\/memo-snp\\/JY0A4hun2ZhUw5j4Rm4ArLFlvlnyYdHzIiZfHQOL.pdf\",\"jth_tempo\":\"2026-09-14T17:00:00.000000Z\",\"status\":\"dalam_proses\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-26T02:36:10.000000Z\",\"updated_at\":\"2026-08-26T02:37:33.000000Z\"},\"butir_pics\":[{\"id\":76,\"id_butir_snp\":\"tes buat membaca flow SNP-SNP.01\",\"unit_kerja_id\":null,\"komite_id\":3,\"jenis_pic\":\"komite\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-26T02:37:33.000000Z\",\"updated_at\":\"2026-08-26T02:37:33.000000Z\"},{\"id\":74,\"id_butir_snp\":\"tes buat membaca flow SNP-SNP.01\",\"unit_kerja_id\":26,\"komite_id\":null,\"jenis_pic\":\"utama\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-26T02:37:33.000000Z\",\"updated_at\":\"2026-08-26T02:37:33.000000Z\"},{\"id\":75,\"id_butir_snp\":\"tes buat membaca flow SNP-SNP.01\",\"unit_kerja_id\":26,\"komite_id\":null,\"jenis_pic\":\"pendukung\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-26T02:37:33.000000Z\",\"updated_at\":\"2026-08-26T02:37:33.000000Z\"}],\"tanggapan\":[]},\"tanggapan\":{\"id_butir_snp\":\"tes buat membaca flow SNP-SNP.01\",\"butir_pic_id\":75,\"tanggapan\":\"tes buat membaca flow SNP\",\"deliverables\":\"tes buat membaca flow SNP\",\"dokumen\":null,\"created_by\":1,\"updated_by\":1,\"updated_at\":\"2026-08-26T02:38:00.000000Z\",\"created_at\":\"2026-08-26T02:38:00.000000Z\",\"id\":1},\"kompilasi_ready\":false}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-26 02:38:00', '2026-08-26 02:38:00'),
(87, 1, 'snp', 'sidewas_snp', 'tb_tanggapan', 'tes buat membaca flow SNP-SNP.01', 'create', 'User membuat tanggapan SNP.', NULL, '{\"butir\":{\"id\":25,\"id_butir_snp\":\"tes buat membaca flow SNP-SNP.01\",\"id_snp\":\"tes buat membaca flow SNP-SNP\",\"butir_snp\":\"tes buat membaca flow SNP\",\"status\":\"terbit\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-26T02:37:33.000000Z\",\"updated_at\":\"2026-08-26T02:37:33.000000Z\",\"record\":{\"id\":12,\"id_snp\":\"tes buat membaca flow SNP-SNP\",\"cluster_id\":3,\"sub_cluster_id\":13,\"nomor_surat\":\"tes buat membaca flow SNP\",\"tanggal_surat\":\"2026-08-25T17:00:00.000000Z\",\"perihal_surat\":\"tes buat membaca flow SNP\",\"dokumen\":\"dokumen\\/record-snp\\/7B6JXMhv01q9BikZbxdqQQC0aiQqBN8Zp39ZOm5r.pdf\",\"dokumen_memo\":\"dokumen\\/memo-snp\\/JY0A4hun2ZhUw5j4Rm4ArLFlvlnyYdHzIiZfHQOL.pdf\",\"jth_tempo\":\"2026-09-14T17:00:00.000000Z\",\"status\":\"dalam_proses\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-26T02:36:10.000000Z\",\"updated_at\":\"2026-08-26T02:37:33.000000Z\"},\"butir_pics\":[{\"id\":76,\"id_butir_snp\":\"tes buat membaca flow SNP-SNP.01\",\"unit_kerja_id\":null,\"komite_id\":3,\"jenis_pic\":\"komite\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-26T02:37:33.000000Z\",\"updated_at\":\"2026-08-26T02:37:33.000000Z\"},{\"id\":74,\"id_butir_snp\":\"tes buat membaca flow SNP-SNP.01\",\"unit_kerja_id\":26,\"komite_id\":null,\"jenis_pic\":\"utama\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-26T02:37:33.000000Z\",\"updated_at\":\"2026-08-26T02:37:33.000000Z\"},{\"id\":75,\"id_butir_snp\":\"tes buat membaca flow SNP-SNP.01\",\"unit_kerja_id\":26,\"komite_id\":null,\"jenis_pic\":\"pendukung\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-26T02:37:33.000000Z\",\"updated_at\":\"2026-08-26T02:37:33.000000Z\"}],\"tanggapan\":[{\"id\":1,\"id_butir_snp\":\"tes buat membaca flow SNP-SNP.01\",\"butir_pic_id\":75,\"tanggapan\":\"tes buat membaca flow SNP\",\"deliverables\":\"tes buat membaca flow SNP\",\"dokumen\":null,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-26T02:38:00.000000Z\",\"updated_at\":\"2026-08-26T02:38:00.000000Z\"}]},\"tanggapan\":{\"id_butir_snp\":\"tes buat membaca flow SNP-SNP.01\",\"butir_pic_id\":74,\"tanggapan\":\"tes buat membaca flow SNP\",\"deliverables\":\"tes buat membaca flow SNP\",\"dokumen\":null,\"created_by\":1,\"updated_by\":1,\"updated_at\":\"2026-08-26T02:38:24.000000Z\",\"created_at\":\"2026-08-26T02:38:24.000000Z\",\"id\":2},\"kompilasi_ready\":true}', '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-26 02:38:24', '2026-08-26 02:38:24'),
(88, 1, 'snp', 'sidewas_snp', 'tb_record', 'tes buat membaca flow SNP-SNP', 'delete', 'Super Admin menghapus perekaman SNP secara langsung.', '{\"id\":12,\"id_snp\":\"tes buat membaca flow SNP-SNP\",\"cluster_id\":3,\"sub_cluster_id\":13,\"nomor_surat\":\"tes buat membaca flow SNP\",\"tanggal_surat\":\"2026-08-25T17:00:00.000000Z\",\"perihal_surat\":\"tes buat membaca flow SNP\",\"dokumen\":\"dokumen\\/record-snp\\/7B6JXMhv01q9BikZbxdqQQC0aiQqBN8Zp39ZOm5r.pdf\",\"dokumen_memo\":\"dokumen\\/memo-snp\\/JY0A4hun2ZhUw5j4Rm4ArLFlvlnyYdHzIiZfHQOL.pdf\",\"jth_tempo\":\"2026-09-14T17:00:00.000000Z\",\"status\":\"dalam_proses\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-26T02:36:10.000000Z\",\"updated_at\":\"2026-08-26T02:37:33.000000Z\",\"butir_snp\":[{\"id\":25,\"id_butir_snp\":\"tes buat membaca flow SNP-SNP.01\",\"id_snp\":\"tes buat membaca flow SNP-SNP\",\"butir_snp\":\"tes buat membaca flow SNP\",\"status\":\"terbit\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-26T02:37:33.000000Z\",\"updated_at\":\"2026-08-26T02:37:33.000000Z\",\"butir_pics\":[{\"id\":76,\"id_butir_snp\":\"tes buat membaca flow SNP-SNP.01\",\"unit_kerja_id\":null,\"komite_id\":3,\"jenis_pic\":\"komite\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-26T02:37:33.000000Z\",\"updated_at\":\"2026-08-26T02:37:33.000000Z\"},{\"id\":74,\"id_butir_snp\":\"tes buat membaca flow SNP-SNP.01\",\"unit_kerja_id\":26,\"komite_id\":null,\"jenis_pic\":\"utama\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-26T02:37:33.000000Z\",\"updated_at\":\"2026-08-26T02:37:33.000000Z\"},{\"id\":75,\"id_butir_snp\":\"tes buat membaca flow SNP-SNP.01\",\"unit_kerja_id\":26,\"komite_id\":null,\"jenis_pic\":\"pendukung\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-26T02:37:33.000000Z\",\"updated_at\":\"2026-08-26T02:37:33.000000Z\"}]}],\"cluster\":{\"id\":3,\"nama_cluster\":\"Tata Kelola Data dan Teknologi Informasi\",\"keterangan\":null,\"created_at\":\"2026-06-30T07:41:50.000000Z\",\"updated_at\":\"2026-06-30T07:41:50.000000Z\"},\"sub_cluster\":{\"id\":13,\"cluster_id\":3,\"nama_sub_cluster\":\"Pengembangan Teknologi Informasi\",\"keterangan\":null,\"created_at\":\"2026-06-30T07:41:50.000000Z\",\"updated_at\":\"2026-06-30T07:41:50.000000Z\"}}', NULL, '36.64.21.139', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-26 02:39:06', '2026-08-26 02:39:06'),
(89, 1, 'snp', 'sidewas_snp', 'tb_tanggapan', 'B/34/022026-SNP.02', 'create', 'User membuat tanggapan SNP.', NULL, '{\"butir\":{\"id\":24,\"id_butir_snp\":\"B\\/34\\/022026-SNP.02\",\"id_snp\":\"B\\/34\\/022026-SNP\",\"butir_snp\":\"Melakukan penyelarasan program kerja\\/kegiatan pengembangan sistem informasi dalam melakukan perhitungan cadangan teknis aktuaria dengan perencanaan strategis pengembangan IT\",\"status\":\"terbit\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-21T04:29:35.000000Z\",\"updated_at\":\"2026-08-21T04:29:35.000000Z\",\"record\":{\"id\":11,\"id_snp\":\"B\\/34\\/022026-SNP\",\"cluster_id\":5,\"sub_cluster_id\":18,\"nomor_surat\":\"B\\/34\\/022026\",\"tanggal_surat\":\"2026-02-01T17:00:00.000000Z\",\"perihal_surat\":\"Pengembangan Sistem Aktuaria\",\"dokumen\":\"dokumen\\/record-snp\\/PKUfdAa1lHLv7qffEWIVa2UOR4CS0UgbsBlzCqdX.pdf\",\"dokumen_memo\":\"dokumen\\/memo-snp\\/06m145vWXkZSrsxzABDPBLlcPuMwPEUwJ33mY1KH.pdf\",\"jth_tempo\":\"2026-02-19T17:00:00.000000Z\",\"status\":\"dalam_proses\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-21T04:13:51.000000Z\",\"updated_at\":\"2026-08-21T04:28:53.000000Z\"},\"butir_pics\":[{\"id\":73,\"id_butir_snp\":\"B\\/34\\/022026-SNP.02\",\"unit_kerja_id\":null,\"komite_id\":2,\"jenis_pic\":\"komite\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-21T04:29:35.000000Z\",\"updated_at\":\"2026-08-21T04:29:35.000000Z\"},{\"id\":72,\"id_butir_snp\":\"B\\/34\\/022026-SNP.02\",\"unit_kerja_id\":14,\"komite_id\":null,\"jenis_pic\":\"pendukung\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-21T04:29:35.000000Z\",\"updated_at\":\"2026-08-21T04:29:35.000000Z\"},{\"id\":70,\"id_butir_snp\":\"B\\/34\\/022026-SNP.02\",\"unit_kerja_id\":15,\"komite_id\":null,\"jenis_pic\":\"utama\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-21T04:29:35.000000Z\",\"updated_at\":\"2026-08-21T04:29:35.000000Z\"},{\"id\":71,\"id_butir_snp\":\"B\\/34\\/022026-SNP.02\",\"unit_kerja_id\":17,\"komite_id\":null,\"jenis_pic\":\"pendukung\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-21T04:29:35.000000Z\",\"updated_at\":\"2026-08-21T04:29:35.000000Z\"}],\"tanggapan\":[]},\"tanggapan\":{\"id_butir_snp\":\"B\\/34\\/022026-SNP.02\",\"butir_pic_id\":72,\"tanggapan\":\"ME\\/43\\/092026\\r\\nTES\",\"deliverables\":\"TES 1\",\"dokumen\":null,\"created_by\":1,\"updated_by\":1,\"updated_at\":\"2026-09-03T10:33:06.000000Z\",\"created_at\":\"2026-09-03T10:33:06.000000Z\",\"id\":3},\"kompilasi_ready\":false}', '103.178.88.66', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-09-03 10:33:06', '2026-09-03 10:33:06'),
(90, 1, 'snp', 'sidewas_snp', 'tb_tanggapan', 'B/34/022026-SNP.02', 'create', 'User membuat tanggapan SNP.', NULL, '{\"butir\":{\"id\":24,\"id_butir_snp\":\"B\\/34\\/022026-SNP.02\",\"id_snp\":\"B\\/34\\/022026-SNP\",\"butir_snp\":\"Melakukan penyelarasan program kerja\\/kegiatan pengembangan sistem informasi dalam melakukan perhitungan cadangan teknis aktuaria dengan perencanaan strategis pengembangan IT\",\"status\":\"terbit\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-21T04:29:35.000000Z\",\"updated_at\":\"2026-08-21T04:29:35.000000Z\",\"record\":{\"id\":11,\"id_snp\":\"B\\/34\\/022026-SNP\",\"cluster_id\":5,\"sub_cluster_id\":18,\"nomor_surat\":\"B\\/34\\/022026\",\"tanggal_surat\":\"2026-02-01T17:00:00.000000Z\",\"perihal_surat\":\"Pengembangan Sistem Aktuaria\",\"dokumen\":\"dokumen\\/record-snp\\/PKUfdAa1lHLv7qffEWIVa2UOR4CS0UgbsBlzCqdX.pdf\",\"dokumen_memo\":\"dokumen\\/memo-snp\\/06m145vWXkZSrsxzABDPBLlcPuMwPEUwJ33mY1KH.pdf\",\"jth_tempo\":\"2026-02-19T17:00:00.000000Z\",\"status\":\"dalam_proses\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-21T04:13:51.000000Z\",\"updated_at\":\"2026-08-21T04:28:53.000000Z\"},\"butir_pics\":[{\"id\":73,\"id_butir_snp\":\"B\\/34\\/022026-SNP.02\",\"unit_kerja_id\":null,\"komite_id\":2,\"jenis_pic\":\"komite\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-21T04:29:35.000000Z\",\"updated_at\":\"2026-08-21T04:29:35.000000Z\"},{\"id\":72,\"id_butir_snp\":\"B\\/34\\/022026-SNP.02\",\"unit_kerja_id\":14,\"komite_id\":null,\"jenis_pic\":\"pendukung\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-21T04:29:35.000000Z\",\"updated_at\":\"2026-08-21T04:29:35.000000Z\"},{\"id\":70,\"id_butir_snp\":\"B\\/34\\/022026-SNP.02\",\"unit_kerja_id\":15,\"komite_id\":null,\"jenis_pic\":\"utama\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-21T04:29:35.000000Z\",\"updated_at\":\"2026-08-21T04:29:35.000000Z\"},{\"id\":71,\"id_butir_snp\":\"B\\/34\\/022026-SNP.02\",\"unit_kerja_id\":17,\"komite_id\":null,\"jenis_pic\":\"pendukung\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-21T04:29:35.000000Z\",\"updated_at\":\"2026-08-21T04:29:35.000000Z\"}],\"tanggapan\":[{\"id\":3,\"id_butir_snp\":\"B\\/34\\/022026-SNP.02\",\"butir_pic_id\":72,\"tanggapan\":\"ME\\/43\\/092026\\r\\nTES\",\"deliverables\":\"TES 1\",\"dokumen\":null,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-09-03T10:33:06.000000Z\",\"updated_at\":\"2026-09-03T10:33:06.000000Z\"}]},\"tanggapan\":{\"id_butir_snp\":\"B\\/34\\/022026-SNP.02\",\"butir_pic_id\":70,\"tanggapan\":\"TES 2\",\"deliverables\":\"TES 2\",\"dokumen\":null,\"created_by\":1,\"updated_by\":1,\"updated_at\":\"2026-09-03T10:33:56.000000Z\",\"created_at\":\"2026-09-03T10:33:56.000000Z\",\"id\":4},\"kompilasi_ready\":false}', '103.178.88.66', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-09-03 10:33:56', '2026-09-03 10:33:56'),
(91, 1, 'snp', 'sidewas_snp', 'tb_tanggapan', 'B/34/022026-SNP.02', 'create', 'User membuat tanggapan SNP.', NULL, '{\"butir\":{\"id\":24,\"id_butir_snp\":\"B\\/34\\/022026-SNP.02\",\"id_snp\":\"B\\/34\\/022026-SNP\",\"butir_snp\":\"Melakukan penyelarasan program kerja\\/kegiatan pengembangan sistem informasi dalam melakukan perhitungan cadangan teknis aktuaria dengan perencanaan strategis pengembangan IT\",\"status\":\"terbit\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-21T04:29:35.000000Z\",\"updated_at\":\"2026-08-21T04:29:35.000000Z\",\"record\":{\"id\":11,\"id_snp\":\"B\\/34\\/022026-SNP\",\"cluster_id\":5,\"sub_cluster_id\":18,\"nomor_surat\":\"B\\/34\\/022026\",\"tanggal_surat\":\"2026-02-01T17:00:00.000000Z\",\"perihal_surat\":\"Pengembangan Sistem Aktuaria\",\"dokumen\":\"dokumen\\/record-snp\\/PKUfdAa1lHLv7qffEWIVa2UOR4CS0UgbsBlzCqdX.pdf\",\"dokumen_memo\":\"dokumen\\/memo-snp\\/06m145vWXkZSrsxzABDPBLlcPuMwPEUwJ33mY1KH.pdf\",\"jth_tempo\":\"2026-02-19T17:00:00.000000Z\",\"status\":\"dalam_proses\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-21T04:13:51.000000Z\",\"updated_at\":\"2026-08-21T04:28:53.000000Z\"},\"butir_pics\":[{\"id\":73,\"id_butir_snp\":\"B\\/34\\/022026-SNP.02\",\"unit_kerja_id\":null,\"komite_id\":2,\"jenis_pic\":\"komite\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-21T04:29:35.000000Z\",\"updated_at\":\"2026-08-21T04:29:35.000000Z\"},{\"id\":72,\"id_butir_snp\":\"B\\/34\\/022026-SNP.02\",\"unit_kerja_id\":14,\"komite_id\":null,\"jenis_pic\":\"pendukung\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-21T04:29:35.000000Z\",\"updated_at\":\"2026-08-21T04:29:35.000000Z\"},{\"id\":70,\"id_butir_snp\":\"B\\/34\\/022026-SNP.02\",\"unit_kerja_id\":15,\"komite_id\":null,\"jenis_pic\":\"utama\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-21T04:29:35.000000Z\",\"updated_at\":\"2026-08-21T04:29:35.000000Z\"},{\"id\":71,\"id_butir_snp\":\"B\\/34\\/022026-SNP.02\",\"unit_kerja_id\":17,\"komite_id\":null,\"jenis_pic\":\"pendukung\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-21T04:29:35.000000Z\",\"updated_at\":\"2026-08-21T04:29:35.000000Z\"}],\"tanggapan\":[{\"id\":4,\"id_butir_snp\":\"B\\/34\\/022026-SNP.02\",\"butir_pic_id\":70,\"tanggapan\":\"TES 2\",\"deliverables\":\"TES 2\",\"dokumen\":null,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-09-03T10:33:56.000000Z\",\"updated_at\":\"2026-09-03T10:33:56.000000Z\"},{\"id\":3,\"id_butir_snp\":\"B\\/34\\/022026-SNP.02\",\"butir_pic_id\":72,\"tanggapan\":\"ME\\/43\\/092026\\r\\nTES\",\"deliverables\":\"TES 1\",\"dokumen\":null,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-09-03T10:33:06.000000Z\",\"updated_at\":\"2026-09-03T10:33:06.000000Z\"}]},\"tanggapan\":{\"id_butir_snp\":\"B\\/34\\/022026-SNP.02\",\"butir_pic_id\":71,\"tanggapan\":\"TES 3\",\"deliverables\":\"TES 3\",\"dokumen\":null,\"created_by\":1,\"updated_by\":1,\"updated_at\":\"2026-09-03T10:34:12.000000Z\",\"created_at\":\"2026-09-03T10:34:12.000000Z\",\"id\":5},\"kompilasi_ready\":true}', '103.178.88.66', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-09-03 10:34:12', '2026-09-03 10:34:12');

-- --------------------------------------------------------

--
-- Table structure for table `tb_role`
--

CREATE TABLE `tb_role` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `level` int NOT NULL DEFAULT '0',
  `is_universal` tinyint(1) NOT NULL DEFAULT '0',
  `keterangan` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tb_role`
--

INSERT INTO `tb_role` (`id`, `name`, `display_name`, `level`, `is_universal`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, 'super_admin', 'Super Admin', 100, 1, 'Role tertinggi yang dapat mengakses seluruh fitur dan seluruh database.', '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(2, 'admin', 'Admin', 80, 0, 'Role admin berdasarkan tipe akses.', '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(3, 'moderator', 'Moderator', 60, 0, 'Role moderator berdasarkan tipe akses.', '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(4, 'pic', 'PIC', 40, 0, 'Role PIC berdasarkan tipe akses.', '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(5, 'viewer', 'Viewer', 20, 0, 'Role pembaca/viewer berdasarkan tipe akses.', '2026-06-30 07:41:51', '2026-06-30 07:41:51');

-- --------------------------------------------------------

--
-- Table structure for table `tb_role_type`
--

CREATE TABLE `tb_role_type` (
  `id` bigint UNSIGNED NOT NULL,
  `role_id` bigint UNSIGNED NOT NULL,
  `type_id` bigint UNSIGNED DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tb_role_type`
--

INSERT INTO `tb_role_type` (`id`, `role_id`, `type_id`, `name`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 'super_admin', 'Akses universal seluruh tipe.', '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(2, 2, 1, 'admin_snp', 'Admin SNP', '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(3, 2, 2, 'admin_ragab', 'Admin RAGAB', '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(4, 2, 3, 'admin_rawas', 'Admin RAWAS', '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(5, 2, 4, 'admin_djsn', 'Admin DJSN', '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(6, 2, 5, 'admin_produk_hukum', 'Admin PRODUK HUKUM', '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(7, 2, 6, 'admin_eksternal', 'Admin EKSTERNAL', '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(8, 3, 1, 'moderator_snp', 'Moderator SNP', '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(9, 3, 2, 'moderator_ragab', 'Moderator RAGAB', '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(10, 3, 3, 'moderator_rawas', 'Moderator RAWAS', '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(11, 3, 4, 'moderator_djsn', 'Moderator DJSN', '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(12, 3, 6, 'moderator_eksternal', 'Moderator EKSTERNAL', '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(13, 4, 1, 'pic_snp', 'Pic SNP', '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(14, 4, 2, 'pic_ragab', 'Pic RAGAB', '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(15, 4, 3, 'pic_rawas', 'Pic RAWAS', '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(16, 4, 4, 'pic_djsn', 'Pic DJSN', '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(17, 4, 6, 'pic_eksternal', 'Pic EKSTERNAL', '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(18, 5, 1, 'viewer_snp', 'Viewer SNP', '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(19, 5, 2, 'viewer_ragab', 'Viewer RAGAB', '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(20, 5, 3, 'viewer_rawas', 'Viewer RAWAS', '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(21, 5, 4, 'viewer_djsn', 'Viewer DJSN', '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(22, 5, 5, 'viewer_produk_hukum', 'Viewer PRODUK HUKUM', '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(23, 5, 6, 'viewer_eksternal', 'Viewer EKSTERNAL', '2026-06-30 07:41:51', '2026-06-30 07:41:51');

-- --------------------------------------------------------

--
-- Table structure for table `tb_type`
--

CREATE TABLE `tb_type` (
  `id` bigint UNSIGNED NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `database_connection` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `database_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tb_type`
--

INSERT INTO `tb_type` (`id`, `code`, `name`, `database_connection`, `database_name`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, 'snp', 'SNP', 'mysql_snp', 'sidewas_snp', 'Tipe akses untuk SNP Dewas.', '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(2, 'ragab', 'RAGAB', 'mysql_ragab', 'sidewas_ragab', 'Tipe akses untuk RAGAB.', '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(3, 'rawas', 'RAWAS', 'mysql_rawas', 'sidewas_rawas', 'Tipe akses untuk RAWAS.', '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(4, 'djsn', 'DJSN', 'mysql_djsn', 'sidewas_djsn', 'Tipe akses untuk DJSN.', '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(5, 'produk_hukum', 'Produk Hukum', 'mysql_produk_hukum', 'sidewas_produk_hukum', 'Tipe akses untuk Produk Hukum.', '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(6, 'eksternal', 'Eksternal', 'mysql_eksternal', 'sidewas_eksternal', 'Tipe akses untuk Rapat Eksternal.', '2026-06-30 07:41:51', '2026-06-30 07:41:51');

-- --------------------------------------------------------

--
-- Table structure for table `tb_unit_kerja`
--

CREATE TABLE `tb_unit_kerja` (
  `id` bigint UNSIGNED NOT NULL,
  `direktorat_id` bigint UNSIGNED DEFAULT NULL,
  `nama_unit` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kode_unit` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tb_unit_kerja`
--

INSERT INTO `tb_unit_kerja` (`id`, `direktorat_id`, `nama_unit`, `kode_unit`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, 1, 'Deputi Bidang Sekretariat Badan', 'SBD', NULL, '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(2, 1, 'Deputi Bidang Komunikasi', 'KOM', NULL, '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(3, 1, 'Deputi Bidang Kepatuhan dan Hukum', 'KHK', NULL, '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(4, 1, 'Satuan Pengawas Internal', 'SPI', NULL, '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(5, 2, 'Deputi Bidang Kepesertaan Korporasi dan Institusi', 'KSI', NULL, '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(6, 2, 'Deputi Bidang Kepesertaan Program Khusus dan Keagenan', 'KSA', NULL, '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(7, 2, 'Deputi Bidang Pengawasan dan Pemeriksaan', 'WRK', NULL, '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(8, 3, 'Deputi Bidang Kebijakan Pelayanan Program', 'KLP', NULL, '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(9, 3, 'Deputi Bidang Operasional dan Kanal Layanan', 'OKL', NULL, '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(10, 3, 'Deputi Bidang Layanan Digital dan Customer Care', 'LDC', NULL, '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(11, 4, 'Deputi Bidang Analisis Portofolio', 'APF', NULL, '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(12, 4, 'Deputi Bidang Pendapatan Tetap dan Pasar Modal', 'PTM', NULL, '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(13, 4, 'Deputi Bidang Investasi Langsung', 'INL', NULL, '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(14, 5, 'Deputi Bidang Perencanaan Strategis dan Transformasi', 'REN', NULL, '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(15, 5, 'Deputi Bidang Aktuaria dan Riset Jaminan Sosial', 'AKR', NULL, '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(16, 5, 'Deputi Bidang Manajemen Data dan Analitik', 'MDT', NULL, '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(17, 5, 'Deputi Bidang Arsitektur dan Pengembangan TI', 'RPT', NULL, '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(18, 5, 'Deputi Bidang Infrastruktur dan Operasional TI', 'IPT', NULL, '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(19, 6, 'Deputi Bidang Akuntansi', 'AKT', NULL, '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(20, 6, 'Deputi Bidang Keuangan', 'KEU', NULL, '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(21, 6, 'Deputi Bidang Manajemen Risiko', 'MRK', NULL, '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(22, 7, 'Deputi Bidang Human Capital', 'HCP', NULL, '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(23, 7, 'Deputi Bidang Learning and Development', 'LND', NULL, '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(24, 7, 'Deputi Bidang Aset dan Sarana Prasarana', 'ASP', NULL, '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(25, 7, 'Deputi Bidang Pengadaan', 'PDN', NULL, '2026-06-30 07:41:51', '2026-06-30 07:41:51'),
(26, 8, 'Sekretariat Dewan Pengawas', 'SDW', NULL, '2026-06-30 07:41:51', '2026-06-30 07:41:51');

-- --------------------------------------------------------

--
-- Table structure for table `tb_user_komite`
--

CREATE TABLE `tb_user_komite` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `komite_id` bigint UNSIGNED NOT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tb_user_role_type`
--

CREATE TABLE `tb_user_role_type` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `role_type_id` bigint UNSIGNED NOT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tb_user_role_type`
--

INSERT INTO `tb_user_role_type` (`id`, `user_id`, `role_type_id`, `status`, `created_at`, `updated_at`) VALUES
(2, 2, 1, 'active', '2026-06-30 08:39:20', '2026-06-30 08:39:20'),
(4, 3, 3, 'active', '2026-06-30 08:51:19', '2026-06-30 08:52:21'),
(5, 3, 4, 'active', '2026-06-30 08:51:19', '2026-06-30 08:52:21'),
(6, 3, 6, 'active', '2026-06-30 08:51:19', '2026-06-30 08:52:21'),
(7, 3, 7, 'active', '2026-06-30 08:51:19', '2026-06-30 08:52:21'),
(8, 3, 18, 'active', '2026-06-30 08:52:21', '2026-06-30 08:52:21'),
(9, 3, 21, 'active', '2026-06-30 08:52:21', '2026-06-30 08:52:21'),
(10, 4, 8, 'active', '2026-06-30 09:09:32', '2026-06-30 09:10:22'),
(11, 4, 11, 'active', '2026-06-30 09:10:22', '2026-06-30 09:10:22'),
(12, 4, 19, 'active', '2026-06-30 09:10:22', '2026-06-30 09:10:22'),
(13, 4, 20, 'active', '2026-06-30 09:10:22', '2026-06-30 09:10:22'),
(14, 4, 22, 'active', '2026-06-30 09:10:22', '2026-06-30 09:10:22'),
(15, 4, 23, 'active', '2026-06-30 09:10:22', '2026-06-30 09:10:22'),
(22, 5, 8, 'active', '2026-06-30 09:13:09', '2026-06-30 09:13:46'),
(23, 5, 11, 'active', '2026-06-30 09:13:46', '2026-06-30 09:13:46'),
(24, 5, 19, 'active', '2026-06-30 09:13:46', '2026-06-30 09:13:46'),
(25, 5, 20, 'active', '2026-06-30 09:13:46', '2026-06-30 09:13:46'),
(26, 5, 22, 'active', '2026-06-30 09:13:46', '2026-06-30 09:13:46'),
(27, 5, 23, 'active', '2026-06-30 09:13:46', '2026-06-30 09:13:46'),
(28, 6, 8, 'active', '2026-06-30 09:14:36', '2026-06-30 09:15:08'),
(29, 6, 11, 'active', '2026-06-30 09:15:08', '2026-06-30 09:15:08'),
(30, 6, 19, 'active', '2026-06-30 09:15:08', '2026-06-30 09:15:08'),
(31, 6, 20, 'active', '2026-06-30 09:15:08', '2026-06-30 09:15:08'),
(32, 6, 22, 'active', '2026-06-30 09:15:08', '2026-06-30 09:15:08'),
(33, 6, 23, 'active', '2026-06-30 09:15:08', '2026-06-30 09:15:08'),
(34, 7, 2, 'active', '2026-06-30 09:16:02', '2026-06-30 09:16:41'),
(35, 7, 5, 'active', '2026-06-30 09:16:41', '2026-06-30 09:16:41'),
(36, 7, 19, 'active', '2026-06-30 09:16:41', '2026-06-30 09:16:41'),
(37, 7, 20, 'active', '2026-06-30 09:16:41', '2026-06-30 09:16:41'),
(38, 7, 22, 'active', '2026-06-30 09:16:41', '2026-06-30 09:16:41'),
(39, 7, 23, 'active', '2026-06-30 09:16:41', '2026-06-30 09:16:41'),
(40, 8, 9, 'active', '2026-06-30 09:18:08', '2026-06-30 09:18:41'),
(41, 8, 6, 'active', '2026-06-30 09:18:41', '2026-06-30 09:18:41'),
(42, 8, 10, 'active', '2026-06-30 09:18:41', '2026-06-30 09:18:41'),
(43, 8, 12, 'active', '2026-06-30 09:18:41', '2026-06-30 09:18:41'),
(44, 8, 18, 'active', '2026-06-30 09:18:41', '2026-06-30 09:18:41'),
(45, 8, 21, 'active', '2026-06-30 09:18:41', '2026-06-30 09:18:41'),
(46, 9, 9, 'active', '2026-06-30 09:20:06', '2026-06-30 09:20:38'),
(47, 9, 10, 'active', '2026-06-30 09:20:38', '2026-06-30 09:20:38'),
(48, 9, 12, 'active', '2026-06-30 09:20:38', '2026-06-30 09:20:38'),
(49, 9, 18, 'active', '2026-06-30 09:20:38', '2026-06-30 09:20:38'),
(50, 9, 21, 'active', '2026-06-30 09:20:38', '2026-06-30 09:20:38'),
(51, 9, 22, 'active', '2026-06-30 09:20:38', '2026-06-30 09:20:38'),
(52, 10, 9, 'active', '2026-06-30 09:22:12', '2026-06-30 09:22:37'),
(53, 10, 10, 'active', '2026-06-30 09:22:37', '2026-06-30 09:22:37'),
(54, 10, 12, 'active', '2026-06-30 09:22:37', '2026-06-30 09:22:37'),
(55, 10, 18, 'active', '2026-06-30 09:22:37', '2026-06-30 09:22:37'),
(56, 10, 21, 'active', '2026-06-30 09:22:37', '2026-06-30 09:22:37'),
(57, 10, 22, 'active', '2026-06-30 09:22:37', '2026-06-30 09:22:37'),
(58, 11, 1, 'active', '2026-06-30 09:23:33', '2026-06-30 09:23:33'),
(59, 12, 18, 'active', '2026-06-30 09:24:46', '2026-06-30 09:25:03'),
(60, 12, 19, 'active', '2026-06-30 09:25:03', '2026-06-30 09:25:03'),
(61, 12, 20, 'active', '2026-06-30 09:25:03', '2026-06-30 09:25:03'),
(62, 12, 21, 'active', '2026-06-30 09:25:03', '2026-06-30 09:25:03'),
(63, 12, 22, 'active', '2026-06-30 09:25:03', '2026-06-30 09:25:03'),
(64, 12, 23, 'active', '2026-06-30 09:25:03', '2026-06-30 09:25:03'),
(65, 13, 18, 'active', '2026-06-30 09:25:48', '2026-06-30 09:37:16'),
(67, 13, 20, 'active', '2026-06-30 09:26:04', '2026-06-30 09:37:16'),
(68, 13, 21, 'active', '2026-06-30 09:26:04', '2026-06-30 09:37:16'),
(69, 13, 22, 'active', '2026-06-30 09:26:04', '2026-06-30 09:37:16'),
(70, 13, 23, 'active', '2026-06-30 09:26:04', '2026-06-30 09:37:16'),
(71, 14, 18, 'active', '2026-06-30 09:27:17', '2026-06-30 09:37:47'),
(73, 14, 20, 'active', '2026-06-30 09:27:35', '2026-06-30 09:37:47'),
(74, 14, 21, 'active', '2026-06-30 09:27:35', '2026-06-30 09:37:47'),
(75, 14, 22, 'active', '2026-06-30 09:27:35', '2026-06-30 09:37:47'),
(76, 14, 23, 'active', '2026-06-30 09:27:35', '2026-06-30 09:37:47'),
(77, 15, 18, 'active', '2026-06-30 09:29:34', '2026-06-30 09:30:08'),
(78, 15, 20, 'active', '2026-06-30 09:30:08', '2026-06-30 09:30:08'),
(79, 15, 21, 'active', '2026-06-30 09:30:08', '2026-06-30 09:30:08'),
(80, 15, 22, 'active', '2026-06-30 09:30:08', '2026-06-30 09:30:08'),
(81, 15, 23, 'active', '2026-06-30 09:30:08', '2026-06-30 09:30:08'),
(82, 16, 18, 'active', '2026-06-30 09:31:15', '2026-06-30 09:31:38'),
(83, 16, 20, 'active', '2026-06-30 09:31:38', '2026-06-30 09:31:38'),
(84, 16, 21, 'active', '2026-06-30 09:31:38', '2026-06-30 09:31:38'),
(85, 16, 22, 'active', '2026-06-30 09:31:38', '2026-06-30 09:31:38'),
(86, 16, 23, 'active', '2026-06-30 09:31:38', '2026-06-30 09:31:38'),
(87, 17, 18, 'active', '2026-06-30 09:32:51', '2026-06-30 09:33:09'),
(88, 17, 19, 'active', '2026-06-30 09:33:09', '2026-06-30 09:33:09'),
(89, 17, 20, 'active', '2026-06-30 09:33:09', '2026-06-30 09:33:09'),
(90, 17, 21, 'active', '2026-06-30 09:33:09', '2026-06-30 09:33:09'),
(91, 17, 22, 'active', '2026-06-30 09:33:09', '2026-06-30 09:33:09'),
(92, 17, 23, 'active', '2026-06-30 09:33:09', '2026-06-30 09:33:09'),
(93, 18, 18, 'active', '2026-06-30 09:34:53', '2026-06-30 09:35:10'),
(94, 18, 20, 'active', '2026-06-30 09:35:10', '2026-06-30 09:35:10'),
(95, 18, 21, 'active', '2026-06-30 09:35:10', '2026-06-30 09:35:10'),
(96, 18, 22, 'active', '2026-06-30 09:35:10', '2026-06-30 09:35:10'),
(97, 18, 23, 'active', '2026-06-30 09:35:10', '2026-06-30 09:35:10'),
(98, 19, 18, 'active', '2026-06-30 09:36:37', '2026-06-30 09:36:54'),
(99, 19, 20, 'active', '2026-06-30 09:36:54', '2026-06-30 09:36:54'),
(100, 19, 21, 'active', '2026-06-30 09:36:54', '2026-06-30 09:36:54'),
(101, 19, 22, 'active', '2026-06-30 09:36:54', '2026-06-30 09:36:54'),
(102, 19, 23, 'active', '2026-06-30 09:36:54', '2026-06-30 09:36:54'),
(103, 1, 1, 'active', '2026-06-30 09:49:15', '2026-06-30 09:49:15');

-- --------------------------------------------------------

--
-- Table structure for table `tb_user_unit_kerja`
--

CREATE TABLE `tb_user_unit_kerja` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `unit_kerja_id` bigint UNSIGNED NOT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tb_user_unit_kerja`
--

INSERT INTO `tb_user_unit_kerja` (`id`, `user_id`, `unit_kerja_id`, `status`, `created_at`, `updated_at`) VALUES
(1, 2, 26, 'active', '2026-06-30 08:39:20', '2026-06-30 08:39:20'),
(6, 3, 26, 'active', '2026-06-30 08:52:21', '2026-06-30 08:52:21'),
(8, 4, 26, 'active', '2026-06-30 09:10:22', '2026-06-30 09:10:22'),
(11, 5, 26, 'active', '2026-06-30 09:13:46', '2026-06-30 09:13:46'),
(13, 6, 26, 'active', '2026-06-30 09:15:08', '2026-06-30 09:15:08'),
(15, 7, 26, 'active', '2026-06-30 09:16:41', '2026-06-30 09:16:41'),
(17, 8, 26, 'active', '2026-06-30 09:18:41', '2026-06-30 09:18:41'),
(19, 9, 26, 'active', '2026-06-30 09:20:38', '2026-06-30 09:20:38'),
(21, 10, 26, 'active', '2026-06-30 09:22:37', '2026-06-30 09:22:37'),
(22, 11, 26, 'active', '2026-06-30 09:23:33', '2026-06-30 09:23:33'),
(24, 12, 26, 'active', '2026-06-30 09:25:03', '2026-06-30 09:25:03'),
(30, 15, 26, 'active', '2026-06-30 09:30:08', '2026-06-30 09:30:08'),
(32, 16, 26, 'active', '2026-06-30 09:31:38', '2026-06-30 09:31:38'),
(34, 17, 26, 'active', '2026-06-30 09:33:09', '2026-06-30 09:33:09'),
(36, 18, 26, 'active', '2026-06-30 09:35:10', '2026-06-30 09:35:10'),
(38, 19, 26, 'active', '2026-06-30 09:36:54', '2026-06-30 09:36:54'),
(39, 13, 26, 'active', '2026-06-30 09:37:16', '2026-06-30 09:37:16'),
(40, 14, 26, 'active', '2026-06-30 09:37:47', '2026-06-30 09:37:47'),
(41, 1, 26, 'active', '2026-06-30 09:49:15', '2026-06-30 09:49:15');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint UNSIGNED NOT NULL,
  `google_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','active','blocked') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `google_id`, `name`, `email`, `avatar`, `provider`, `status`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, '109807184531177982894', 'Bagian Perencanaan dan Evaluasi Pengawasan Dewan Pengawas', 'pepd.sekdewas@bpjsketenagakerjaan.go.id', 'https://lh3.googleusercontent.com/a/ACg8ocIqj8vhu3oel--iW1FIS5xeNvOzzt22whylyMNGCyIBcfTw7Ur2=s96-c', 'google', 'active', '2026-06-30 07:43:40', '$2y$12$oS6To1gixfaNVI/A6Jt30OtfcZEXkEkvCssKWR79Im6a1Ka6N0uSO', 'F7iRBLLvHlEcQdxVMnfwWuPwJnzv98dqUoDndYGemmwKUClelPDqnH6NscDE', '2026-06-30 07:43:40', '2026-08-21 03:28:27'),
(2, '118002159147596813925', 'Sekretariat Dewan Pengawas', 'sekdewas@bpjsketenagakerjaan.go.id', 'https://lh3.googleusercontent.com/a/ACg8ocI2VaU5apQtPEqy-PthqvHtYIIMw46Gq3Bj4_7P6PTi3k8b2sQ=s96-c', 'google', 'pending', '2026-06-30 08:38:31', '$2y$12$veeeoU/Ciep2jN4O39sCa.cagAm6l52OqixgVbe6jQ48.rAjc4VK.', 'zukb3IBA7rT3L4MwZmhi3jNFrezFB0GpcfnB5NEB0VdkJYaMqlBFzf3K2O1d', '2026-06-30 08:38:31', '2026-06-30 08:38:31'),
(3, NULL, 'Bagian Persidangan, Produk Hukum dan Tata Kelola Dewan Pengawas', 'ppht.sekdewas@bpjsketenagakerjaan.go.id', NULL, NULL, 'active', '2026-06-30 08:49:27', '$2y$12$7KP72nLVAYE2LGHXZpky6.wJp5QxwtqtQNPUWCcxdU8SWJHRn2vAe', NULL, '2026-06-30 08:49:27', '2026-06-30 08:49:27'),
(4, '100311844600117752232', 'Krisna Adriyanto', 'krisna.adriyanto@bpjsketenagakerjaan.go.id', 'https://lh3.googleusercontent.com/a/ACg8ocLHTDes4VYVgH8tEZBhTXSSErz6oO5Em_CDD73XdH9kZAG0Xt1_=s96-c', 'google', 'active', '2026-06-30 09:09:31', '$2y$12$osqRQDRP8qgmqOKk/nTgpeE1YIt3jTnO.kxhQq9AU3lQ5B8ZnW0JO', 'oWO1KDg5hVsxueRbXa39nShdQi5c1lVTKzgHEj0gWJ3C5zllQycbiNCG9ROb', '2026-06-30 09:09:32', '2026-06-30 09:40:54'),
(5, '100320190816521398092', 'Sulfadli Muslim', 'sulfadli.muslim@bpjsketenagakerjaan.go.id', 'https://lh3.googleusercontent.com/a/ACg8ocLqd8f9P-YL6cIF3ZpCXUIWsXoUSJ7gJnqgaThrWkxP2o7bKFoz=s96-c', 'google', 'active', '2026-06-30 09:13:09', '$2y$12$jKdfBf2aG0OwsCG5/xHROeUgcFASwxZinFKvX2fawa4HBxmUt4FEO', 'yHqM4W5dNUaYmezHXXwj6fcDmN2fSs0yFFCtUMPLQGxLXAzTZPVmHBZsdbHx', '2026-06-30 09:13:09', '2026-06-30 12:23:10'),
(6, '103889245888464433865', 'Setyo Ardy Gunawan', 'setyo.ardy@bpjsketenagakerjaan.go.id', 'https://lh3.googleusercontent.com/a/ACg8ocIVV24ahgonjw_92KezKv4lzWpZXZOHYQKyRxTJqcYaNA7lM0kG=s96-c', 'google', 'active', '2026-06-30 09:14:36', '$2y$12$UmjOUEIMpI.XMh23Nv7gD.dta4rmBJf.rYu3A97UV2hD7uIGrqfja', 'ec0jJz1qEkc0ki8jLXaaqobMQvgmQqL4MEJibFVC1EdzgCt9DbhbycHsrpyW', '2026-06-30 09:14:36', '2026-06-30 12:30:45'),
(7, '113883557536914254333', 'Deni Juandani', 'deni.juandani@bpjsketenagakerjaan.go.id', 'https://lh3.googleusercontent.com/a/ACg8ocLlT3sBPalTM-0UI5IjvuK5vc-GlLmTMz7VGqmdmIzS8DNIxcY=s96-c', 'google', 'active', '2026-06-30 09:16:02', '$2y$12$i2qZgbgY7HbKMYgFnPuS2udO7oLJFHCNhccpXXTUHrdUHS/qAQ54K', 'k9rKYPbpX895T6m2I7gzVw6AHFZfygtGC2Ps7pc5aXfHd12TEpnjJ2lhyCsz', '2026-06-30 09:16:02', '2026-07-15 06:45:18'),
(8, '107143299000730286390', 'Fanny Amalul Arifin', 'fanny.amalul@bpjsketenagakerjaan.go.id', 'https://lh3.googleusercontent.com/a/ACg8ocI5iRPK5ymGVNQC1OCaD-WkJ5Ba2LzFHNZtonLSmegB40cUymg=s96-c', 'google', 'active', '2026-06-30 09:18:07', '$2y$12$z3DRO.xpqAuz8y.ym240w.39gWixMqun9UJdCuMjkgwRDMTmHHdu6', '5SPa6ov4iBjlT55iRiDogpLYtO6GTt5HSYAkBUx5MYEWvxol16u5DLMuZTlV', '2026-06-30 09:18:08', '2026-07-17 10:41:33'),
(9, '108559433082211355805', 'Farid Nur Iman', 'farid.nuriman@bpjsketenagakerjaan.go.id', 'https://lh3.googleusercontent.com/a/ACg8ocJdrKpOeqHBhihIxgCwaGFLkovj3VaRlMMb5ZqeJyK2GwVjIhU=s96-c', 'google', 'active', '2026-06-30 09:20:06', '$2y$12$Yq1XUe3Xbv9R9YM4C8YsXe8sKO6DcKT0DZgWPw1zcHYngmfCVwrR.', 'yDSHmxqPkxzI6ni1yZ9qSgd0U0XaKOX0Dj2c5JOp5wiP75PMpBErOUL6iEO6', '2026-06-30 09:20:06', '2026-07-09 07:08:45'),
(10, NULL, 'Mohamad Rhesa Adisty', 'mohamad.rhesa@bpjsketenagakerjaan.go.id', NULL, NULL, 'active', '2026-06-30 09:22:12', '$2y$12$fH1ozFjsWEilJi3lR3HoNeQRPLgq.Eyz52563rq0HrQHQaAAkloLi', NULL, '2026-06-30 09:22:12', '2026-06-30 09:22:12'),
(11, NULL, 'Lubis Latif', 'lubis.latif@bpjsketenagakerjaan.go.id', NULL, NULL, 'active', '2026-06-30 09:23:33', '$2y$12$1TfZV8CLv8REjhnFYihWkOvHOIW.99r0BXZUvWQdGZaauVAfawDsK', NULL, '2026-06-30 09:23:33', '2026-06-30 09:23:33'),
(12, NULL, 'Fitri Piralanasih', 'fitri.piralanasih@bpjsketenagakerjaan.go.id', NULL, NULL, 'active', '2026-06-30 09:24:46', '$2y$12$scWxUtCZaKtAsINwnOwvOOufZC3VKuLXg1tYb.fr4wIb5Ja/bBTK.', NULL, '2026-06-30 09:24:46', '2026-06-30 09:24:46'),
(13, NULL, 'Risa Purnama', 'risa.purnama@bpjsketenagakerjaan.go.id', NULL, NULL, 'active', '2026-06-30 09:25:47', '$2y$12$YP5yycEvFZt8H9ICVTPBKeX.SA3qnwT6zVIeEEjKc12b4ut1yEWbK', NULL, '2026-06-30 09:25:48', '2026-06-30 09:25:48'),
(14, NULL, 'Lucky Oktavianto', 'lucky.oktavianto@bpjsketenagakerjaan.go.id', NULL, NULL, 'active', '2026-06-30 09:27:17', '$2y$12$flmuQUb6ONMu0v8q5geOCuS7RZH.KC.KuUtN12qci/8G31UbaSaqm', NULL, '2026-06-30 09:27:17', '2026-06-30 09:27:17'),
(15, NULL, 'Sefi Dwi Prasanti', 'sefi.dwi@bpjsketenagakerjaan.go.id', NULL, NULL, 'active', '2026-06-30 09:29:34', '$2y$12$tzM98vsodP9FJ0fu0XUlMetpW05cCTZA/MfgoNxoLWCf122itXRxi', NULL, '2026-06-30 09:29:34', '2026-06-30 09:29:34'),
(16, NULL, 'Siti Rohani', 'siti.rohani@bpjsketenagakerjaan.go.id', NULL, NULL, 'active', '2026-06-30 09:31:15', '$2y$12$R9aSZ0CH0Fek8uaEV9vdAeQJqi4qA5NiwOBlGjYOBpsNErCSPdQu2', NULL, '2026-06-30 09:31:15', '2026-06-30 09:31:15'),
(17, NULL, 'Kartikarina Widyastuti', 'kartikarina.widyastuti@bpjsketenagakerjaan.go.id', NULL, NULL, 'active', '2026-06-30 09:32:51', '$2y$12$ZbF8tOKIWfaRS9YSjzj8keKQR4p8ROq4eArx.2S743v6Zw.01NGeu', NULL, '2026-06-30 09:32:51', '2026-06-30 09:32:51'),
(18, NULL, 'Fauzia Amalia Rachmawatie', 'fauzia.amalia@bpjsketenagakerjaan.go.id', NULL, NULL, 'active', '2026-06-30 09:34:52', '$2y$12$aPVb5dAs8HwHapt0dGLGOeY/m9gAD6mJqlpCoxicnB.b9DlkvM3nS', NULL, '2026-06-30 09:34:53', '2026-06-30 09:34:53'),
(19, NULL, 'Ines Kusuma Ningrum', 'ines.ningrum@bpjsketenagakerjaan.go.id', NULL, NULL, 'active', '2026-06-30 09:36:37', '$2y$12$2zXZNfdP7Hld.qNqRc6wX.yrhGZ75r44AJEr7wWMGJJCVL8F7iLMW', NULL, '2026-06-30 09:36:37', '2026-06-30 09:36:37');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_expiration_index` (`expiration`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_locks_expiration_index` (`expiration`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indexes for table `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `tb_delete_requests`
--
ALTER TABLE `tb_delete_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tb_delete_requests_requested_by_foreign` (`requested_by`),
  ADD KEY `tb_delete_requests_verified_by_foreign` (`verified_by`),
  ADD KEY `tb_delete_requests_approved_by_foreign` (`approved_by`),
  ADD KEY `tb_delete_requests_rejected_by_foreign` (`rejected_by`),
  ADD KEY `tb_delete_requests_type_code_table_name_record_key_index` (`type_code`,`table_name`,`record_key`),
  ADD KEY `tb_delete_requests_status_index` (`status`);

--
-- Indexes for table `tb_direktorat`
--
ALTER TABLE `tb_direktorat`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tb_direktorat_kode_direktorat_unique` (`kode_direktorat`);

--
-- Indexes for table `tb_komite`
--
ALTER TABLE `tb_komite`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tb_komite_kode_komite_unique` (`kode_komite`);

--
-- Indexes for table `tb_log_activity`
--
ALTER TABLE `tb_log_activity`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tb_log_activity_user_id_foreign` (`user_id`);

--
-- Indexes for table `tb_role`
--
ALTER TABLE `tb_role`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tb_role_name_unique` (`name`);

--
-- Indexes for table `tb_role_type`
--
ALTER TABLE `tb_role_type`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tb_role_type_name_unique` (`name`),
  ADD UNIQUE KEY `tb_role_type_role_type_unique` (`role_id`,`type_id`),
  ADD KEY `tb_role_type_type_id_foreign` (`type_id`);

--
-- Indexes for table `tb_type`
--
ALTER TABLE `tb_type`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tb_type_code_unique` (`code`);

--
-- Indexes for table `tb_unit_kerja`
--
ALTER TABLE `tb_unit_kerja`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tb_unit_kerja_kode_unit_unique` (`kode_unit`),
  ADD KEY `tb_unit_kerja_direktorat_id_foreign` (`direktorat_id`);

--
-- Indexes for table `tb_user_komite`
--
ALTER TABLE `tb_user_komite`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tb_user_komite_unique` (`user_id`,`komite_id`),
  ADD KEY `tb_user_komite_komite_id_foreign` (`komite_id`);

--
-- Indexes for table `tb_user_role_type`
--
ALTER TABLE `tb_user_role_type`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tb_user_role_type_unique` (`user_id`,`role_type_id`),
  ADD KEY `tb_user_role_type_role_type_id_foreign` (`role_type_id`);

--
-- Indexes for table `tb_user_unit_kerja`
--
ALTER TABLE `tb_user_unit_kerja`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tb_user_unit_kerja_unique` (`user_id`,`unit_kerja_id`),
  ADD KEY `tb_user_unit_kerja_unit_kerja_id_foreign` (`unit_kerja_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`),
  ADD UNIQUE KEY `users_google_id_unique` (`google_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `tb_delete_requests`
--
ALTER TABLE `tb_delete_requests`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tb_direktorat`
--
ALTER TABLE `tb_direktorat`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `tb_komite`
--
ALTER TABLE `tb_komite`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tb_log_activity`
--
ALTER TABLE `tb_log_activity`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tb_role`
--
ALTER TABLE `tb_role`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tb_role_type`
--
ALTER TABLE `tb_role_type`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `tb_type`
--
ALTER TABLE `tb_type`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tb_unit_kerja`
--
ALTER TABLE `tb_unit_kerja`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `tb_user_komite`
--
ALTER TABLE `tb_user_komite`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tb_user_role_type`
--
ALTER TABLE `tb_user_role_type`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=104;

--
-- AUTO_INCREMENT for table `tb_user_unit_kerja`
--
ALTER TABLE `tb_user_unit_kerja`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tb_delete_requests`
--
ALTER TABLE `tb_delete_requests`
  ADD CONSTRAINT `tb_delete_requests_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `tb_delete_requests_rejected_by_foreign` FOREIGN KEY (`rejected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `tb_delete_requests_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `tb_delete_requests_verified_by_foreign` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `tb_log_activity`
--
ALTER TABLE `tb_log_activity`
  ADD CONSTRAINT `tb_log_activity_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `tb_role_type`
--
ALTER TABLE `tb_role_type`
  ADD CONSTRAINT `tb_role_type_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `tb_role` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tb_role_type_type_id_foreign` FOREIGN KEY (`type_id`) REFERENCES `tb_type` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tb_unit_kerja`
--
ALTER TABLE `tb_unit_kerja`
  ADD CONSTRAINT `tb_unit_kerja_direktorat_id_foreign` FOREIGN KEY (`direktorat_id`) REFERENCES `tb_direktorat` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `tb_user_komite`
--
ALTER TABLE `tb_user_komite`
  ADD CONSTRAINT `tb_user_komite_komite_id_foreign` FOREIGN KEY (`komite_id`) REFERENCES `tb_komite` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tb_user_komite_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tb_user_role_type`
--
ALTER TABLE `tb_user_role_type`
  ADD CONSTRAINT `tb_user_role_type_role_type_id_foreign` FOREIGN KEY (`role_type_id`) REFERENCES `tb_role_type` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tb_user_role_type_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tb_user_unit_kerja`
--
ALTER TABLE `tb_user_unit_kerja`
  ADD CONSTRAINT `tb_user_unit_kerja_unit_kerja_id_foreign` FOREIGN KEY (`unit_kerja_id`) REFERENCES `tb_unit_kerja` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tb_user_unit_kerja_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
