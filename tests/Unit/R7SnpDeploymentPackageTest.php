<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

use function SidewasR7Snp20261027\buildOperations;
use function SidewasR7Snp20261027\collectExpectedState;
use function SidewasR7Snp20261027\executeBundle;
use function SidewasR7Snp20261027\verifyExpectedState;

uses(TestCase::class);

require_once __DIR__.'/WorkflowTestSetup.php';
require_once __DIR__.'/../../resources/deployment/r7-snp-reconciliation-20261027.php';

beforeEach(function () {
    $port = getenv('SIDEWAS_PUTARAN_TEST_MYSQL_PORT');
    $backup = getenv('SIDEWAS_R7_SNP_BACKUP_DIR');
    $audit = getenv('SIDEWAS_R7_AUDIT_JSON');
    if (! $port || ! $backup || ! $audit) {
        $this->markTestSkipped('Memerlukan MySQL lokal dan salinan SNP/audit yang diberikan untuk pengujian paket operasional.');
    }
    config(['app.timezone' => 'Asia/Jakarta', 'app.key' => 'base64:'.base64_encode(str_repeat('r', 32)), 'database.default' => 'mysql', 'sidewas.alur_revisi_enabled' => false, 'sidewas.reviu_putaran_enabled' => true]);
    $this->travelTo(Carbon::parse('2026-10-27T10:00:00+07:00'));
    $this->admin = new PDO('mysql:host=127.0.0.1;port='.(int) $port.';charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $this->mainDatabase = 'sidewas_r7_snpops_main_'.bin2hex(random_bytes(5));
    $this->admin->exec('CREATE DATABASE '.$this->mainDatabase.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    config(['database.connections.mysql' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => (int) $port, 'database' => $this->mainDatabase, 'username' => 'root', 'password' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'strict' => true]]);
    DB::purge('mysql');
    DB::connection('mysql')->statement("SET time_zone = '+07:00'");
    (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
    (require database_path('migrations/2026_05_07_000000_create_main_custom_tables.php'))->up();
    foreach (['tb_direktorat', 'tb_unit_kerja'] as $table) {
        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->string('status')->default('active'));
    }
    DB::table('tb_direktorat')->insert(['id' => 1, 'nama_direktorat' => 'Direktorat Uji']);
    Schema::drop('tb_log_activity');
    DB::connection('mysql')->unprepared(<<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `tb_log_activity`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tb_log_activity_user_id_foreign` (`user_id`);
ALTER TABLE `tb_log_activity`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `tb_log_activity`
  ADD CONSTRAINT `tb_log_activity_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
SQL);
    $this->database = 'sidewas_putaran_test_snp_'.bin2hex(random_bytes(5));
    $this->admin->exec('CREATE DATABASE '.$this->database.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    config(['database.connections.mysql_snp' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => (int) $port, 'database' => $this->database, 'username' => 'root', 'password' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'strict' => true]]);
    DB::purge('mysql_snp');
    $this->db = DB::connection('mysql_snp');
    $sql = file_get_contents($backup.'/sidewasi_sidewas_snp_backup_20261027.sql');
    expect(hash('sha256', $sql))->toBe('56632c3e2856edca64f766a213719483930f0b5b7f4db2d7794b91bf65688d82');
    $this->db->unprepared($sql);
    $this->db->statement("SET time_zone = '+07:00'");
    foreach ($this->db->table('tb_butir_pic')->whereNotNull('unit_kerja_id')->distinct()->pluck('unit_kerja_id') as $id) {
        DB::table('tb_unit_kerja')->insert(['id' => $id, 'direktorat_id' => 1, 'nama_unit' => 'Unit Uji '.$id]);
    }
    if (! DB::table('tb_unit_kerja')->where('id', 1)->exists()) {
        DB::table('tb_unit_kerja')->insert(['id' => 1, 'direktorat_id' => 1, 'nama_unit' => 'Unit Uji']);
    }
    foreach ($this->db->table('tb_butir_pic')->whereNotNull('komite_id')->distinct()->pluck('komite_id')->push(1)->unique() as $id) {
        DB::table('tb_komite')->insert(['id' => $id, 'nama_komite' => 'Komite Uji '.$id]);
    }
    $this->actor = workflowUser('super_admin');
    expect($this->actor->id)->toBe(1);
    Storage::fake('local');
    config(['filesystems.disks.public.root' => $backup.'/storage/app/public']);
    Storage::forgetDisk('public');
    $auditBytes = file_get_contents($audit);
    $this->audit = json_decode($auditBytes, true, flags: JSON_THROW_ON_ERROR);
    $evidence = ['disk' => 'local', 'path' => 'arsip-r7/snp-20261027/audit-uji.json', 'sha256' => hash('sha256', $auditBytes)];
    Storage::disk('local')->put($evidence['path'], $auditBytes);
    $archive = json_decode(file_get_contents($backup.'/manifest-arsip-dokumen.json'), true, flags: JSON_THROW_ON_ERROR);
    $this->package = ['version' => 1, 'actor_id' => 1, 'record_ids' => \SidewasR7Snp20261027\recordIds(), 'private_main_backup_confirmed' => true,
        'evidence' => $evidence, 'operations' => buildOperations($this->audit), 'expected' => collectExpectedState($this->db), 'documents' => array_map(fn ($row): array => array_intersect_key($row, array_flip(['disk', 'path', 'sha256', 'bytes'])), $archive['referenced_files'])];
    $this->before = [];
    foreach (\SidewasR7Snp20261027\tableNames() as $table) {
        $this->before[$table] = $this->db->table($table)->orderBy('id')->get()->toJson();
    }
    $this->mainControl = [];
    foreach (Schema::getTableListing($this->mainDatabase, schemaQualified: false) as $table) {
        if ($table === 'tb_log_activity') {
            continue;
        }
        $query = DB::table($table);
        if (in_array('id', Schema::getColumnListing($table), true)) {
            $query->orderBy('id');
        }
        $this->mainControl[$table] = $query->get()->toJson();
    }
});

afterEach(function () {
    DB::purge('mysql_snp');
    if (isset($this->database) && preg_match('/\Asidewas_putaran_test_snp_[a-f0-9]{10}\z/', $this->database)) {
        $this->admin->exec('DROP DATABASE '.$this->database);
    }
    DB::purge('mysql');
    if (isset($this->mainDatabase) && preg_match('/\Asidewas_r7_snpops_main_[a-f0-9]{10}\z/', $this->mainDatabase)) {
        $this->admin->exec('DROP DATABASE '.$this->mainDatabase);
    }
});

test('paket SNP membuktikan 25 operasi dan preview tidak menulis database maupun bukti operasi', function () {
    expect($this->package['operations'])->toHaveCount(25);
    $preview = executeBundle($this->package, $this->actor, false);
    expect($preview['mode'])->toBe('preview')->and($preview['database_modified'])->toBeFalse()
        ->and($preview['operations'])->toHaveCount(25)->and($preview['before_fingerprints'])->toHaveCount(12)
        ->and(DB::table('tb_log_activity')->count())->toBe(0)
        ->and(Storage::disk('local')->files('rekonsiliasi-r7'))->toBe([]);
    verifyExpectedState($this->db, $this->package['expected']);
    foreach ($this->before as $table => $json) {
        expect($this->db->table($table)->orderBy('id')->get()->toJson())->toBe($json, $table);
    }
    if ($directory = getenv('SIDEWAS_R7_BUILD_SNP_PACKAGE_DIR')) {
        $auditPath = $directory.'/r7-snp-audit-20261027.json';
        file_put_contents($auditPath, file_get_contents(getenv('SIDEWAS_R7_AUDIT_JSON')));
        $this->package['evidence'] = ['disk' => 'local', 'path' => 'arsip-r7/snp-20261027/bukti-log-'.substr(hash_file('sha256', $auditPath), 0, 12).'.json', 'sha256' => hash_file('sha256', $auditPath)];
        $this->package['source_audit_sha256'] = hash_file('sha256', $auditPath);
        $this->package['source_sql_sha256'] = hash_file('sha256', getenv('SIDEWAS_R7_SNP_BACKUP_DIR').'/sidewasi_sidewas_snp_backup_20261027.sql');
        $this->package['source_zip_sha256'] = hash_file('sha256', getenv('SIDEWAS_R7_SNP_BACKUP_DIR').'/dokumen-snp-20261027.zip');
        $this->package['code_hashes'] = [];
        foreach (['Models/User', 'Models/LogActivity', 'Models/SnpRecord', 'Models/SnpKompilasi', 'Models/SnpReview', 'Models/SuratRekonsiliasi', 'Models/SuratTahap', 'Models/SuratPutaran', 'Models/KompilasiBahan', 'Models/Concerns/HasSuratPutaran', 'Services/SuratRekonsiliasiService', 'Services/WorkflowReadinessService', 'Services/SuratAwalStatusService', 'Services/SuratTanggalService', 'Services/IsianUnitTahapService', 'Services/KompilasiBahanReader', 'Services/KompilasiBerkasService', 'Enums/SuratStatus', 'Enums/TahapProses'] as $file) {
            $relative = 'app/'.$file.'.php';
            $this->package['code_hashes'][$relative] = hash('sha256', str_replace("\r\n", "\n", file_get_contents(base_path($relative))));
        }
        file_put_contents($directory.'/r7-snp-package-20261027.json', json_encode($this->package, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
});

test('paket SNP menerapkan seluruh metadata dan audit dengan sumber asli serta putaran tetap utuh', function () {
    $oldLogId = DB::table('tb_log_activity')->insertGetId(['user_id' => $this->actor->id, 'action' => 'audit_kontrol_sebelum_R7', 'description' => 'Log lama harus tetap utuh', 'old_values' => '{}', 'new_values' => '{}', 'created_at' => '2026-10-26 08:00:00', 'updated_at' => '2026-10-26 08:00:00']);
    $oldLog = DB::table('tb_log_activity')->where('id', $oldLogId)->first();
    $preview = executeBundle($this->package, $this->actor, false);
    $phases = [];
    $result = executeBundle($this->package, $this->actor, true, $preview['before_fingerprints'], function ($phase) use (&$phases) {
        $phases[] = $phase;
    });
    expect($result['snp_committed'])->toBeTrue()->and($result['primary_audit_committed'])->toBeTrue()
        ->and($result['cross_database_atomic_commit'])->toBeFalse()
        ->and(DB::table('tb_log_activity')->count())->toBe(26)
        ->and(DB::table('tb_log_activity')->where('id', $oldLogId)->first())->toEqual($oldLog)
        ->and($this->db->table('tb_surat_rekonsiliasi')->count())->toBe(39)
        ->and($this->db->table('tb_surat_tanggal_riwayat')->count())->toBe(12)
        ->and($this->db->table('tb_kompilasi_bahan')->count())->toBe(1)
        ->and($this->db->table('tb_isian_unit_tahap')->count())->toBe(5)
        ->and($this->db->table('tb_surat_putaran')->count())->toBe(12)
        ->and($result['status_counts'])->toBe(['belum_ditindaklanjuti' => 9, 'proses' => 2, 'terbit' => 1])
        ->and($phases)->toContain('prepared_commit', 'snp_committed_main_pending', 'committed');
    verifyExpectedState($this->db, $this->package['expected'], true);
    expect(Storage::disk('local')->files('rekonsiliasi-r7'))->toHaveCount(25);
    foreach ($this->mainControl as $table => $json) {
        $query = DB::table($table);
        if (in_array('id', Schema::getColumnListing($table), true)) {
            $query->orderBy('id');
        }
        expect($query->get()->toJson())->toBe($json, 'Tabel utama selain log berubah: '.$table);
    }
    expect(fn () => executeBundle($this->package, $this->actor, true, $preview['before_fingerprints']))->toThrow(RuntimeException::class, 'Data berubah');
});

test('kegagalan di tengah paket membatalkan SNP dan audit serta membersihkan hanya bukti yang dibuatnya', function () {
    $preview = executeBundle($this->package, $this->actor, false);
    Storage::disk('local')->put('rekonsiliasi-r7/bukti-kontrol.bin', 'harus tetap ada');
    expect(fn () => executeBundle($this->package, $this->actor, true, $preview['before_fingerprints'], null, function ($step) {
        if ($step === 12) {
            throw new RuntimeException('SIMULASI gagal sesudah operasi 12');
        }
    }))->toThrow(RuntimeException::class, 'operasi 12');
    foreach ($this->before as $table => $json) {
        expect($this->db->table($table)->orderBy('id')->get()->toJson())->toBe($json, $table);
    }
    expect(DB::table('tb_log_activity')->count())->toBe(0)
        ->and(Storage::disk('local')->files('rekonsiliasi-r7'))->toBe(['rekonsiliasi-r7/bukti-kontrol.bin']);
});

test('paket menolak perubahan sumber terbaru tanpa menimpa perubahan tersebut', function () {
    $preview = executeBundle($this->package, $this->actor, false);
    $id = $this->db->table('tb_butir_snp')->min('id');
    $this->db->table('tb_butir_snp')->where('id', $id)->update(['butir_snp' => 'Perubahan baru harus dipertahankan']);
    expect(fn () => executeBundle($this->package, $this->actor, true, $preview['before_fingerprints']))->toThrow(RuntimeException::class, 'Data berubah');
    expect($this->db->table('tb_butir_snp')->where('id', $id)->value('butir_snp'))->toBe('Perubahan baru harus dipertahankan')
        ->and(DB::table('tb_log_activity')->count())->toBe(0);
});

test('perubahan sumber yang disisipkan selama operasi juga membatalkan seluruh paket', function () {
    $preview = executeBundle($this->package, $this->actor, false);
    expect(fn () => executeBundle($this->package, $this->actor, true, $preview['before_fingerprints'], null, function ($step) {
        if ($step === 2) {
            $id = $this->db->table('tb_butir_snp')->min('id');
            $this->db->table('tb_butir_snp')->where('id', $id)->update(['butir_snp' => 'SIMULASI perubahan tidak diizinkan']);
        }
    }))->toThrow(RuntimeException::class, 'Data berubah');
    foreach ($this->before as $table => $json) {
        expect($this->db->table($table)->orderBy('id')->get()->toJson())->toBe($json, $table);
    }
    expect(DB::table('tb_log_activity')->count())->toBe(0)
        ->and(Storage::disk('local')->files('rekonsiliasi-r7'))->toBe([]);
});

test('pemeriksaan menolak dokumen berbeda akun yang salah dan fingerprint preview yang salah', function () {
    $bad = $this->package;
    $bad['documents'][0]['sha256'] = str_repeat('0', 64);
    expect(fn () => executeBundle($bad, $this->actor, false))->toThrow(RuntimeException::class, 'Dokumen tidak tersedia atau berubah');
    $other = workflowUser('super_admin');
    expect(fn () => executeBundle($this->package, $other, false))->toThrow(RuntimeException::class, 'ID 1');
    expect(fn () => executeBundle($this->package, $this->actor, true, [6 => str_repeat('0', 64)]))->toThrow(RuntimeException::class, 'Fingerprint berbeda');
    DB::statement("SET time_zone = '+00:00'");
    expect(fn () => executeBundle($this->package, $this->actor, false))->toThrow(RuntimeException::class, 'waktu audit WIB');
    DB::statement("SET time_zone = '+07:00'");
    expect(DB::table('tb_log_activity')->count())->toBe(0);
    verifyExpectedState($this->db, $this->package['expected']);
});

test('jurnal membedakan kegagalan audit setelah commit SNP dan mempertahankan bukti pemulihan', function () {
    $preview = executeBundle($this->package, $this->actor, false);
    $journal = [];
    expect(function () use (&$journal, $preview) {
        executeBundle($this->package, $this->actor, true, $preview['before_fingerprints'], function ($phase, $details) use (&$journal) {
            $journal = array_replace($journal, ['phase' => $phase], $details);
            if ($phase === 'snp_committed_main_pending') {
                throw new RuntimeException('SIMULASI koneksi audit gagal sesudah commit SNP');
            }
        });
    })->toThrow(RuntimeException::class, 'koneksi audit');
    expect($journal['phase'])->toBe('snp_committed_audit_or_receipt_needs_review')
        ->and($journal['snp_committed'])->toBeTrue()->and($journal['primary_audit_committed'])->toBeFalse()
        ->and($journal['primary_audit_rows'])->toHaveCount(25)
        ->and($this->db->table('tb_surat_tanggal_riwayat')->count())->toBe(12)
        ->and(DB::table('tb_log_activity')->count())->toBe(0)
        ->and(Storage::disk('local')->files('rekonsiliasi-r7'))->toHaveCount(25);
    verifyExpectedState($this->db, $this->package['expected'], true);
});
