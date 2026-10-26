<?php

use App\Enums\ReviuStatus;
use App\Enums\TahapProses;
use App\Models\DjsnButir;
use App\Models\DjsnKompilasi;
use App\Models\DjsnRecord;
use App\Models\DjsnReview;
use App\Models\SnpButir;
use App\Models\SnpKompilasi;
use App\Models\SnpRecord;
use App\Models\SnpReview;
use App\Models\SuratPutaran;
use App\Models\SuratTahap;
use App\Services\IsianUnitTahapService;
use App\Services\KompilasiTahapService;
use App\Services\ReviuPutaranService;
use App\Services\SnpButirContent;
use App\Services\SuratAwalStatusService;
use App\Services\SuratPutaranBackfill;
use App\Services\SuratPutaranService;
use App\Services\SuratTahapBackfill;
use App\Services\WorkflowReadinessService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

require_once __DIR__.'/WorkflowTestSetup.php';

function awaitWorkflowWorker(Process $process): void
{
    $deadline = microtime(true) + 10;
    while (! str_contains($process->getOutput(), "ready\n") && $process->isRunning() && microtime(true) < $deadline) {
        usleep(10_000);
    }
    expect(str_contains($process->getOutput(), "ready\n"))->toBeTrue($process->getOutput().$process->getErrorOutput());
}

