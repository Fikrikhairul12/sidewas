<?php

use App\Services\SharedClusterCatalog;
use Database\Seeders\SnpMasterSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config()->set('database.default', 'mysql');
    foreach (['mysql', ...array_map(fn ($module) => 'mysql_'.$module, SharedClusterCatalog::MODULES)] as $connection) {
        config()->set('database.connections.'.$connection, array_merge(config('database.connections.sqlite'), ['database' => ':memory:']));
        DB::purge($connection);
    }
    Schema::create('tb_log_activity', function (Blueprint $table) {
        $table->id();
        $table->string('type_code');
        $table->string('table_name');
        $table->string('record_key');
        $table->string('action');
    });
    foreach (SharedClusterCatalog::MODULES as $module) {
        $schema = Schema::connection('mysql_'.$module);
        $schema->create('tb_cluster', function (Blueprint $table) {
            $table->id();
            $table->string('nama_cluster');
            $table->string('status')->default('active');
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
        $schema->create('tb_sub_cluster', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cluster_id')->constrained('tb_cluster');
            $table->string('nama_sub_cluster');
            $table->string('status')->default('active');
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
        $schema->create('tb_record', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cluster_id')->constrained('tb_cluster');
            $table->foreignId('sub_cluster_id')->constrained('tb_sub_cluster');
        });
    }
    $this->migration = require database_path('migrations/2026_10_06_091418_share_cluster_catalog_across_modules.php');
});

test('migration merges names while preserving distinct local ids and historical references', function () {
    foreach (SharedClusterCatalog::MODULES as $index => $module) {
        $db = DB::connection('mysql_'.$module);
        $db->table('tb_cluster')->insert(['id' => 10 + $index, 'nama_cluster' => $index === 1 ? '  CLUSTER   Bersama  ' : 'Cluster Bersama', 'status' => $index === 2 ? 'inactive' : 'active']);
        $db->table('tb_sub_cluster')->insert(['id' => 20 + $index, 'cluster_id' => 10 + $index, 'nama_sub_cluster' => $index === 3 ? 'SUB Bersama' : 'Sub Bersama', 'status' => $index === 4 ? 'inactive' : 'active']);
        $db->table('tb_record')->insert(['cluster_id' => 10 + $index, 'sub_cluster_id' => 20 + $index]);
    }
    $this->migration->up();
    $clusterKey = DB::connection('mysql_snp')->table('tb_cluster')->value('shared_key');
    $subKey = DB::connection('mysql_snp')->table('tb_sub_cluster')->value('shared_key');
    expect($clusterKey)->not->toBeNull()->and($subKey)->not->toBeNull();
    foreach (SharedClusterCatalog::MODULES as $index => $module) {
        $db = DB::connection('mysql_'.$module);
        expect($db->table('tb_cluster')->count())->toBe(1);
        expect((array) $db->table('tb_cluster')->first())->toMatchArray(['id' => 10 + $index, 'nama_cluster' => 'Cluster Bersama', 'status' => 'inactive', 'shared_key' => $clusterKey]);
        expect((array) $db->table('tb_sub_cluster')->first())->toMatchArray(['id' => 20 + $index, 'cluster_id' => 10 + $index, 'nama_sub_cluster' => 'Sub Bersama', 'status' => 'inactive', 'shared_key' => $subKey]);
        expect((array) $db->table('tb_record')->first())->toMatchArray(['cluster_id' => 10 + $index, 'sub_cluster_id' => 20 + $index]);
    }
    $this->migration->up();
    expect(DB::connection('mysql_snp')->table('tb_cluster')->value('shared_key'))->toBe($clusterKey);
    expect(DB::connection('mysql_eksternal')->table('tb_sub_cluster')->count())->toBe(1);
    $this->migration->down();
    foreach (SharedClusterCatalog::MODULES as $module) {
        expect(Schema::connection('mysql_'.$module)->hasColumn('tb_cluster', 'shared_key'))->toBeFalse();
        expect(DB::connection('mysql_'.$module)->table('tb_record')->count())->toBe(1);
    }
});

