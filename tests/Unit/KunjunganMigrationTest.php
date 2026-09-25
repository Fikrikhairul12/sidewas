<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $capsule = new Capsule;
    $connection = [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ];

    $capsule->addConnection($connection, 'sqlite');
    $capsule->addConnection($connection, 'mysql_kunjungan');
    $capsule->getDatabaseManager()->setDefaultConnection('sqlite');
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
    $container = $capsule->getContainer();
    $container->instance('db', $capsule->getDatabaseManager());
    $container->bind('db.schema', fn ($app) => $app['db']->connection()->getSchemaBuilder());
    Facade::setFacadeApplication($container);
});

afterEach(function () {
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication(null);
});

test('report workflow migration enables versioned report review', function () {
    $schema = Schema::connection('mysql_kunjungan');
    $schema->create('visit_reports', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('visit_id');
        $table->unsignedInteger('version');
        $table->string('original_filename');
        $table->string('stored_filename');
        $table->string('file_path');
        $table->string('mime_type');
        $table->unsignedBigInteger('file_size');
        $table->unsignedBigInteger('uploaded_by_user_id');
        $table->boolean('is_current')->default(true);
        $table->timestamp('uploaded_at');
        $table->timestamps();
        $table->unique('visit_id');
    });
    DB::connection('mysql_kunjungan')->table('visit_reports')->insert([
        'visit_id' => 1,
        'version' => 1,
        'original_filename' => 'laporan.pdf',
        'stored_filename' => 'laporan.pdf',
        'file_path' => 'laporan.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 100,
        'uploaded_by_user_id' => 1,
        'is_current' => true,
        'uploaded_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = require dirname(__DIR__, 2).'/database/migrations/2026_09_25_151347_add_report_review_workflow_to_visit_reports_table.php';
    $migration->up();

    expect($schema->hasColumns('visit_reports', [
        'status', 'reviewed_by_user_id', 'review_notes', 'reviewed_at',
    ]))->toBeTrue()
        ->and(DB::connection('mysql_kunjungan')->table('visit_reports')->value('status'))->toBe('APPROVED');

    DB::connection('mysql_kunjungan')->table('visit_reports')->insert([
        'visit_id' => 1,
        'version' => 2,
        'status' => 'PENDING',
        'original_filename' => 'laporan-revisi.pdf',
        'stored_filename' => 'laporan-revisi.pdf',
        'file_path' => 'laporan-revisi.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 120,
        'uploaded_by_user_id' => 1,
        'is_current' => true,
        'uploaded_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::connection('mysql_kunjungan')->table('visit_reports')->count())->toBe(2);
});

test('status log migration stores structured visit changes', function () {
    $schema = Schema::connection('mysql_kunjungan');
    $schema->create('visit_status_logs', function (Blueprint $table): void {
        $table->id();
        $table->text('notes')->nullable();
    });

    $migration = require dirname(__DIR__, 2).'/database/migrations/2026_09_25_151351_add_changes_to_visit_status_logs_table.php';
    $migration->up();

    expect($schema->hasColumn('visit_status_logs', 'changes'))->toBeTrue();
});

test('kunjungan admin role migration restores the brd monitoring role', function () {
    Schema::create('tb_role', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });
    Schema::create('tb_type', function (Blueprint $table): void {
        $table->id();
        $table->string('code');
    });
    Schema::create('tb_role_type', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('role_id');
        $table->unsignedBigInteger('type_id');
        $table->string('name');
        $table->string('keterangan')->nullable();
        $table->timestamps();
    });
    Schema::create('tb_user_role_type', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('role_type_id');
    });
    $roleId = DB::table('tb_role')->insertGetId(['name' => 'admin']);
    $typeId = DB::table('tb_type')->insertGetId(['code' => 'kunjungan']);

    $migration = require dirname(__DIR__, 2).'/database/migrations/2026_09_25_151356_add_admin_kunjungan_role_type.php';
    $migration->up();

    expect(DB::table('tb_role_type')->where([
        'role_id' => $roleId,
        'type_id' => $typeId,
        'name' => 'admin_kunjungan',
    ])->exists())->toBeTrue();
});
