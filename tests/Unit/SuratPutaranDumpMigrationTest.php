<?php

use App\Enums\ReviuStatus;
use App\Models\DjsnButir;
use App\Models\DjsnRecord;
use App\Models\SnpButir;
use App\Models\SnpRecord;
use App\Models\SuratPutaran;
use App\Services\SuratAwalStatusService;
use App\Services\SuratPutaranBackfill;
use App\Services\SuratPutaranService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

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
        if (preg_match('/\Asidewas_putaran_test_(snp|djsn)_[a-f0-9]{10}\z/', $database)) {
            $this->admin->exec('DROP DATABASE `'.$database.'`');
        }
    }
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

test('fondasi MySQL dapat dipasang dan dibatalkan pada skema instalasi baru', function () {
    (require database_path('migrations/2026_05_06_101519_create_snp_tables.php'))->up();
    (require database_path('migrations/2026_06_03_093422_create_djsn_tables.php'))->up();
    $this->migration->up();
    foreach (['snp', 'djsn'] as $module) {
        expect(Schema::connection('mysql_'.$module)->hasColumn('tb_tanggapan', 'putaran_id'))->toBeTrue()
            ->and(SuratPutaran::on('mysql_'.$module)->count())->toBe(0);
    }
    $this->migration->down();
    $this->migration->up();
    foreach (['snp', 'djsn'] as $module) {
        expect(Schema::connection('mysql_'.$module)->hasTable('tb_surat_putaran'))->toBeTrue();
    }
});

test('MySQL serializes concurrent responses by unit even for different PIC assignments', function () {
    (require database_path('migrations/2026_05_06_101519_create_snp_tables.php'))->up();
    (require database_path('migrations/2026_06_03_093422_create_djsn_tables.php'))->up();
    $this->migration->up();
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
        $processes = [];
        $db->beginTransaction();
        try {
            $db->table('tb_record')->where('id', $record->id)->lockForUpdate()->first();
            foreach ($pics as $pic) {
                $process = new Process([PHP_BINARY, '-r', $worker, base_path(), json_encode(config('database.connections.mysql_'.$module)), $module, $butir->{'id_butir_'.$module}, (string) $pic], base_path(), ['APP_ENV' => 'testing', 'LOG_CHANNEL' => 'stderr']);
                $process->setTimeout(30)->start();
                $processes[] = $process;
                expect($process->waitUntil(fn ($type, $output): bool => str_contains($output, 'ready')))->toBeTrue($process->getErrorOutput());
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
});