test('catalog includes unmatched master data and scopes matching subcluster names by parent', function () {
    $db = DB::connection('mysql_snp');
    $db->table('tb_cluster')->insert(['id' => 1, 'nama_cluster' => 'Cluster A']);
    $db->table('tb_sub_cluster')->insert(['id' => 1, 'cluster_id' => 1, 'nama_sub_cluster' => 'Sub Sama']);
    $db = DB::connection('mysql_ragab');
    $db->table('tb_cluster')->insert(['id' => 1, 'nama_cluster' => 'Cluster B', 'keterangan' => 'Detail B']);
    $db->table('tb_sub_cluster')->insert(['id' => 1, 'cluster_id' => 1, 'nama_sub_cluster' => 'Sub Sama']);
    $db->table('tb_record')->insert(['cluster_id' => 1, 'sub_cluster_id' => 1]);
    $this->migration->up();
    foreach (SharedClusterCatalog::MODULES as $module) {
        $db = DB::connection('mysql_'.$module);
        expect($db->table('tb_cluster')->count())->toBe(2);
        expect($db->table('tb_sub_cluster')->count())->toBe(2);
        foreach (['Cluster A', 'Cluster B'] as $name) {
            $parentId = $db->table('tb_cluster')->where('nama_cluster', $name)->value('id');
            expect($db->table('tb_sub_cluster')->where('cluster_id', $parentId)->value('nama_sub_cluster'))->toBe('Sub Sama');
        }
        expect($db->table('tb_cluster')->where('nama_cluster', 'Cluster B')->value('keterangan'))->toBe('Detail B');
    }
    expect(DB::connection('mysql_ragab')->table('tb_cluster')->where('id', 1)->value('nama_cluster'))->toBe('Cluster B');
    expect(DB::connection('mysql_ragab')->table('tb_record')->value('sub_cluster_id'))->toBe(1);
});

test('ambiguous duplicate cluster names are rejected before schema or data changes', function () {
    DB::connection('mysql_rawas')->table('tb_cluster')->insert([
        ['nama_cluster' => 'Cluster A'], ['nama_cluster' => ' CLUSTER  A '],
    ]);
    expect(fn () => $this->migration->up())->toThrow(RuntimeException::class);
    foreach (SharedClusterCatalog::MODULES as $module) {
        expect(Schema::connection('mysql_'.$module)->hasColumn('tb_cluster', 'shared_key'))->toBeFalse();
    }
    expect(DB::connection('mysql_rawas')->table('tb_cluster')->count())->toBe(2);
});

test('ambiguous duplicate subcluster names are rejected before schema changes', function () {
    $db = DB::connection('mysql_djsn');
    $db->table('tb_cluster')->insert(['id' => 1, 'nama_cluster' => 'Cluster A']);
    $db->table('tb_sub_cluster')->insert([
        ['cluster_id' => 1, 'nama_sub_cluster' => 'Sub A'], ['cluster_id' => 1, 'nama_sub_cluster' => ' SUB  A '],
    ]);
    expect(fn () => $this->migration->up())->toThrow(RuntimeException::class);
    expect(Schema::connection('mysql_snp')->hasColumn('tb_cluster', 'shared_key'))->toBeFalse();
});

test('fresh installation seeds the shared catalog once and all module seeders preserve it', function () {
    $this->migration->up();
    (new SnpMasterSeeder)->run();
    $before = DB::connection('mysql_snp')->table('tb_cluster')->orderBy('id')->get()->toArray();
    expect($before)->toHaveCount(12);
    foreach (SharedClusterCatalog::MODULES as $module) {
        $class = 'Database\\Seeders\\'.ucfirst($module).'MasterSeeder';
        (new $class)->run();
        expect(DB::connection('mysql_'.$module)->table('tb_cluster')->orderBy('id')->get()->toArray())->toEqual($before);
        expect(DB::connection('mysql_'.$module)->table('tb_sub_cluster')->count())->toBe(39);
    }
});

test('orphan subclusters stop migration before adding shared keys', function () {
    Schema::connection('mysql_eksternal')->disableForeignKeyConstraints();
    DB::connection('mysql_eksternal')->table('tb_sub_cluster')->insert(['cluster_id' => 999, 'nama_sub_cluster' => 'Tanpa induk']);
    Schema::connection('mysql_eksternal')->enableForeignKeyConstraints();
    expect(fn () => $this->migration->up())->toThrow(RuntimeException::class);
    expect(Schema::connection('mysql_snp')->hasColumn('tb_cluster', 'shared_key'))->toBeFalse();
    expect(DB::connection('mysql_eksternal')->table('tb_sub_cluster')->value('cluster_id'))->toBe(999);
});

test('shared transactions roll back writes to every connection when a later operation fails', function () {
    expect(fn () => app(SharedClusterCatalog::class)->transaction(function () {
        foreach (SharedClusterCatalog::MODULES as $module) {
            DB::connection('mysql_'.$module)->table('tb_cluster')->insert(['nama_cluster' => 'Rollback']);
        }
        DB::table('tb_log_activity')->insert(['type_code' => 'snp', 'table_name' => 'tb_cluster', 'record_key' => '1', 'action' => 'create_cluster']);
        throw new RuntimeException('Interrupted');
    }))->toThrow(RuntimeException::class, 'Interrupted');
    foreach (SharedClusterCatalog::MODULES as $module) {
        expect(DB::connection('mysql_'.$module)->table('tb_cluster')->count())->toBe(0);
    }
    expect(DB::table('tb_log_activity')->count())->toBe(0);
});
