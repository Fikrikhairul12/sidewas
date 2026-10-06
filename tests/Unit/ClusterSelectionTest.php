<?php

use App\Models\RagabButir;
use App\Models\SnpRecord;
use App\Services\ClusterSelection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    foreach (['snp', 'ragab', 'rawas', 'djsn', 'eksternal'] as $module) {
        $connection = 'mysql_'.$module;
        config(['database.connections.'.$connection => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]]);
        DB::purge($connection);
        $schema = Schema::connection($connection);
        $schema->create('tb_cluster', function (Blueprint $table): void {
            $table->id();
            $table->string('nama_cluster');
        });
        $schema->create('tb_sub_cluster', function (Blueprint $table): void {
            $table->id();
            $table->integer('cluster_id');
            $table->string('nama_sub_cluster');
        });
        DB::connection($connection)->table('tb_cluster')->insert([
            ['id' => 1, 'nama_cluster' => 'Cluster lama'], ['id' => 2, 'nama_cluster' => 'Cluster lain'],
        ]);
        DB::connection($connection)->table('tb_sub_cluster')->insert([
            ['id' => 1, 'cluster_id' => 1, 'nama_sub_cluster' => 'Subcluster lama'],
            ['id' => 2, 'cluster_id' => 2, 'nama_sub_cluster' => 'Subcluster lain'],
            ['id' => 3, 'cluster_id' => 1, 'nama_sub_cluster' => 'Subcluster tambahan'],
        ]);
    }
    (require database_path('migrations/2026_10_06_084205_add_status_to_module_clusters.php'))->up();
    Schema::connection('mysql_ragab')->create('tb_butir_sub_cluster', function (Blueprint $table): void {
        $table->string('id_butir_ragab');
        $table->integer('sub_cluster_id');
        $table->timestamps();
    });
});

test('status migration preserves old master data and can be rolled back', function (string $module) {
    $db = DB::connection('mysql_'.$module);
    expect($db->table('tb_cluster')->where('id', 1)->first()->status)->toBe('active')
        ->and($db->table('tb_sub_cluster')->where('id', 1)->first()->status)->toBe('active');
    (require database_path('migrations/2026_10_06_084205_add_status_to_module_clusters.php'))->down();
    expect(Schema::connection('mysql_'.$module)->hasColumn('tb_cluster', 'status'))->toBeFalse()
        ->and($db->table('tb_sub_cluster')->where('id', 1)->value('nama_sub_cluster'))->toBe('Subcluster lama');
})->with(['snp', 'ragab', 'rawas', 'djsn', 'eksternal']);

test('cluster selection rejects inactive mismatched and malformed values while retaining historical selections', function (string $module) {
    $db = DB::connection('mysql_'.$module);
    $check = fn (array $data, ?Model $existing = null) => Validator::make($data, [
        'cluster_id' => ['required', 'integer', new ClusterSelection($module, $existing)],
        'sub_cluster_id' => ['required', 'integer', new ClusterSelection($module, $existing)],
    ]);
    expect($check(['cluster_id' => 1, 'sub_cluster_id' => 1])->passes())->toBeTrue()
        ->and($check(['cluster_id' => 1, 'sub_cluster_id' => 2])->errors()->has('sub_cluster_id'))->toBeTrue()
        ->and($check(['cluster_id' => 999, 'sub_cluster_id' => 1])->fails())->toBeTrue()
        ->and($check(['cluster_id' => [], 'sub_cluster_id' => []])->fails())->toBeTrue();
    $existingClass = $module === 'snp' ? SnpRecord::class : 'App\\Models\\'.ucfirst($module).'Butir';
    $existing = (new $existingClass)->forceFill(['cluster_id' => 1, 'sub_cluster_id' => 1, 'id_butir_ragab' => 'RAGAB.01']);
    $db->table('tb_sub_cluster')->where('id', 1)->update(['status' => 'inactive']);
    expect($check(['cluster_id' => 1, 'sub_cluster_id' => 1])->fails())->toBeTrue()
        ->and($check(['cluster_id' => 1, 'sub_cluster_id' => 1], $existing)->passes())->toBeTrue();
    $db->table('tb_cluster')->where('id', 1)->update(['status' => 'inactive']);
    expect($check(['cluster_id' => 1, 'sub_cluster_id' => 1])->fails())->toBeTrue()
        ->and($check(['cluster_id' => 1, 'sub_cluster_id' => 1], $existing)->passes())->toBeTrue()
        ->and($check(['cluster_id' => 1, 'sub_cluster_id' => 3], $existing)->fails())->toBeTrue()
        ->and($check(['cluster_id' => 2, 'sub_cluster_id' => 2], $existing)->passes())->toBeTrue();
})->with(['snp', 'ragab', 'rawas', 'djsn', 'eksternal']);

test('ragab retains secondary inactive subclusters but rejects new inactive selections', function () {
    $db = DB::connection('mysql_ragab');
    $db->table('tb_sub_cluster')->whereIn('id', [1, 3])->update(['status' => 'inactive']);
    $db->table('tb_butir_sub_cluster')->insert(['id_butir_ragab' => 'RAGAB.01', 'sub_cluster_id' => 3]);
    $butir = (new RagabButir)->forceFill(['id_butir_ragab' => 'RAGAB.01', 'cluster_id' => 1, 'sub_cluster_id' => 1]);
    $data = ['cluster_id' => 1, 'sub_cluster_ids' => [1, 3]];
    $rules = ['sub_cluster_ids.*' => ['integer', new ClusterSelection('ragab', $butir)]];
    expect(Validator::make($data, $rules)->passes())->toBeTrue();
    $db->table('tb_butir_sub_cluster')->delete();
    expect(Validator::make($data, $rules)->errors()->has('sub_cluster_ids.1'))->toBeTrue();
});

test('recording form options exclude inactive masters and retain only their original historical selection', function () {
    $process = new Process(['node', base_path('tests/Unit/ClusterSelection.browser.mjs')], base_path());
    $process->mustRun();
    expect($process->getOutput())->toContain('Cluster selection controls passed');
});