beforeEach(function () {
    $port = getenv('SIDEWAS_PUTARAN_TEST_MYSQL_PORT');
    if (! $port) {
        $this->markTestSkipped('Set SIDEWAS_PUTARAN_TEST_MYSQL_PORT untuk server MySQL lokal khusus pengujian.');
    }

    $this->admin = new PDO('mysql:host=127.0.0.1;port='.(int) $port.';charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $this->databases = [];
    config(['database.default' => 'mysql']);
    config(['database.connections.mysql' => ['driver' => 'sqlite', 'database' => ':memory:']]);
    DB::purge('mysql');

    foreach (['snp', 'djsn'] as $module) {
        $database = 'sidewas_putaran_test_'.$module.'_'.bin2hex(random_bytes(5));
        $this->admin->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->databases[$module] = $database;
        config(['database.connections.mysql_'.$module => [
            'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => (int) $port,
            'database' => $database, 'username' => 'root', 'password' => '',
            'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true,
        ]]);
        DB::purge('mysql_'.$module);
    }
    $this->migration = require database_path('migrations/2026_10_21_134321_add_surat_putaran_foundation.php');
});

afterEach(function () {
    foreach ($this->databases ?? [] as $module => $database) {
        DB::purge('mysql_'.$module);
        if (preg_match('/\Asidewas_putaran_test_(snp|djsn|ragab|rawas|eksternal|produk_hukum|kunjungan)_[a-f0-9]{10}\z/', $database)) {
            $this->admin->exec('DROP DATABASE `'.$database.'`');
        }
    }
});

test('R7 dump terbaru tujuh modul tetap utuh setelah migrasi ulang serta backup dan restore MySQL', function () {
    $directory = getenv('SIDEWAS_R7_DUMP_DIR');
    if (! $directory) {
        $this->markTestSkipped('Set SIDEWAS_R7_DUMP_DIR untuk dump terbaru tanpa database akun.');
    }
    $modules = ['snp', 'djsn', 'ragab', 'rawas', 'eksternal', 'produk_hukum', 'kunjungan'];
    $snapshots = [];
    foreach ($modules as $module) {
        if (! isset($this->databases[$module])) {
            $database = 'sidewas_putaran_test_'.$module.'_'.bin2hex(random_bytes(5));
            $this->admin->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $this->databases[$module] = $database;
            config(['database.connections.mysql_'.$module => array_replace(config('database.connections.mysql_snp'), ['database' => $database])]);
            DB::purge('mysql_'.$module);
        }
        $db = DB::connection('mysql_'.$module);
        $filename = $module === 'snp' ? 'sidewasi_sidewas_snp (1).sql' : 'sidewasi_sidewas_'.$module.'.sql';
        $sql = file_get_contents($directory.DIRECTORY_SEPARATOR.$filename);
        expect($sql)->not->toBeFalse();
        if (preg_match('/^\s*(?:USE\b|(?:CREATE|DROP)\s+DATABASE\b|SOURCE\b|DELIMITER\b|CREATE\s+USER\b|GRANT\b)/im', $sql)) {
            throw new RuntimeException('Dump harus diarahkan hanya ke database uji.');
        }
        $db->unprepared($sql);
        foreach ($db->getSchemaBuilder()->getTableListing($db->getDatabaseName(), schemaQualified: false) as $table) {
            $columns = $db->getSchemaBuilder()->getColumnListing($table);
            $snapshots[$module][$table] = ['columns' => $columns, 'rows' => $db->table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all()];
        }
    }
    config(['sidewas.alur_revisi_enabled' => false]);
    foreach (['2026_10_21_134321_add_surat_putaran_foundation.php', '2026_10_21_141454_enable_snp_djsn_initial_letter_statuses.php',
        '2026_10_22_085515_expand_snp_djsn_review_status_choices.php', '2026_10_22_100320_add_snp_djsn_letter_closure_metadata.php',
        '2026_10_22_132318_add_unit_assignments_to_surat_putaran.php', '2026_10_23_094342_add_snp_djsn_process_stage_foundation.php',
        '2026_10_23_102718_add_snp_djsn_stage_unit_entries.php', '2026_10_23_131534_add_snp_djsn_compilation_snapshots.php',
        '2026_10_23_143129_add_snp_djsn_stage_deadline_audit.php'] as $file) {
        $migration = require database_path('migrations/'.$file);
        $migration->up();
        $migration->up();
    }
    $directory = base_path('tmp/r7/backup-test');
    if (! is_dir($directory)) {
        mkdir($directory, 0700, true);
    }
    $summary = [];
    foreach ($snapshots as $module => $tables) {
        $db = DB::connection('mysql_'.$module);
        foreach ($tables as $table => $snapshot) {
            expect($db->table($table)->select($snapshot['columns'])->orderBy('id')->get()->map(fn ($row) => (array) $row)->all())->toEqual($snapshot['rows']);
        }
        $dumpPath = $directory.'/'.$module.'.sql';
        $dump = new Process([getenv('SIDEWAS_R7_MYSQLDUMP') ?: 'mysqldump', '--no-defaults', '--host=127.0.0.1',
            '--port='.getenv('SIDEWAS_PUTARAN_TEST_MYSQL_PORT'), '--user=root', '--single-transaction', '--quick', '--hex-blob', '--no-tablespaces',
            '--set-gtid-purged=OFF', '--result-file='.$dumpPath, $db->getDatabaseName()]);
        $dump->setTimeout(60)->run();
        expect($dump->isSuccessful())->toBeTrue($dump->getErrorOutput());
        $restored = 'sidewas_putaran_test_'.$module.'_'.bin2hex(random_bytes(5));
        $this->admin->exec('CREATE DATABASE `'.$restored.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        try {
            $pdo = new PDO('mysql:host=127.0.0.1;port='.(int) getenv('SIDEWAS_PUTARAN_TEST_MYSQL_PORT').';dbname='.$restored.';charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec(file_get_contents($dumpPath));
            $pdo->exec("SET time_zone = '+00:00'");
            $db->statement("SET time_zone = '+00:00'");
            foreach ($db->getSchemaBuilder()->getTableListing($db->getDatabaseName(), schemaQualified: false) as $table) {
                $before = $db->table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
                $after = $pdo->query('SELECT * FROM `'.$table.'` ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
                expect(hash('sha256', json_encode($after, JSON_THROW_ON_ERROR)))->toBe(hash('sha256', json_encode($before, JSON_THROW_ON_ERROR)), $module.'.'.$table.' berbeda setelah restore');
            }
        } finally {
            $pdo = null;
            $this->admin->exec('DROP DATABASE `'.$restored.'`');
        }
        $summary[$module] = ['tables' => count($tables), 'rows' => array_sum(array_map(fn ($table) => count($table['rows']), $tables)),
            'backup_sha256' => hash_file('sha256', $dumpPath), 'source_preserved' => true, 'restore_verified' => true];
    }
    $readiness = app(WorkflowReadinessService::class)->inspect();
    file_put_contents(base_path('tmp/r7/latest-dump-readiness.json'), json_encode($readiness, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    file_put_contents(base_path('tmp/r7/migration-restore-results.json'), json_encode($summary, JSON_PRETTY_PRINT));
    if ($storage = getenv('SIDEWAS_R7_STORAGE_DIR')) {
        $assets = [];
        foreach (['snp', 'djsn'] as $module) {
            foreach ($snapshots[$module] as $table => $snapshot) {
                foreach ($snapshot['rows'] as $row) {
                    foreach ($row as $field => $value) {
                        if (in_array($field, ['dokumen', 'dokumen_memo'], true) && is_string($value) && $value !== '') {
                            $key = 'public:'.$value;
                            $assets[$key]['disk'] = 'public';
                            $assets[$key]['path'] = $value;
                            $assets[$key]['references'][] = [$module, $table, $row['id'], $field];
                        }
                        if (in_array($field, ['butir_'.$module, 'tanggapan', 'tindak_lanjut', 'hasil_kompilasi', 'hasil_review'], true) && is_string($value)) {
                            foreach (app(SnpButirContent::class)->images($value) as $image) {
                                $key = 'local:'.$image['path'];
                                $assets[$key]['disk'] = 'local';
                                $assets[$key]['path'] = $image['path'];
                                $assets[$key]['references'][] = [$module, $table, $row['id'], $field];
                            }
                        }
                    }
                }
            }
        }
        foreach ($assets as &$asset) {
            $path = $asset['path'];
            $safe = ! str_contains($path, '..') && ! str_contains($path, ':') && ! str_starts_with($path, '/') && ! str_contains($path, '\\');
            $file = rtrim($storage, '/\\').'/'.($asset['disk'] === 'local' ? 'private' : 'public').'/'.$path;
            $asset['available'] = $safe && is_file($file);
            $asset['sha256'] = $asset['available'] ? hash_file('sha256', $file) : null;
        }
        unset($asset);
        file_put_contents(base_path('tmp/r7/storage-inventory.json'), json_encode($assets, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    expect(config('sidewas.alur_revisi_enabled'))->toBeFalse();
});

test('migrasi dump MySQL mempertahankan seluruh nilai sumber dan memisahkan tiga putaran', function () {
    $directory = getenv('SIDEWAS_PUTARAN_DUMP_DIR');
    if (! $directory) {
        $this->markTestSkipped('Set SIDEWAS_PUTARAN_DUMP_DIR untuk salinan dump SNP dan DJSN.');
    }

    $snapshots = [];
    foreach (['snp' => 'sidewasi_sidewas_snp (1).sql', 'djsn' => 'sidewasi_sidewas_djsn.sql'] as $module => $filename) {
        $sql = file_get_contents($directory.DIRECTORY_SEPARATOR.$filename);
        expect($sql)->not->toBeFalse();

        if (preg_match('/^\s*(?:USE\b|(?:CREATE|DROP)\s+DATABASE\b|SOURCE\b|DELIMITER\b)/im', $sql)) {
            throw new RuntimeException('Dump pengujian hanya boleh berisi objek di database pengujian yang dipilih.');
        }

        $db = DB::connection('mysql_'.$module);
        $db->unprepared($sql);
        foreach (Schema::connection('mysql_'.$module)->getTableListing($db->getDatabaseName(), schemaQualified: false) as $table) {
            $snapshots[$module][$table] = [
                'columns' => Schema::connection('mysql_'.$module)->getColumnListing($table),
                'rows' => $db->table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            ];
        }
    }

    $this->migration->up();
    $this->migration->up();
    $closure = require database_path('migrations/2026_10_22_100320_add_snp_djsn_letter_closure_metadata.php');
    $closure->up();
    $closure->up();
    $assignments = require database_path('migrations/2026_10_22_132318_add_unit_assignments_to_surat_putaran.php');
    $assignments->up();
    $assignments->up();
    $reviewChoices = require database_path('migrations/2026_10_22_085515_expand_snp_djsn_review_status_choices.php');
    $reviewChoices->up();
    $reviewChoices->up();

    foreach ($snapshots as $module => $tables) {
        $db = DB::connection('mysql_'.$module);
        foreach ($tables as $table => $snapshot) {
            expect($db->table($table)->select($snapshot['columns'])->orderBy('id')->get()->map(fn ($row) => (array) $row)->all())
                ->toEqual($snapshot['rows']);
        }

        expect(array_sum((new SuratPutaranBackfill)->run('mysql_'.$module)))->toBe(0)
            ->and($db->table('tb_record')->whereNotNull('putaran_aktif_id')->count())->toBe(0);
        foreach (['tb_tanggapan', 'tb_kompilasi', 'tb_review', 'tb_tindak_lanjut'] as $table) {
            expect($db->table($table)->whereNull('putaran_id')->count())->toBe(0);
        }

        $class = $module === 'snp' ? SnpRecord::class : DjsnRecord::class;
        $butir = $db->table('tb_butir_'.$module.' as b')->join('tb_butir_pic as p', 'b.id_butir_'.$module, '=', 'p.id_butir_'.$module)
            ->whereNotNull('p.unit_kerja_id')->whereIn('p.jenis_pic', ['utama', 'pendukung'])
            ->select(['b.id_'.$module.' as record_key', 'b.id_butir_'.$module.' as butir_key', 'p.id as pic_id', 'p.unit_kerja_id'])->first();
        expect($butir)->not->toBeNull();
        $record = $class::where('id_'.$module, $butir->record_key)->firstOrFail();
        $workflow = new SuratPutaranService;
        $previous = [];

        foreach ([1, 2, 3] as $number) {
            $putaran = $workflow->buka($record, 901);
            $response = $putaran->tanggapan()->create([
                'id_butir_'.$module => $butir->butir_key, 'butir_pic_id' => $butir->pic_id,
                'unit_kerja_id' => $butir->unit_kerja_id, 'tanggapan' => 'Jawaban uji putaran '.$number,
                'created_by' => 901, 'updated_by' => 901,
            ]);
            $compilation = $putaran->kompilasi()->create([
                'id_butir_'.$module => $butir->butir_key, 'tahap_kompilasi' => 'tanggapan',
                'hasil_kompilasi' => '<p>Hasil kompilasi '.$number.'</p>', 'created_by' => 902,
            ]);
            $review = $putaran->reviu()->create([
                'id_butir_'.$module => $butir->butir_key, 'tahap_review' => 'tanggapan',
                'id_tanggapan' => $response->id, 'kompilasi_id' => $compilation->id,
                'hasil_review' => '<p>Hasil reviu '.$number.'</p>', 'created_by' => 903,
            ]);

            expect($review->kompilasiPutaran->id)->toBe($compilation->id)
                ->and($response->putaran->nomor_putaran)->toBe($number)
                ->and($putaran->tanggapan()->count())->toBe(1);
            foreach (array_keys(ReviuStatus::options()) as $choice) {
                $db->table('tb_review')->where('id', $review->id)->update(['status' => $choice]);
                expect($review->fresh()->status)->toBe($choice);
            }
            foreach ($previous as [$model, $snapshot]) {
                expect($model->fresh()->getRawOriginal())->toEqual($snapshot);
                expect(fn () => $model->update(['tanggapan' => 'Dilarang menimpa']))->toThrow(LogicException::class);
            }
            $previous[] = [$response, $response->fresh()->getRawOriginal()];
            $workflow->arsipkan($putaran, 903);
        }

        expect($record->putaran()->pluck('nomor_putaran')->all())->toBe([0, 1, 2, 3]);
        expect(fn () => $reviewChoices->down())->toThrow(RuntimeException::class);
        foreach ($tables as $table => $snapshot) {
            if ($table === 'tb_record') {
                continue;
            }
            $ids = array_column($snapshot['rows'], 'id');
            $this->assertEquals($snapshot['rows'], $db->table($table)->whereIn('id', $ids)->select($snapshot['columns'])->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(), $module.'.'.$table.' berubah pada baris sumber lama');
        }
    }

    $beforeStatusMigration = [];
    foreach (['snp', 'djsn'] as $module) {
        $db = DB::connection('mysql_'.$module);
        $db->table('tb_record')->whereNotNull('putaran_aktif_id')->update(['status' => 'proses_tindak_lanjut_belum_sesuai']);
        $beforeStatusMigration[$module] = $db->table('tb_record')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
    }
    $statusMigration = require database_path('migrations/2026_10_21_141454_enable_snp_djsn_initial_letter_statuses.php');
    $statusMigration->up();
    $statusMigration->up();
    foreach ($beforeStatusMigration as $module => $rows) {
        $db = DB::connection('mysql_'.$module);
        $columns = array_diff(array_keys($rows[0]), ['status']);
        $withoutStatus = fn (array $row): array => array_intersect_key($row, array_flip($columns));
        expect($db->table('tb_record')->orderBy('id')->get()->map(fn ($row) => $withoutStatus((array) $row))->all())
            ->toEqual(array_map($withoutStatus, $rows));
        expect($db->table('tb_record')->whereNotNull('putaran_aktif_id')->value('status'))->toBe('proses_tindak_lanjut_belum_sesuai')
            ->and($db->table('tb_record')->where('status', 'dalam_proses')->count())->toBe(0)
            ->and((new SuratAwalStatusService)->synchronizeModule($module))->toBe(0);
    }
    expect(fn () => $statusMigration->down())->toThrow(RuntimeException::class);
    expect(fn () => $this->migration->down())->toThrow(RuntimeException::class);
});

test('R1 sampai R5 pada dump MySQL mempertahankan sumber tanggal lama dan bahan tanpa perkiraan', function () {
    $directory = getenv('SIDEWAS_PUTARAN_DUMP_DIR');
    if (! $directory) {
        $this->markTestSkipped('Set SIDEWAS_PUTARAN_DUMP_DIR untuk salinan dump SNP dan DJSN.');
    }
    foreach (['snp' => 'sidewasi_sidewas_snp (1).sql', 'djsn' => 'sidewasi_sidewas_djsn.sql'] as $module => $filename) {
        $sql = file_get_contents($directory.DIRECTORY_SEPARATOR.$filename);
        if (preg_match('/^\s*(?:USE\b|(?:CREATE|DROP)\s+DATABASE\b|SOURCE\b|DELIMITER\b)/im', $sql)) {
            throw new RuntimeException('Dump hanya boleh berisi objek dalam database pengujian yang dipilih.');
        }
        DB::connection('mysql_'.$module)->unprepared($sql);
    }
    $this->migration->up();
    (require database_path('migrations/2026_10_22_100320_add_snp_djsn_letter_closure_metadata.php'))->up();
    (require database_path('migrations/2026_10_22_132318_add_unit_assignments_to_surat_putaran.php'))->up();
    $snapshots = [];
    foreach (['snp', 'djsn'] as $module) {
        $db = DB::connection('mysql_'.$module);
        foreach (['tb_record', 'tb_butir_'.$module, 'tb_butir_pic', 'tb_surat_putaran', 'tb_tanggapan', 'tb_tindak_lanjut', 'tb_kompilasi', 'tb_review'] as $table) {
            $snapshots[$module][$table] = [
                'columns' => Schema::connection('mysql_'.$module)->getColumnListing($table),
                'rows' => $db->table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            ];
        }
    }
    $stages = require database_path('migrations/2026_10_23_094342_add_snp_djsn_process_stage_foundation.php');
    $stages->up();
    $stages->up();
    $entries = require database_path('migrations/2026_10_23_102718_add_snp_djsn_stage_unit_entries.php');
    $entries->up();
    $entries->up();
    $captures = require database_path('migrations/2026_10_23_131534_add_snp_djsn_compilation_snapshots.php');
    $captures->up();
    $captures->up();
    $dates = require database_path('migrations/2026_10_23_143129_add_snp_djsn_stage_deadline_audit.php');
    $dates->up();
    $dates->up();
    foreach ($snapshots as $module => $tables) {
        $db = DB::connection('mysql_'.$module);
        foreach ($tables as $table => $snapshot) {
            expect($db->table($table)->select($snapshot['columns'])->orderBy('id')->get()->map(fn ($row) => (array) $row)->all())->toEqual($snapshot['rows']);
        }
        expect(array_sum((new SuratTahapBackfill)->run('mysql_'.$module)))->toBe(0)
            ->and($db->table('tb_record')->whereNotNull('tahap_aktif_id')->count())->toBe(0)
            ->and($db->table('tb_kompilasi_bahan')->count())->toBe(0)
            ->and($db->table('tb_kompilasi')->where('status_bahan', '!=', 'perlu_rekonsiliasi')->count())->toBe(0)
            ->and($db->table('tb_tanggapan')->whereNull('tahap_id')->count())->toBe(0)
            ->and($db->table('tb_tindak_lanjut')->whereNull('tahap_id')->count())->toBe(0)
            ->and($db->table('tb_surat_rekonsiliasi')->exists())->toBeTrue();
    }
    expect(DB::connection('mysql_djsn')->table('tb_surat_tahap')->where('tahap_proses', 'tindak_lanjut')->whereNull('nomor_putaran')->exists())->toBeTrue();
    expect(DB::connection('mysql_djsn')->table('tb_tindak_lanjut')->whereNotNull('putaran_tl')->count())->toBe(0)
        ->and(DB::connection('mysql_djsn')->table('tb_isian_unit_tahap')->count())->toBe(0);
    foreach (['snp', 'djsn'] as $module) {
        expect(DB::connection('mysql_'.$module)->table('tb_kompilasi')->whereNotNull('salinan_konteks')->count())->toBe(0);
        expect(DB::connection('mysql_'.$module)->table('tb_surat_tanggal_riwayat')->count())->toBe(0)
            ->and(DB::connection('mysql_'.$module)->table('tb_surat_tahap')->whereNotNull('tanggal_ditetapkan_at')->count())->toBe(0)
            ->and(DB::connection('mysql_'.$module)->table('tb_tanggapan')->whereNotNull('submitted_late_at')->count())->toBe(0);
    }
    $dates->down();
    $captures->down();
    $entries->down();
    $stages->down();
    foreach ($snapshots as $module => $tables) {
        foreach ($tables as $table => $snapshot) {
            expect(DB::connection('mysql_'.$module)->table($table)->select($snapshot['columns'])->orderBy('id')->get()->map(fn ($row) => (array) $row)->all())->toEqual($snapshot['rows']);
        }
    }
});

test('fondasi MySQL dapat dipasang dan dibatalkan pada skema instalasi baru', function () {
    (require database_path('migrations/2026_05_06_101519_create_snp_tables.php'))->up();
    (require database_path('migrations/2026_06_03_093422_create_djsn_tables.php'))->up();
    $this->migration->up();
    $closure = require database_path('migrations/2026_10_22_100320_add_snp_djsn_letter_closure_metadata.php');
    $closure->up();
    $assignments = require database_path('migrations/2026_10_22_132318_add_unit_assignments_to_surat_putaran.php');
    $assignments->up();
    $stages = require database_path('migrations/2026_10_23_094342_add_snp_djsn_process_stage_foundation.php');
    $stages->up();
    $stages->up();
    $entries = require database_path('migrations/2026_10_23_102718_add_snp_djsn_stage_unit_entries.php');
    $entries->up();
    $entries->up();
    $captures = require database_path('migrations/2026_10_23_131534_add_snp_djsn_compilation_snapshots.php');
    $captures->up();
    $captures->up();
    foreach (['snp', 'djsn'] as $module) {
        expect(Schema::connection('mysql_'.$module)->hasColumn('tb_tanggapan', 'putaran_id'))->toBeTrue()
            ->and(SuratPutaran::on('mysql_'.$module)->count())->toBe(0);
    }
    $captures->down();
    $entries->down();
    $stages->down();
    $assignments->down();
    $closure->down();
    $this->migration->down();
    $this->migration->up();
    $closure->up();
    $assignments->up();
    foreach (['snp', 'djsn'] as $module) {
        expect(Schema::connection('mysql_'.$module)->hasTable('tb_surat_putaran'))->toBeTrue();
    }
});

test('MySQL applies only one final or correction decision when reviews of the same letter are submitted concurrently', function (bool $sameReview, string $firstDecision, string $secondDecision) {
    (require database_path('migrations/2026_05_06_101519_create_snp_tables.php'))->up();
    (require database_path('migrations/2026_06_03_093422_create_djsn_tables.php'))->up();
    $this->migration->up();
    (require database_path('migrations/2026_10_21_141454_enable_snp_djsn_initial_letter_statuses.php'))->up();
    (require database_path('migrations/2026_10_22_085515_expand_snp_djsn_review_status_choices.php'))->up();
    (require database_path('migrations/2026_10_22_100320_add_snp_djsn_letter_closure_metadata.php'))->up();
    (require database_path('migrations/2026_10_22_132318_add_unit_assignments_to_surat_putaran.php'))->up();

    $worker = <<<'PHP'
        require $argv[1].'/vendor/autoload.php';
        $app = require $argv[1].'/bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $module = $argv[3];
        $connection = 'mysql_'.$module;
        $settings = json_decode($argv[2], true);
        config(['database.connections.'.$connection => $settings, 'database.connections.mysql' => $settings, 'database.default' => $connection, 'sidewas.reviu_putaran_enabled' => true]);
        Illuminate\Support\Facades\DB::purge($connection);
        Illuminate\Support\Facades\DB::purge('mysql');
        Illuminate\Support\Facades\Auth::shouldReceive('check')->andReturn(true);
        Illuminate\Support\Facades\Auth::shouldReceive('id')->andReturn(999);
        $user = Mockery::mock(App\Models\User::class)->makePartial();
        $user->id = 999;
        $user->shouldReceive('canReview'.ucfirst($module).'ByKomite')->andReturn(true);
        $class = $module === 'snp' ? App\Models\SnpReview::class : App\Models\DjsnReview::class;
        $review = $class::findOrFail((int) $argv[4]);
        fwrite(STDOUT, "ready\n");
        fflush(STDOUT);
        try {
            $next = app(App\Services\ReviuPutaranService::class)->review($review, $user, ['hasil_review' => 'Keputusan seluruh surat', 'status' => $argv[5]], Illuminate\Http\Request::create('/test-loop', 'PATCH'));
            fwrite(STDOUT, $next ? "opened\n" : "closed\n");
        } catch (Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            if ($exception->getStatusCode() !== 422) {
                throw $exception;
            }
            fwrite(STDOUT, "stale\n");
        }
        PHP;

    foreach (['snp', 'djsn'] as $module) {
        $connection = 'mysql_'.$module;
        Schema::connection($connection)->create('tb_log_activity', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            foreach (['type_code', 'database_name', 'table_name', 'record_key', 'action', 'ip_address'] as $column) {
                $table->string($column)->nullable();
            }
            $table->text('description')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamps();
        });
        $recordClass = $module === 'snp' ? SnpRecord::class : DjsnRecord::class;
        $butirClass = $module === 'snp' ? SnpButir::class : DjsnButir::class;
        $compilationClass = $module === 'snp' ? SnpKompilasi::class : DjsnKompilasi::class;
        $reviewClass = $module === 'snp' ? SnpReview::class : DjsnReview::class;
        $record = $recordClass::create(['nomor_surat' => 'CONCURRENT-REVIEW', 'tanggal_surat' => '2026-10-22']);
        $reviews = [];
        foreach ([1, 2] as $number) {
            $butir = $butirClass::create(['id_'.$module => $record->{'id_'.$module}, 'butir_'.$module => 'Butir '.$number]);
            $compilation = $compilationClass::create(['id_butir_'.$module => $butir->{'id_butir_'.$module}, 'hasil_kompilasi' => 'Kompilasi '.$number, 'tahap_kompilasi' => 'tanggapan', 'status' => 'dalam_proses_reviu_dewas', 'created_by' => 999]);
            $reviews[] = $reviewClass::create(['id_butir_'.$module => $butir->{'id_butir_'.$module}, 'kompilasi_id' => $compilation->id, 'putaran_id' => $compilation->putaran_id, 'tahap_review' => 'tanggapan', 'komite_id' => 1, 'status' => 'belum_ditanggapi', 'created_by' => 999]);
        }
        $db = DB::connection($connection);
        $processes = [];
        $db->beginTransaction();
        try {
            $db->table('tb_record')->where('id', $record->id)->lockForUpdate()->first();
            foreach ([$reviews[0]->id, $sameReview ? $reviews[0]->id : $reviews[1]->id] as $index => $reviewId) {
                $process = new Process([PHP_BINARY, '-r', $worker, base_path(), json_encode(config('database.connections.'.$connection)), $module, (string) $reviewId, $index ? $secondDecision : $firstDecision], base_path(), ['APP_ENV' => 'testing', 'LOG_CHANNEL' => 'stderr']);
                $process->setTimeout(30)->start();
                $processes[] = $process;
                awaitWorkflowWorker($process);
            }
            $db->commit();
            $outcomes = [];
            foreach ($processes as $process) {
                $process->wait();
                expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
                $outcomes[] = str_contains($process->getOutput(), 'opened') ? 'opened' : (str_contains($process->getOutput(), 'closed') ? 'closed' : 'stale');
            }
            sort($outcomes);
            expect($outcomes)->toBe(in_array('closed', $outcomes) ? ['closed', 'stale'] : ['opened', 'stale']);
            if (in_array('closed', $outcomes)) {
                expect($record->fresh()->status)->toBeIn([$firstDecision, $secondDecision])
                    ->and($record->fresh()->closed_by)->toBe(999)->and($record->fresh()->closed_at)->not->toBeNull()
                    ->and($record->fresh()->putaran()->pluck('nomor_putaran')->all())->toBe([0])
                    ->and($db->table('tb_log_activity')->where('action', 'close_surat_putaran')->count())->toBe(1)
                    ->and($db->table('tb_log_activity')->where('action', 'buka_putaran_perbaikan')->count())->toBe(0);
            } else {
                expect($record->fresh()->status)->toBe('proses_tindak_lanjut_belum_sesuai')
                    ->and($record->fresh()->closed_at)->toBeNull()
                    ->and($record->fresh()->putaran()->pluck('nomor_putaran')->all())->toBe([0, 1])
                    ->and($record->fresh()->putaranAktif->created_by)->toBe(999)
                    ->and($db->table('tb_log_activity')->where('action', 'buka_putaran_perbaikan')->count())->toBe(1);
            }
            expect($db->table('tb_review')->where('status', '!=', 'belum_ditanggapi')->count())->toBe(1);
        } finally {
            if ($db->transactionLevel() > 0) {
                $db->rollBack();
            }
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
        }
    }
})->with([true, false])->with([
    ['tindak_lanjut_belum_sesuai', 'tindak_lanjut_belum_sesuai'],
    ['tindak_lanjut_sesuai', 'tidak_dapat_ditindaklanjuti'],
    ['tidak_dapat_ditindaklanjuti', 'tindak_lanjut_belum_sesuai'],
]);

test('R2 MySQL mencegah dua akun atau PIC dari unit sama menyimpan isian bersamaan', function (bool $closed) {
    (require database_path('migrations/2026_05_06_101519_create_snp_tables.php'))->up();
    (require database_path('migrations/2026_06_03_093422_create_djsn_tables.php'))->up();
    $this->migration->up();
    (require database_path('migrations/2026_10_22_100320_add_snp_djsn_letter_closure_metadata.php'))->up();
    (require database_path('migrations/2026_10_22_132318_add_unit_assignments_to_surat_putaran.php'))->up();
    (require database_path('migrations/2026_10_23_094342_add_snp_djsn_process_stage_foundation.php'))->up();
    (require database_path('migrations/2026_10_23_102718_add_snp_djsn_stage_unit_entries.php'))->up();
    $worker = <<<'PHP'
        require $argv[1].'/vendor/autoload.php';
        $app = require $argv[1].'/bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $module = $argv[3];
        $connection = 'mysql_'.$module;
        $settings = json_decode($argv[2], true);
        config(['database.connections.'.$connection => $settings, 'database.connections.mysql' => $settings,
            'database.default' => $connection, 'sidewas.alur_revisi_enabled' => true]);
        Illuminate\Support\Facades\DB::purge($connection);
        Illuminate\Support\Facades\DB::purge('mysql');
        Illuminate\Support\Facades\Auth::shouldReceive('check')->andReturn(false);
        Illuminate\Support\Facades\Auth::shouldReceive('id')->andReturn(null);
        $stage = App\Models\SuratTahap::on($connection)->findOrFail((int) $argv[6]);
        $initial = $stage->tahap_proses === App\Enums\TahapProses::TanggapanAwal;
        $class = $stage->relatedClass($initial ? 'Tanggapan' : 'TindakLanjut');
        fwrite(STDOUT, "ready\n");
        fflush(STDOUT);
        try {
            $class::create(['id_butir_'.$module => $argv[4], 'tahap_id' => $stage->id,
                'putaran_id' => $stage->putaran_asal_id, 'butir_pic_id' => (int) $argv[5],
                'unit_kerja_id' => 10, $initial ? 'tanggapan' : 'tindak_lanjut' => 'Isian bersamaan',
                'deliverables' => 'Hasil', 'created_by' => (int) $argv[7], ...($initial ? [] : ['putaran_tl' => $stage->nomor_putaran])]);
            fwrite(STDOUT, "created\n");
        } catch (LogicException $exception) {
            fwrite(STDOUT, "duplicate\n");
        }
        PHP;
    foreach (['snp', 'djsn'] as $module) {
        foreach ([false, true] as $followUp) {
            $connection = 'mysql_'.$module;
            $db = DB::connection($connection);
            $recordClass = $module === 'snp' ? SnpRecord::class : DjsnRecord::class;
            $butirClass = $module === 'snp' ? SnpButir::class : DjsnButir::class;
            $record = $recordClass::create(['nomor_surat' => 'R2-RACE-'.($followUp ? 'TL' : 'AWAL'), 'tanggal_surat' => '2026-10-23']);
            $butir = $butirClass::create(['id_'.$module => $record->{'id_'.$module}, 'butir_'.$module => 'Butir persaingan isian']);
            $round = (new SuratPutaranService)->legacyFor($connection, $record->fresh()->getRawOriginal());
            $stage = SuratTahap::factory()->forSurat($record)->state([
                'tahap_proses' => $followUp ? 'tindak_lanjut' : 'tanggapan_awal', 'nomor_putaran' => $followUp ? 1 : 0,
                'putaran_asal_id' => $round->id,
            ])->create();
            $record->forceFill(['tahap_aktif_id' => $stage->id])->save();
            if ($closed) {
                $record->forceFill(['status' => 'tindak_lanjut_sesuai', 'closed_at' => now(), 'closed_by' => 999, 'closed_putaran_id' => $round->id])->save();
            }
            $snapshot = $record->fresh()->getRawOriginal();
            $pics = [];
            foreach (['utama', 'pendukung'] as $kind) {
                $pics[] = $db->table('tb_butir_pic')->insertGetId(['id_butir_'.$module => $butir->{'id_butir_'.$module}, 'jenis_pic' => $kind, 'unit_kerja_id' => 10]);
            }
            $workers = [];
            $db->beginTransaction();
            try {
                $db->table('tb_record')->where('id', $record->id)->lockForUpdate()->first();
                foreach ($pics as $index => $pic) {
                    $process = new Process([PHP_BINARY, '-r', $worker, base_path(), json_encode(config('database.connections.'.$connection)),
                        $module, $butir->{'id_butir_'.$module}, (string) $pic, (string) $stage->id, (string) (999 + $index)],
                        base_path(), ['APP_ENV' => 'testing', 'LOG_CHANNEL' => 'stderr']);
                    $process->setTimeout(30)->start();
                    $workers[] = $process;
                    awaitWorkflowWorker($process);
                }
                $db->commit();
                $outcomes = [];
                foreach ($workers as $process) {
                    $process->wait();
                    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
                    $outcomes[] = str_contains($process->getOutput(), 'created') ? 'created' : 'duplicate';
                }
                sort($outcomes);
                $table = $followUp ? 'tb_tindak_lanjut' : 'tb_tanggapan';
                expect($outcomes)->toBe(['created', 'duplicate'])
                    ->and($db->table($table)->where('tahap_id', $stage->id)->count())->toBe(1)
                    ->and($db->table('tb_isian_unit_tahap')->where('tahap_id', $stage->id)->count())->toBe(1)
                    ->and($db->table($table)->where('tahap_id', $stage->id)->whereNotNull('submitted_after_close_at')->count())->toBe($closed ? 1 : 0)
                    ->and($record->fresh()->getRawOriginal())->toBe($snapshot);
            } finally {
                if ($db->transactionLevel() > 0) {
                    $db->rollBack();
                }
                foreach ($workers as $process) {
                    if ($process->isRunning()) {
                        $process->stop();
                    }
                }
            }
        }
    }
})->with([false, true]);

test('R3 MySQL membekukan bahan secara konsisten saat pengisian edit dan kompilasi bersamaan', function (string $operation, bool $inputFirst) {
    (require database_path('migrations/2026_05_06_101519_create_snp_tables.php'))->up();
    (require database_path('migrations/2026_06_03_093422_create_djsn_tables.php'))->up();
    $this->migration->up();
    (require database_path('migrations/2026_10_22_100320_add_snp_djsn_letter_closure_metadata.php'))->up();
    (require database_path('migrations/2026_10_22_132318_add_unit_assignments_to_surat_putaran.php'))->up();
    (require database_path('migrations/2026_10_23_094342_add_snp_djsn_process_stage_foundation.php'))->up();
    (require database_path('migrations/2026_10_23_102718_add_snp_djsn_stage_unit_entries.php'))->up();
    (require database_path('migrations/2026_10_23_131534_add_snp_djsn_compilation_snapshots.php'))->up();
    (require database_path('migrations/2026_10_23_143129_add_snp_djsn_stage_deadline_audit.php'))->up();
    config(['sidewas.alur_revisi_enabled' => true, 'sidewas.reviu_putaran_enabled' => true]);
    $worker = <<<'PHP'
        require $argv[1].'/vendor/autoload.php';
        $app = require $argv[1].'/bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $module = $argv[3];
        $connection = 'mysql_'.$module;
        $settings = json_decode($argv[2], true);
        config(['database.connections.'.$connection => $settings, 'database.connections.mysql' => $settings,
            'database.default' => 'mysql', 'sidewas.alur_revisi_enabled' => true, 'sidewas.reviu_putaran_enabled' => true]);
        Illuminate\Support\Facades\DB::purge($connection);
        Illuminate\Support\Facades\DB::purge('mysql');
        $user = App\Models\User::findOrFail((int) $argv[6]);
        Illuminate\Support\Facades\Auth::login($user);
        fwrite(STDOUT, "ready\n");
        fflush(STDOUT);
        $db = Illuminate\Support\Facades\DB::connection($connection);
        $db->beginTransaction();
        try {
            $class = $module === 'snp' ? App\Models\SnpButir::class : App\Models\DjsnButir::class;
            $butir = $class::findOrFail((int) $argv[4]);
            $record = $butir->record()->lockForUpdate()->firstOrFail();
            fwrite(STDOUT, "locked\n");
            fflush(STDOUT);
            if (trim(fgets(STDIN) ?: '') !== 'proceed') throw new RuntimeException('Missing concurrency gate');
            $stage = App\Models\SuratTahap::on($connection)->findOrFail((int) $argv[5]);
            $request = Illuminate\Http\Request::create('/r3-race', 'POST');
            if ($argv[7] === 'compile') {
                app(App\Services\KompilasiTahapService::class)->compile($butir, $user, [
                    'tahap_id' => $stage->id, 'putaran_id' => $stage->putaran_asal_id,
                    'tahap_kompilasi' => $stage->tahap_proses === App\Enums\TahapProses::TanggapanAwal ? 'tanggapan' : 'tindak_lanjut',
                    'hasil_kompilasi' => 'Kompilasi bersamaan',
                ], $request);
            } elseif ($argv[7] === 'create') {
                app(App\Services\IsianUnitTahapService::class)->create($butir, $user, $stage->tahap_proses, [
                    'tahap_id' => $stage->id, 'butir_pic_id' => (int) $argv[8], 'isi' => 'Isian bersamaan', 'deliverables' => 'Hasil',
                ], $request);
            } else {
                $source = $stage->relatedClass($stage->tahap_proses === App\Enums\TahapProses::TanggapanAwal ? 'Tanggapan' : 'TindakLanjut')::where('tahap_id', $stage->id)->firstOrFail();
                app(App\Services\IsianUnitTahapService::class)->update($source, $user, ['isi' => 'Edit bersamaan', 'deliverables' => 'Hasil edit'], $request);
            }
            $db->commit();
            fwrite(STDOUT, "saved\n");
        } catch (Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
        PHP;
    $waitForLock = function (Process $process): void {
        $deadline = microtime(true) + 10;
        while (! str_contains($process->getOutput(), "locked\n") && $process->isRunning() && microtime(true) < $deadline) {
            usleep(10_000);
        }
        expect(str_contains($process->getOutput(), "locked\n"))->toBeTrue($process->getOutput().$process->getErrorOutput());
    };
    foreach (['snp', 'djsn'] as $module) {
        $connection = 'mysql_'.$module;
        config(['database.connections.mysql' => config('database.connections.'.$connection), 'database.default' => 'mysql']);
        DB::purge('mysql');
        (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        (require database_path('migrations/2026_05_07_000000_create_main_custom_tables.php'))->up();
        foreach (['tb_direktorat', 'tb_unit_kerja'] as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->string('status')->default('active'));
        }
        DB::table('tb_direktorat')->insert(['id' => 1, 'nama_direktorat' => 'Direktorat Uji']);
        DB::table('tb_unit_kerja')->insert(['id' => 1, 'direktorat_id' => 1, 'nama_unit' => 'Unit A']);
        DB::table('tb_komite')->insert(['id' => 1, 'nama_komite' => 'Komite Uji']);
        $compiler = workflowUser('super_admin');
        $unit = workflowUser('pic_'.$module);
        foreach ([false, true] as $followUp) {
            $butir = workflowButir($module);
            $record = $butir->record->fresh();
            $round = app(SuratPutaranService::class)->legacyFor($connection, $record->getRawOriginal());
            if ($followUp) {
                $round = app(SuratPutaranService::class)->buka($record, $compiler->id);
            }
            $stage = (! $followUp ? $record->tahapanProses()->where('nomor_putaran', 0)->first() : null) ?? SuratTahap::factory()->forSurat($record)->state([
                'tahap_proses' => $followUp ? 'tindak_lanjut' : 'tanggapan_awal', 'nomor_putaran' => $followUp ? 1 : 0,
                'putaran_asal_id' => $round->id,
            ])->create();
            $record->forceFill(['tahap_aktif_id' => $stage->id])->save();
            $pic = $butir->butirPics()->where('jenis_pic', 'utama')->firstOrFail();
            if ($operation === 'edit') {
                Auth::login($unit);
                app(IsianUnitTahapService::class)->create($butir, $unit, $stage->tahap_proses, [
                    'tahap_id' => $stage->id, 'butir_pic_id' => $pic->id, 'isi' => 'Versi sebelum edit', 'deliverables' => 'Hasil asli',
                ], Request::create('/r3-initial', 'POST'));
            }
            $workers = [];
            $streams = [];
            try {
                foreach ($inputFirst ? [$operation, 'compile'] : ['compile', $operation] as $index => $kind) {
                    $streams[$index] = new InputStream;
                    $process = new Process([PHP_BINARY, '-r', $worker, base_path(), json_encode(config('database.connections.'.$connection)),
                        $module, (string) $butir->id, (string) $stage->id, (string) ($kind === 'compile' ? $compiler->id : $unit->id), $kind, (string) $pic->id],
                        base_path(), ['APP_ENV' => 'testing', 'LOG_CHANNEL' => 'stderr']);
                    $process->setInput($streams[$index])->setTimeout(40)->start();
                    $workers[$index] = $process;
                    awaitWorkflowWorker($process);
                    if ($index === 0) {
                        $waitForLock($process);
                    }
                }
                foreach ($workers as $index => $process) {
                    $streams[$index]->write("proceed\n");
                    $streams[$index]->close();
                    $process->wait();
                    expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput());
                }
                $compilation = $butir->kompilasis()->firstOrFail();
                $source = $stage->relatedClass($followUp ? 'TindakLanjut' : 'Tanggapan')::where('tahap_id', $stage->id)->firstOrFail();
                expect($butir->kompilasis()->count())->toBe(1)->and($butir->reviews()->count())->toBe(1);
                if ($operation === 'create') {
                    expect($compilation->bahan()->count())->toBe($inputFirst ? 1 : 0)
                        ->and($source->submitted_after_compilation_at !== null)->toBe(! $inputFirst);
                } else {
                    expect($compilation->bahan()->firstOrFail()->salinan['isi'])->toBe($inputFirst ? 'Edit bersamaan' : 'Versi sebelum edit')
                        ->and($source->changed_after_compilation_at !== null)->toBe(! $inputFirst);
                }
                expect($compilation->salinan_konteks['jumlah_bahan'])->toBe($operation === 'edit' || $inputFirst ? 1 : 0);
            } finally {
                foreach ($workers as $process) {
                    if ($process->isRunning()) {
                        $process->stop();
                    }
                }
                foreach ($streams as $stream) {
                    $stream->close();
                }
            }
        }
    }
})->with(['create', 'edit'])->with([true, false]);

test('R4 MySQL hanya menerima satu transisi saat reviu surat dikirim bersamaan', function (bool $sameReview, string $firstDecision, string $secondDecision) {
    (require database_path('migrations/2026_05_06_101519_create_snp_tables.php'))->up();
    (require database_path('migrations/2026_06_03_093422_create_djsn_tables.php'))->up();
    $this->migration->up();
    foreach (['2026_10_22_085515_expand_snp_djsn_review_status_choices.php', '2026_10_22_100320_add_snp_djsn_letter_closure_metadata.php',
        '2026_10_22_132318_add_unit_assignments_to_surat_putaran.php', '2026_10_23_094342_add_snp_djsn_process_stage_foundation.php',
        '2026_10_23_102718_add_snp_djsn_stage_unit_entries.php', '2026_10_23_131534_add_snp_djsn_compilation_snapshots.php',
        '2026_10_23_143129_add_snp_djsn_stage_deadline_audit.php'] as $migration) {
        (require database_path('migrations/'.$migration))->up();
    }
    config(['sidewas.alur_revisi_enabled' => true, 'sidewas.reviu_putaran_enabled' => true]);
    $worker = <<<'PHP'
        require $argv[1].'/vendor/autoload.php';
        $app = require $argv[1].'/bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $module = $argv[3];
        $connection = 'mysql_'.$module;
        $settings = json_decode($argv[2], true);
        config(['database.connections.'.$connection => $settings, 'database.connections.mysql' => $settings,
            'database.default' => 'mysql', 'sidewas.alur_revisi_enabled' => true, 'sidewas.reviu_putaran_enabled' => true]);
        Illuminate\Support\Facades\DB::purge($connection);
        Illuminate\Support\Facades\DB::purge('mysql');
        $user = App\Models\User::findOrFail((int) $argv[5]);
        Illuminate\Support\Facades\Auth::login($user);
        $db = Illuminate\Support\Facades\DB::connection($connection);
        $db->beginTransaction();
        $class = $module === 'snp' ? App\Models\SnpReview::class : App\Models\DjsnReview::class;
        $review = $class::findOrFail((int) $argv[4]);
        $review->load('butir.record');
        fwrite(STDOUT, "ready\n");
        fflush(STDOUT);
        try {
            $next = app(App\Services\ReviuPutaranService::class)->review($review, $user, [
                'hasil_review' => 'Keputusan bersamaan', 'status' => $argv[6], 'jatuh_tempo_tl' => now('Asia/Jakarta')->addDays(14)->toDateString(),
                ...isset($argv[7]) ? ['status_pengajuan_tgl' => $argv[7], 'alasan_keputusan_tgl' => 'Keputusan tanggal bersamaan'] : [],
            ], Illuminate\Http\Request::create('/r4-race', 'PATCH'));
            $db->commit();
            fwrite(STDOUT, isset($argv[7]) ? "date_saved\n" : ($next ? "opened\n" : "closed\n"));
        } catch (Illuminate\Validation\ValidationException $exception) {
            $db->rollBack();
            fwrite(STDOUT, "stale\n");
        } catch (Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $db->rollBack();
            if ($exception->getStatusCode() !== 422) throw $exception;
            fwrite(STDOUT, "stale\n");
        }
        PHP;
    foreach (['snp', 'djsn'] as $module) {
        $connection = 'mysql_'.$module;
        config(['database.connections.mysql' => config('database.connections.'.$connection), 'database.default' => 'mysql']);
        DB::purge('mysql');
        (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        (require database_path('migrations/2026_05_07_000000_create_main_custom_tables.php'))->up();
        foreach (['tb_direktorat', 'tb_unit_kerja'] as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->string('status')->default('active'));
        }
        DB::table('tb_direktorat')->insert(['id' => 1, 'nama_direktorat' => 'Direktorat Uji']);
        DB::table('tb_unit_kerja')->insert(['id' => 1, 'direktorat_id' => 1, 'nama_unit' => 'Unit A']);
        DB::table('tb_komite')->insert(['id' => 1, 'nama_komite' => 'Komite Uji']);
        $actor = workflowUser('super_admin');
        Auth::login($actor);
        foreach ([false, true] as $followUp) {
            $first = workflowButir($module);
            $other = workflowButir($module);
            $other->forceFill(['id_'.$module => $first->record->{'id_'.$module}])->save();
            $record = $first->record->fresh();
            $compile = function ($butir, ?SuratTahap $stage = null) use ($actor): Model {
                $compilation = app(KompilasiTahapService::class)->compile($butir, $actor, [
                    'tahap_id' => $stage?->id, 'putaran_id' => $stage?->putaran_asal_id,
                    'tahap_kompilasi' => $stage?->tahap_proses === TahapProses::TindakLanjut ? 'tindak_lanjut' : 'tanggapan',
                    'hasil_kompilasi' => 'Kompilasi untuk uji transisi',
                    ...$stage?->tahap_proses === TahapProses::TindakLanjut
                        ? ['ubah_tgl' => now('Asia/Jakarta')->addDays(21)->toDateString(), 'alasan_pengajuan_tgl' => 'Bahan putaran perlu waktu tambahan'] : [],
                ], Request::create('/r4-compile', 'POST'));

                return $butir->reviews()->where('kompilasi_id', $compilation->id)->firstOrFail();
            };
            $initialReview = $compile($first);
            if ($followUp) {
                app(ReviuPutaranService::class)->review($initialReview, $actor, [
                    'status' => 'tindak_lanjut_belum_sesuai', 'hasil_review' => 'Buka Tindak Lanjut', 'jatuh_tempo_tl' => now('Asia/Jakarta')->addDays(14)->toDateString(),
                ], Request::create('/r4-initial-review', 'PATCH'));
                $stage = $record->fresh()->tahapAktif;
                $firstReview = $compile($first->fresh(), $stage);
            } else {
                $firstReview = $initialReview;
                $stage = $record->fresh()->tahapAktif;
            }
            $otherReview = $compile($other->fresh(), $stage);
            if ($followUp) {
                $workers = [];
                $db = $first->getConnection();
                $db->beginTransaction();
                try {
                    $db->table('tb_record')->where('id', $record->id)->lockForUpdate()->first();
                    foreach (['disetujui', 'ditolak'] as $decision) {
                        $process = new Process([PHP_BINARY, '-r', $worker, base_path(), json_encode(config('database.connections.'.$connection)),
                            $module, (string) $firstReview->id, (string) $actor->id, 'belum_ditanggapi', $decision], base_path(), ['APP_ENV' => 'testing', 'LOG_CHANNEL' => 'stderr']);
                        $process->setTimeout(40)->start();
                        $workers[] = $process;
                        awaitWorkflowWorker($process);
                    }
                    $db->commit();
                    $outcomes = [];
                    foreach ($workers as $process) {
                        $process->wait();
                        expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput());
                        $outcomes[] = str_contains($process->getOutput(), 'date_saved') ? 'date_saved' : 'stale';
                    }
                    sort($outcomes);
                    expect($outcomes)->toBe(['date_saved', 'stale']);
                    $compilation = $firstReview->kompilasiPutaran()->firstOrFail();
                    expect($stage->riwayatTanggal()->where('kompilasi_id', $compilation->id)->count())->toBe(1)
                        ->and($stage->fresh()->jatuh_tempo->toDateString())->toBe(now('Asia/Jakarta')->addDays($compilation->status_pengajuan_tgl === 'disetujui' ? 21 : 14)->toDateString());
                } finally {
                    if ($db->transactionLevel() > 0) {
                        $db->rollBack();
                    }
                    foreach ($workers as $process) {
                        if ($process->isRunning()) {
                            $process->stop();
                        }
                    }
                }
            }
            $beforeStages = $record->tahapanProses()->count();
            $db = $first->getConnection();
            $workers = [];
            $db->beginTransaction();
            try {
                $db->table('tb_record')->where('id', $record->id)->lockForUpdate()->first();
                foreach ([$firstReview->id, $sameReview ? $firstReview->id : $otherReview->id] as $index => $id) {
                    $process = new Process([PHP_BINARY, '-r', $worker, base_path(), json_encode(config('database.connections.'.$connection)),
                        $module, (string) $id, (string) $actor->id, $index ? $secondDecision : $firstDecision], base_path(), ['APP_ENV' => 'testing', 'LOG_CHANNEL' => 'stderr']);
                    $process->setTimeout(40)->start();
                    $workers[] = $process;
                    awaitWorkflowWorker($process);
                }
                $db->commit();
                $outcomes = [];
                foreach ($workers as $process) {
                    $process->wait();
                    expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput());
                    $outcomes[] = str_contains($process->getOutput(), 'opened') ? 'opened' : (str_contains($process->getOutput(), 'closed') ? 'closed' : 'stale');
                }
                sort($outcomes);
                $closed = in_array('closed', $outcomes);
                expect($outcomes)->toBe($closed ? ['closed', 'stale'] : ['opened', 'stale'])
                    ->and($record->tahapanProses()->count())->toBe($beforeStages + ($closed ? 0 : 1))
                    ->and($record->fresh()->closed_at !== null)->toBe($closed)
                    ->and($stage->reviu()->where('status', '!=', 'belum_ditanggapi')->count())->toBe(1)
                    ->and($stage->fresh()->archived_at !== null)->toBe(! $closed);
                if (! $closed) {
                    expect($record->fresh()->status)->toBe('proses_tindak_lanjut_belum_sesuai')
                        ->and($record->fresh()->tahapAktif->tahap_proses)->toBe(TahapProses::TindakLanjut)
                        ->and($record->fresh()->tahapAktif->nomor_putaran)->toBe($followUp ? 2 : 1);
                    expect($record->fresh()->tahapAktif->jatuh_tempo->toDateString())->toBe(now('Asia/Jakarta')->addDays(14)->toDateString())
                        ->and($record->fresh()->tahapAktif->riwayatTanggal()->where('jenis', 'penetapan_tindak_lanjut')->count())->toBe(1);
                }
            } finally {
                if ($db->transactionLevel() > 0) {
                    $db->rollBack();
                }
                foreach ($workers as $process) {
                    if ($process->isRunning()) {
                        $process->stop();
                    }
                }
            }
        }
    }
})->with([true, false])->with([
    ['tindak_lanjut_belum_sesuai', 'tindak_lanjut_belum_sesuai'],
    ['tindak_lanjut_sesuai', 'tidak_dapat_ditindaklanjuti'],
    ['tidak_dapat_ditindaklanjuti', 'tindak_lanjut_belum_sesuai'],
]);

test('MySQL serializes concurrent responses by unit including late completion after Close', function (bool $closed) {
    (require database_path('migrations/2026_05_06_101519_create_snp_tables.php'))->up();
    (require database_path('migrations/2026_06_03_093422_create_djsn_tables.php'))->up();
    $this->migration->up();
    (require database_path('migrations/2026_10_22_085515_expand_snp_djsn_review_status_choices.php'))->up();
    (require database_path('migrations/2026_10_22_100320_add_snp_djsn_letter_closure_metadata.php'))->up();
    (require database_path('migrations/2026_10_22_132318_add_unit_assignments_to_surat_putaran.php'))->up();
    $worker = <<<'PHP'
        require $argv[1].'/vendor/autoload.php';
        $app = require $argv[1].'/bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $module = $argv[3];
        $connection = 'mysql_'.$module;
        config(['database.connections.'.$connection => json_decode($argv[2], true), 'database.default' => $connection]);
        Illuminate\Support\Facades\DB::purge($connection);
        $class = $module === 'snp' ? App\Models\SnpTanggapan::class : App\Models\DjsnTanggapan::class;
        fwrite(STDOUT, "ready\n");
        fflush(STDOUT);
        try {
            $class::create(['id_butir_'.$module => $argv[4], 'butir_pic_id' => (int) $argv[5], 'tanggapan' => 'Concurrent response', 'deliverables' => 'Hasil', 'created_by' => 999]);
            fwrite(STDOUT, "created\n");
        } catch (LogicException $exception) {
            fwrite(STDOUT, "duplicate\n");
        }
        PHP;

    foreach (['snp', 'djsn'] as $module) {
        $recordClass = $module === 'snp' ? SnpRecord::class : DjsnRecord::class;
        $butirClass = $module === 'snp' ? SnpButir::class : DjsnButir::class;
        $record = $recordClass::create(['nomor_surat' => 'CONCURRENT', 'tanggal_surat' => '2026-10-22', 'perihal_surat' => 'Surat uji']);
        $butir = $butirClass::create(['id_'.$module => $record->{'id_'.$module}, 'butir_'.$module => 'Butir uji']);
        $db = DB::connection('mysql_'.$module);
        $pics = [];
        foreach (['utama', 'pendukung'] as $jenis) {
            $pics[] = $db->table('tb_butir_pic')->insertGetId(['id_butir_'.$module => $butir->{'id_butir_'.$module}, 'jenis_pic' => $jenis, 'unit_kerja_id' => 10]);
        }
        if ($closed) {
            $compilationClass = $module === 'snp' ? SnpKompilasi::class : DjsnKompilasi::class;
            $reviewClass = $module === 'snp' ? SnpReview::class : DjsnReview::class;
            $compilation = $compilationClass::create(['id_butir_'.$module => $butir->{'id_butir_'.$module}, 'hasil_kompilasi' => 'Bahan final', 'tahap_kompilasi' => 'tanggapan', 'created_by' => 999]);
            $review = $reviewClass::create(['id_butir_'.$module => $butir->{'id_butir_'.$module}, 'kompilasi_id' => $compilation->id, 'putaran_id' => $compilation->putaran_id, 'tahap_review' => 'tanggapan', 'status' => 'tindak_lanjut_sesuai', 'hasil_review' => 'Keputusan final', 'created_by' => 999]);
            $record->forceFill(['status' => 'tindak_lanjut_sesuai', 'closed_at' => now(), 'closed_by' => 999, 'closed_review_id' => $review->id, 'closed_putaran_id' => $review->putaran_id])->save();
        }
        $snapshot = $record->fresh()->getRawOriginal();
        $processes = [];
        $db->beginTransaction();
        try {
            $db->table('tb_record')->where('id', $record->id)->lockForUpdate()->first();
            foreach ($pics as $pic) {
                $process = new Process([PHP_BINARY, '-r', $worker, base_path(), json_encode(config('database.connections.mysql_'.$module)), $module, $butir->{'id_butir_'.$module}, (string) $pic], base_path(), ['APP_ENV' => 'testing', 'LOG_CHANNEL' => 'stderr']);
                $process->setTimeout(30)->start();
                $processes[] = $process;
                awaitWorkflowWorker($process);
            }
            $db->commit();
            $outcomes = [];
            foreach ($processes as $process) {
                $process->wait();
                expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
                $outcomes[] = str_contains($process->getOutput(), 'created') ? 'created' : 'duplicate';
            }
            sort($outcomes);
            expect($outcomes)->toBe(['created', 'duplicate'])->and($db->table('tb_tanggapan')->count())->toBe(1);
            expect($db->table('tb_tanggapan')->whereNotNull('submitted_after_close_at')->count())->toBe($closed ? 1 : 0)
                ->and($record->fresh()->getRawOriginal())->toBe($snapshot);
        } finally {
            if ($db->transactionLevel() > 0) {
                $db->rollBack();
            }
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
        }
    }
})->with([false, true]);
