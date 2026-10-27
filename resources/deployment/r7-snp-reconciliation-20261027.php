<?php

declare(strict_types=1);

namespace SidewasR7Snp20261027;

use App\Models\SnpKompilasi;
use App\Models\SnpRecord;
use App\Models\User;
use App\Services\KompilasiBahanReader;
use App\Services\SuratAwalStatusService;
use App\Services\SuratRekonsiliasiService;
use App\Services\WorkflowReadinessService;
use Closure;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

const PACKAGE_SHA256 = 'afbf51b87162ef6bb40fba3d05c5f7aa0ead7132018c0084c518ec3ceba93d8f';

function recordIds(): array
{
    return [6, 7, 8, 9, 10, 11, 13, 14, 15, 16, 18, 19];
}

function tableNames(): array
{
    return ['tb_butir_pic', 'tb_butir_snp', 'tb_cluster', 'tb_isian_unit_tahap', 'tb_kompilasi', 'tb_kompilasi_bahan', 'tb_record', 'tb_review', 'tb_sub_cluster', 'tb_surat_putaran', 'tb_surat_rekonsiliasi', 'tb_surat_tahap', 'tb_surat_tanggal_riwayat', 'tb_tanggapan', 'tb_tindak_lanjut'];
}

function jsonHash(mixed $value): string
{
    return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function requireCondition(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function identifier(string $value): string
{
    requireCondition(preg_match('/\A[a-z0-9_]+\z/', $value) === 1, 'Nama tabel/kolom tidak valid.');

    return chr(96).$value.chr(96);
}

function schemaColumns(Connection $db, string $table): array
{
    return array_map(fn ($row): array => ['name' => $row->COLUMN_NAME, 'timestamp' => $row->DATA_TYPE === 'timestamp'], $db->select(
        'SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
        [$db->getDatabaseName(), $table],
    ));
}

function tableFingerprint(Connection $db, string $table, array $columns): array
{
    requireCondition(in_array($table, tableNames(), true), 'Tabel di luar paket SNP.');
    $expressions = array_map(fn ($column): string => $column['timestamp'] ? 'UNIX_TIMESTAMP('.identifier($column['name']).')' : identifier($column['name']), $columns);
    $sql = 'SELECT COUNT(*) AS jumlah, SHA2(COALESCE(GROUP_CONCAT(SHA2(CAST(JSON_ARRAY('.implode(', ', $expressions).") AS CHAR CHARACTER SET utf8mb4), 256) ORDER BY id SEPARATOR ''), ''), 256) AS sha256_isi FROM ".identifier($table);
    $row = $db->selectOne($sql);

    return ['count' => (int) $row->jumlah, 'sha256' => $row->sha256_isi];
}

function collectExpectedState(Connection $db): array
{
    $db->statement('SET SESSION group_concat_max_len = 1048576');
    $state = [];
    $excluded = [
        'tb_record' => ['status', 'pernah_terbit', 'tahap_aktif_id', 'putaran_aktif_id'],
        'tb_kompilasi' => ['salinan_konteks', 'status_bahan', 'bahan_dicatat_at', 'kunci_salinan'],
        'tb_surat_tahap' => ['nomor_putaran', 'status_pemetaan', 'mulai_at', 'jatuh_tempo', 'tanggal_ditetapkan_at', 'tanggal_ditetapkan_by', 'bukti_tanggal'],
    ];
    $fullyImmutable = ['tb_butir_pic', 'tb_butir_snp', 'tb_cluster', 'tb_sub_cluster', 'tb_tanggapan', 'tb_tindak_lanjut', 'tb_review', 'tb_surat_putaran'];
    foreach (tableNames() as $table) {
        $columns = schemaColumns($db, $table);
        $row = ['columns' => $columns, 'before' => tableFingerprint($db, $table, $columns)];
        if (in_array($table, $fullyImmutable, true) || isset($excluded[$table])) {
            $immutable = array_values(array_filter($columns, fn ($column): bool => ! in_array($column['name'], $excluded[$table] ?? [], true)));
            $row['immutable_columns'] = $immutable;
            $row['immutable'] = tableFingerprint($db, $table, $immutable);
        }
        $state[$table] = $row;
    }

    return $state;
}

/** Derives the same historical material already covered by the SNP audit-replay test. */
function buildOperations(array $audit): array
{
    requireCondition(($audit['aggregate_count_matches'] ?? false) === true && count($audit['rows']) === $audit['total_log'], 'Audit lengkap tidak konsisten.');
    $logs = collect($audit['rows']);
    $expectedLogs = [6 => 53, 7 => 59, 8 => 63, 9 => 73, 10 => 77, 11 => 82, 13 => 106, 14 => 109, 15 => 114, 16 => 116, 18 => 170, 19 => 172];
    $operations = [];
    $publication = [];
    foreach (SnpRecord::query()->whereIn('id', recordIds())->orderBy('id')->get() as $record) {
        $keys = $record->butirSnp()->pluck('id_butir_snp');
        $first = $logs->filter(fn ($log): bool => $log['type_code'] === 'snp' && $log['table_name'] === 'tb_butir_snp' && $log['action'] === 'create' && $keys->contains($log['record_key']))->sortBy('created_at_epoch')->first();
        requireCondition($first !== null && (int) $first['id'] === $expectedLogs[$record->id] && (int) data_get($first, 'new_values.record.id') === (int) $record->id, 'Bukti publikasi surat tidak cocok.');
        requireCondition(data_get($first, 'new_values.butir.status') === 'terbit' && ! empty(data_get($first, 'new_values.input.unit_kerja_utama_id')), 'Bukti penerbitan/penugasan tidak lengkap.');
        $stage = $record->tahapanProses()->where('tahap_proses', 'tanggapan_awal')->sole();
        $at = Carbon::createFromTimestamp($first['created_at_epoch'], 'Asia/Jakarta')->toIso8601String();
        $reason = 'Rekonsiliasi R7 SNP berdasarkan log bisnis #'.$first['id'].'; surat ID '.$record->id.'. Isi, pengisi, waktu asli dan putaran lama dipertahankan.';
        $operations[] = ['record_id' => (int) $record->id, 'action' => 'konfirmasi_tahap', 'reason' => $reason, 'details' => ['stage_id' => (int) $stage->id, 'nomor_putaran' => 0, 'aktif' => true]];
        $operations[] = ['record_id' => (int) $record->id, 'action' => 'konfirmasi_tanggal', 'reason' => $reason, 'details' => ['stage_id' => (int) $stage->id, 'mulai_at' => $at, 'ditetapkan_at' => $at]];
        $publication[$record->id] = $at;
    }
    requireCondition(count($operations) === 24, 'Paket harus berisi 12 surat yang dibuktikan.');
    $log = $logs->firstWhere('id', 176);
    requireCondition($log !== null && $log['type_code'] === 'snp', 'Log kompilasi historis tidak tersedia.');
    $archived = $log['new_values'];
    $butir = $archived['butir'];
    $compiled = $archived['kompilasi'];
    $letter = $butir['record'];
    $record = SnpRecord::findOrFail($letter['id']);
    $stage = $record->tahapanProses()->where('tahap_proses', 'tanggapan_awal')->sole();
    requireCondition((int) $record->id === 19 && (int) $compiled['id'] === 5, 'Target salinan historis berbeda.');
    $date = fn ($value) => $value === null ? null : Carbon::parse($value)->setTimezone('Asia/Jakarta')->toDateString();
    $bundle = fn ($value): array => ['isi' => $value, 'isi_asal' => $value, 'gambar' => []];
    $assignments = collect($butir['butir_pics'])->map(fn ($pic): array => ['pic_id' => $pic['id'], 'jenis_pic' => $pic['jenis_pic'],
        'unit' => $pic['unit_kerja_id'] === null ? null : ['id' => $pic['unit_kerja_id'], 'nama' => $pic['unit_kerja']['nama_unit'], 'kode' => $pic['unit_kerja']['kode_unit'], 'direktorat' => ['id' => $pic['unit_kerja']['direktorat_id'], 'nama' => null]],
        'komite' => $pic['komite_id'] === null ? null : ['id' => $pic['komite_id'], 'nama' => $pic['komite']['nama_komite']]])->values();
    $materials = [];
    foreach ($butir['tanggapan'] as $source) {
        $pic = collect($butir['butir_pics'])->firstWhere('id', $source['butir_pic_id']);
        $unit = $assignments->firstWhere('pic_id', $pic['id'])['unit'];
        requireCondition($source['dokumen'] === null && (int) $source['id'] === 9 && (int) $unit['id'] === 14, 'Bahan historis berbeda dari arsip yang diperiksa.');
        $materials[] = ['jenis_sumber' => 'tb_tanggapan', 'sumber_id' => $source['id'], 'unit_kerja_id' => $unit['id'], 'salinan' => $bundle($source['tanggapan']) + [
            'versi_salinan' => 1, 'versi_sumber' => hash('sha256', json_encode($source, JSON_THROW_ON_ERROR)), 'sumber' => ['tabel' => 'tb_tanggapan', 'id' => $source['id'], 'data_asal' => $source],
            'record_id' => $record->id, 'butir_key' => $butir['id_butir_snp'], 'tahap_id' => $stage->id, 'tahap_proses' => 'tanggapan_awal', 'nomor_putaran' => 0, 'putaran_asal_id' => $stage->putaran_asal_id,
            'deliverables' => $source['deliverables'], 'dokumen' => null, 'unit' => $unit, 'pengisi' => ['id' => $source['created_by'], 'nama' => null], 'pengubah' => ['id' => $source['updated_by'], 'nama' => null],
            'created_at' => $source['created_at'], 'updated_at' => $source['updated_at'], 'dicatat_at' => $log['created_at_wib'], 'penugasan' => $assignments->where('unit.id', $unit['id'])->values()->all()]];
    }
    requireCondition(count($materials) === 1 && $letter['dokumen'] === null && $letter['dokumen_memo'] === null && $compiled['dokumen'] === null, 'Paket khusus ini hanya mendukung arsip yang sudah diperiksa tanpa berkas salinan tambahan.');
    $context = ['versi_salinan' => 1, 'dicatat_at' => $log['created_at_wib'], 'penyusun' => ['id' => $compiled['created_by'], 'nama' => null],
        'surat' => ['id' => $record->id, 'id_surat' => $letter['id_snp'], 'nomor_surat' => $letter['nomor_surat'], 'tanggal_surat' => $date($letter['tanggal_surat']), 'perihal' => $letter['perihal_surat'], 'dokumen' => null, 'dokumen_memo' => null],
        'butir' => ['id' => $butir['id'], 'key' => $butir['id_butir_snp']] + $bundle($butir['butir_snp']),
        'tahap' => ['id' => $stage->id, 'jenis' => 'tanggapan_awal', 'nomor_putaran' => 0, 'putaran_asal_id' => $stage->putaran_asal_id, 'mulai_at' => $publication[$record->id], 'jatuh_tempo' => $date($letter['jth_tempo'])],
        'kompilasi' => $bundle($compiled['hasil_kompilasi']) + ['deliverables' => $compiled['deliverables'], 'dokumen' => null, 'ubah_tgl' => $date($compiled['ubah_tgl']), 'status_pengajuan_tgl' => $compiled['status_pengajuan_tgl']],
        'penugasan' => $assignments->all(), 'jumlah_bahan' => count($materials),
        'bahan' => collect($materials)->map(fn ($material): array => ['tabel' => $material['jenis_sumber'], 'id' => $material['sumber_id'], 'unit_kerja_id' => $material['unit_kerja_id'], 'versi_sumber' => $material['salinan']['versi_sumber']])->all(),
        'kelengkapan_unit' => $assignments->whereNotNull('unit')->unique('unit.id')->map(fn ($assignment): array => $assignment['unit'] + ['terisi' => collect($materials)->contains('unit_kerja_id', $assignment['unit']['id'])])->values()->all()];
    $operations[] = ['record_id' => 19, 'action' => 'salinan_historis', 'reason' => 'Rekonsiliasi R7 SNP berdasarkan snapshot kompilasi pada log bisnis #176. Hanya tanggapan ID 9 unit REN menjadi bahan resmi saat kompilasi.', 'details' => ['compilation_id' => 5, 'context' => $context, 'materials' => $materials, 'review_ids' => [3]]];

    return $operations;
}

function verifyExpectedState(Connection $db, array $expected, bool $immutableOnly = false): void
{
    requireCondition(array_keys($expected) === tableNames(), 'Daftar tabel paket berbeda.');
    foreach ($expected as $table => $spec) {
        requireCondition(schemaColumns($db, $table) === $spec['columns'], 'Struktur kolom berubah: '.$table);
        $columns = $immutableOnly ? ($spec['immutable_columns'] ?? null) : $spec['columns'];
        if ($columns === null) {
            continue;
        }
        $target = $immutableOnly ? $spec['immutable'] : $spec['before'];
        requireCondition(tableFingerprint($db, $table, $columns) === $target, 'Data berubah di luar rencana: '.$table);
    }
}

function verifyDocuments(array $documents): void
{
    requireCondition(count($documents) === 22, 'Daftar dokumen harus mencakup 22 PDF.');
    foreach ($documents as $document) {
        requireCondition($document['disk'] === 'public' && preg_match('~\Adokumen/(record-snp|memo-snp|pending-edit-snp)/[A-Za-z0-9]+\.pdf\z~', $document['path']) === 1, 'Path dokumen di luar cakupan SNP.');
        requireCondition(Storage::disk('public')->exists($document['path']) && hash('sha256', Storage::disk('public')->get($document['path'])) === $document['sha256'], 'Dokumen tidak tersedia atau berubah: '.$document['path']);
    }
}

function verifyDatabaseMetadata(Connection $db): void
{
    $name = $db->getDatabaseName();
    $tables = $db->select("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME", [$name]);
    requireCondition(array_column($tables, 'TABLE_NAME') === tableNames() && array_unique(array_column($tables, 'ENGINE')) === ['InnoDB'], 'Daftar/engine tabel SNP berbeda.');
    requireCondition((int) $db->selectOne('SELECT COUNT(*) AS n FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = ?', [$name])->n === 24, 'Foreign key SNP berbeda.');
    requireCondition((int) $db->selectOne('SELECT COUNT(*) AS n FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = ?', [$name])->n === 0, 'Trigger SNP perlu ditinjau.');
    requireCondition((int) $db->selectOne('SELECT COUNT(*) AS n FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE (CONSTRAINT_SCHEMA = ? AND UNIQUE_CONSTRAINT_SCHEMA <> ?) OR (UNIQUE_CONSTRAINT_SCHEMA = ? AND CONSTRAINT_SCHEMA <> ?)', [$name, $name, $name, $name])->n === 0, 'Ada foreign key lintas database SNP.');
    requireCondition((int) $db->selectOne('SELECT @@SESSION.foreign_key_checks AS n')->n === 1, 'Foreign key harus tetap aktif.');
    requireCondition((int) $db->selectOne('SELECT TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), NOW()) AS n')->n === 25200, 'Offset sesi MySQL harus +07:00 agar waktu historis WIB tersimpan benar.');
}

function verifySourceCode(array $hashes): void
{
    foreach ($hashes as $relative => $sha) {
        requireCondition(preg_match('~\Aapp/(Models|Services|Enums)/[A-Za-z0-9_/]+\.php\z~', $relative) === 1, 'Path kode tidak valid.');
        $path = base_path($relative);
        requireCondition(is_file($path) && hash('sha256', str_replace("\r\n", "\n", file_get_contents($path))) === $sha, 'Kode terpasang berbeda: '.$relative);
    }
}

/** One SNP transaction, plus a primary audit transaction. Journal explicitly records the cross-database commit boundary. */
function executeBundle(array $package, User $actor, bool $apply, array $reviewedFingerprints = [], ?Closure $journal = null, ?Closure $afterStep = null): array
{
    $db = DB::connection('mysql_snp');
    $main = DB::connection('mysql');
    requireCondition($db->getDriverName() === 'mysql' && $db->transactionLevel() === 0 && $main->transactionLevel() === 0, 'Gunakan koneksi dan transaksi baru.');
    requireCondition((int) $actor->id === 1 && (int) $package['actor_id'] === 1 && $actor->status === 'active' && $actor->isSuperAdmin(), 'Pemeriksa harus Super Admin aktif ID 1.');
    requireCondition($package['record_ids'] === recordIds() && count($package['operations']) === 25 && $package['private_main_backup_confirmed'] === true, 'Identitas paket atau cadangan belum sesuai.');
    requireCondition(config('app.timezone') === 'Asia/Jakarta' && ! config('sidewas.alur_revisi_enabled') && config('sidewas.reviu_putaran_enabled'), 'Zona waktu/flag harus sesuai sebelum penerapan.');
    verifyDatabaseMetadata($db);
    requireCondition((int) $main->selectOne('SELECT TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), NOW()) AS n')->n === 25200, 'Offset sesi MySQL utama harus +07:00 agar waktu audit WIB tersimpan benar.');
    verifyDocuments($package['documents']);
    $oldLimit = (int) $db->selectOne('SELECT @@SESSION.group_concat_max_len AS n')->n;
    $db->statement('SET SESSION group_concat_max_len = 1048576');
    $snpCommitted = false;
    $mainCommitted = false;
    $ownedProofs = [];
    $results = [];
    $operationIds = [];
    $oldFlow = config('sidewas.alur_revisi_enabled');
    try {
        $db->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $main->beginTransaction();
        $db->beginTransaction();
        $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
        requireCondition($actor->status === 'active' && $actor->isSuperAdmin(), 'Hak pemeriksa berubah.');
        foreach (tableNames() as $table) {
            $db->table($table)->where('id', '>=', 0)->orderBy('id')->lockForUpdate()->get(['id']);
        }
        verifyExpectedState($db, $package['expected']);
        $readiness = app(WorkflowReadinessService::class);
        $before = [];
        foreach (recordIds() as $id) {
            $before[$id] = $readiness->fingerprint('snp', $id);
        }
        if ($apply) {
            requireCondition($reviewedFingerprints !== [] && jsonHash($before) === jsonHash($reviewedFingerprints), 'Fingerprint berbeda dari laporan pemeriksaan yang ditinjau.');
            $journal?->__invoke('started', ['before_fingerprints' => $before]);
        }
        foreach ($package['operations'] as $index => $operation) {
            $input = ['version' => 1, 'module' => 'snp', 'record_id' => $operation['record_id'], 'action' => $operation['action'], 'reason' => $operation['reason'], 'details' => $operation['details'],
                'expected_fingerprint' => $apply ? $readiness->fingerprint('snp', $operation['record_id']) : $before[$operation['record_id']], 'evidence' => $package['evidence']];
            if ($apply) {
                $operationId = hash('sha256', json_encode([$input, $actor->id], JSON_THROW_ON_ERROR));
                $path = 'rekonsiliasi-r7/'.$operationId.'.bin';
                requireCondition(! Storage::disk('local')->exists($path), 'Bukti tujuan sudah ada; tinjau operasi sebelumnya.');
                $ownedProofs[] = $path;
                $journal?->__invoke('applying', ['step' => $index + 1, 'owned_proofs' => $ownedProofs, 'operation_ids' => $operationIds]);
            }
            $result = app(SuratRekonsiliasiService::class)->run($input, $actor, $apply);
            $results[] = $result;
            $operationIds[] = $result['operation_id'];
            $afterStep?->__invoke($index + 1);
        }
        if ($apply) {
            config(['sidewas.alur_revisi_enabled' => true]);
            foreach (SnpRecord::whereIn('id', recordIds())->get() as $record) {
                app(SuratAwalStatusService::class)->synchronizeRecord($record);
            }
            config(['sidewas.alur_revisi_enabled' => $oldFlow]);
            verifyExpectedState($db, $package['expected'], true);
            foreach (['tb_record' => 12, 'tb_surat_putaran' => 12, 'tb_surat_tahap' => 12, 'tb_tanggapan' => 5, 'tb_tindak_lanjut' => 0, 'tb_isian_unit_tahap' => 5, 'tb_surat_tanggal_riwayat' => 12, 'tb_kompilasi_bahan' => 1, 'tb_surat_rekonsiliasi' => 39] as $table => $count) {
                requireCondition($db->table($table)->count() === $count, 'Jumlah akhir berbeda: '.$table);
            }
            $compilation = SnpKompilasi::findOrFail(5);
            requireCondition(app(KompilasiBahanReader::class)->verified($compilation) && $compilation->bahan->pluck('sumber_id')->all() === [9], 'Salinan historis akhir tidak sesuai.');
            $status = $readiness->inspect(['snp'], true);
            requireCondition($status['ready'], 'Masih ada penghalang SNP setelah penerapan.');
            $auditRows = $main->table('tb_log_activity')->where('action', 'rekonsiliasi_alur_R7')->whereIn('new_values->operation_id', $operationIds)->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all();
            requireCondition(count($auditRows) === 25, 'Log aktivitas tidak lengkap.');
            $journal?->__invoke('prepared_commit', ['operation_ids' => $operationIds, 'owned_proofs' => $ownedProofs, 'primary_audit_rows' => $auditRows]);
            $db->commit();
            $snpCommitted = true;
            $journal?->__invoke('snp_committed_main_pending', ['operation_ids' => $operationIds, 'primary_audit_rows' => $auditRows]);
            $main->commit();
            $mainCommitted = true;
            $journal?->__invoke('committed', ['operation_ids' => $operationIds]);
        } else {
            verifyExpectedState($db, $package['expected']);
            $db->rollBack();
            $main->rollBack();
        }

        return ['version' => 1, 'mode' => $apply ? 'apply' : 'preview', 'checked_at' => now('Asia/Jakarta')->toIso8601String(), 'actor_id' => 1, 'operations' => $results,
            'before_fingerprints' => $before, 'database_modified' => $apply, 'source_values_preserved' => true, 'original_rounds_preserved' => true,
            'new_rounds_created' => 0, 'cross_database_atomic_commit' => false, 'snp_committed' => $snpCommitted, 'primary_audit_committed' => $mainCommitted,
            'status_counts' => $db->table('tb_record')->selectRaw('status, COUNT(*) AS jumlah')->groupBy('status')->pluck('jumlah', 'status')->all()];
    } catch (Throwable $exception) {
        if ($db->transactionLevel() > 0) {
            $db->rollBack();
        }
        if ($main->transactionLevel() > 0) {
            $main->rollBack();
        }
        if (! $snpCommitted) {
            foreach ($ownedProofs as $path) {
                if (Storage::disk('local')->exists($path)) {
                    Storage::disk('local')->delete($path);
                }
            }
        }
        $journal?->__invoke($snpCommitted ? 'snp_committed_audit_or_receipt_needs_review' : 'rolled_back', ['error' => $exception->getMessage(), 'snp_committed' => $snpCommitted, 'primary_audit_committed' => $mainCommitted, 'owned_proofs' => $ownedProofs]);
        throw $exception;
    } finally {
        config(['sidewas.alur_revisi_enabled' => $oldFlow]);
        $db->statement('SET SESSION group_concat_max_len = '.$oldLimit);
    }
}

function writePrivateJson(string $path, array $data, bool $replace = false): void
{
    requireCondition(is_dir(dirname($path)) && ($replace || ! file_exists($path)), 'Gunakan nama laporan baru pada folder yang sudah ada.');
    $temporary = $path.'.tmp-'.bin2hex(random_bytes(6));
    $stream = fopen($temporary, 'xb');
    requireCondition($stream !== false, 'Berkas laporan tidak dapat dibuat.');
    try {
        chmod($temporary, 0600);
        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        requireCondition(fwrite($stream, $json) === strlen($json), 'Laporan tidak tersimpan lengkap.');
    } finally {
        fclose($stream);
    }
    requireCondition(rename($temporary, $path), 'Laporan tidak dapat diselesaikan.');
}

function runCli(array $options): int
{
    $mode = $options['mode'] ?? 'preview';
    requireCondition(in_array($mode, ['preview', 'apply'], true), 'Mode harus preview atau apply.');
    $directory = __DIR__;
    $project = $options['project'] ?? '/home/sidewasi/repositories/sidewas';
    $output = $options['output'] ?? $directory.'/r7-snp-'.$mode.'-20261027.json';
    requireCondition(realpath(dirname($output)) === realpath($directory) && ! file_exists($output), 'Laporan harus memakai nama baru dalam folder privat paket.');
    $packagePath = $directory.'/r7-snp-package-20261027.json';
    requireCondition(is_file($packagePath) && hash_file('sha256', $packagePath) === PACKAGE_SHA256, 'Hash paket tidak cocok.');
    $packageBytes = file_get_contents($packagePath);
    $package = json_decode($packageBytes, true, 64, JSON_THROW_ON_ERROR);
    requireCondition($package['actor_id'] === 1 && $package['record_ids'] === recordIds(), 'Identitas paket berbeda.');
    foreach (['source_sql' => 'sidewasi_sidewas_snp_backup_20261027.sql', 'source_zip' => 'dokumen-snp-20261027.zip', 'source_audit' => 'r7-snp-audit-20261027.json'] as $key => $name) {
        requireCondition(is_file($directory.'/'.$name) && hash_file('sha256', $directory.'/'.$name) === $package[$key.'_sha256'], 'Cadangan/bukti tidak cocok: '.$name);
    }
    requireCondition(is_file($project.'/vendor/autoload.php') && is_file($project.'/bootstrap/app.php'), 'Folder proyek Laravel tidak ditemukan.');
    require $project.'/vendor/autoload.php';
    $app = require $project.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    requireCondition(DB::connection('mysql_snp')->getDatabaseName() === 'sidewasi_sidewas_snp' && DB::connection('mysql')->getDatabaseName() === 'sidewasi_sidewas', 'Database runtime tidak sesuai paket.');
    requireCondition(DB::connection('mysql')->getDriverName() === 'mysql', 'Koneksi utama harus MySQL.');
    verifySourceCode($package['code_hashes']);
    $main = DB::connection('mysql');
    requireCondition($main->selectOne("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'tb_log_activity'", [$main->getDatabaseName()])?->ENGINE === 'InnoDB', 'Log utama harus InnoDB.');
    requireCondition((int) $main->selectOne("SELECT COUNT(*) AS n FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = ? AND EVENT_OBJECT_TABLE = 'tb_log_activity'", [$main->getDatabaseName()])->n === 0, 'Trigger log utama perlu ditinjau.');
    $actor = User::findOrFail(1);
    requireCondition($actor->status === 'active' && $actor->isSuperAdmin(), 'Akun ID 1 harus tetap Super Admin aktif.');
    $audit = json_decode(file_get_contents($directory.'/r7-snp-audit-20261027.json'), true, 64, JSON_THROW_ON_ERROR);
    requireCondition(jsonHash(buildOperations($audit)) === jsonHash($package['operations']), 'Rencana berbeda dari audit atau hubungan data terbaru.');
    $evidence = $package['evidence'];
    requireCondition($evidence['disk'] === 'local' && preg_match('~\Aarsip-r7/snp-20261027/[a-z0-9-]+\.json\z~', $evidence['path']) === 1 && $evidence['sha256'] === $package['source_audit_sha256'], 'Path bukti paket tidak sesuai.');
    if (Storage::disk('local')->exists($evidence['path'])) {
        requireCondition(hash('sha256', Storage::disk('local')->get($evidence['path'])) === $evidence['sha256'], 'Bukti yang sudah ada berbeda.');
    } else {
        requireCondition(Storage::disk('local')->put($evidence['path'], file_get_contents($directory.'/r7-snp-audit-20261027.json')), 'Bukti tidak dapat disiapkan.');
        chmod(Storage::disk('local')->path($evidence['path']), 0600);
    }
    $preview = [];
    $journalPath = $directory.'/r7-snp-journal-20261027.json';
    if ($mode === 'apply') {
        requireCondition(($options['confirm'] ?? '') === 'REKONSILIASI-SNP-12-SURAT' && $app->isDownForMaintenance(), 'Penerapan memerlukan maintenance dan konfirmasi target yang tepat.');
        $previewPath = $directory.'/r7-snp-preview-20261027.json';
        requireCondition(is_file($previewPath) && ! is_file($journalPath), 'Laporan preview harus ada dan belum boleh ada jurnal penerapan.');
        $preview = json_decode(file_get_contents($previewPath), true, 64, JSON_THROW_ON_ERROR);
        requireCondition(($preview['ok'] ?? false) && $preview['package_sha256'] === PACKAGE_SHA256 && $preview['mode'] === 'preview' && $preview['database_modified'] === false, 'Laporan preview tidak sesuai paket.');
    }
    $journal = $mode === 'apply' ? function (string $phase, array $details) use ($journalPath): void {
        $previous = is_file($journalPath) ? json_decode(file_get_contents($journalPath), true, 64, JSON_THROW_ON_ERROR) : [];
        writePrivateJson($journalPath, array_replace($previous, ['version' => 1, 'phase' => $phase, 'at' => now('Asia/Jakarta')->toIso8601String(), 'package_sha256' => PACKAGE_SHA256, 'actor_id' => 1], $details), true);
    } : null;
    $result = executeBundle($package, $actor, $mode === 'apply', $preview['before_fingerprints'] ?? [], $journal);
    $result = ['ok' => true, 'package_sha256' => PACKAGE_SHA256, ...$result];
    writePrivateJson($output, $result);
    fwrite(STDOUT, json_encode(['ok' => true, 'mode' => $mode, 'output' => $output, 'operations' => count($result['operations']), 'actor_id' => 1], JSON_THROW_ON_ERROR).PHP_EOL);

    return 0;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if (PHP_SAPI !== 'cli') {
        http_response_code(403);
        exit;
    }
    umask(0077);
    try {
        exit(runCli(getopt('', ['mode:', 'project:', 'output:', 'confirm:'])));
    } catch (Throwable $exception) {
        fwrite(STDERR, json_encode(['ok' => false, 'error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE).PHP_EOL);
        exit(1);
    }
}
