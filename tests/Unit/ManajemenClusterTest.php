<?php

use App\Models\LogActivity;
use App\Models\Role;
use App\Models\RoleType;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

dataset('cluster modules', ['snp', 'ragab', 'rawas', 'djsn', 'eksternal']);

beforeEach(function () {
    $this->withoutVite();
    config()->set('app.key', 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
    config()->set('database.default', 'mysql');
    foreach (['mysql', 'mysql_snp', 'mysql_ragab', 'mysql_rawas', 'mysql_djsn', 'mysql_eksternal'] as $connection) {
        config()->set('database.connections.'.$connection, array_merge(config('database.connections.sqlite'), ['database' => ':memory:']));
        DB::purge($connection);
    }
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->string('status')->default('active');
        $table->timestamp('email_verified_at')->nullable();
        $table->rememberToken();
        $table->timestamps();
    });
    Schema::create('tb_role', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('display_name');
        $table->integer('level');
        $table->boolean('is_universal');
        $table->timestamps();
    });
    Schema::create('tb_role_type', function (Blueprint $table) {
        $table->id();
        $table->foreignId('role_id');
        $table->foreignId('type_id')->nullable();
        $table->string('name');
        $table->timestamps();
    });
    Schema::create('tb_user_role_type', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id');
        $table->foreignId('role_type_id');
        $table->string('status');
        $table->timestamps();
    });
    Schema::create('tb_log_activity', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->nullable();
        foreach (['type_code', 'database_name', 'table_name', 'record_key', 'action', 'ip_address'] as $column) {
            $table->string($column)->nullable();
        }
        foreach (['description', 'old_values', 'new_values', 'user_agent'] as $column) {
            $table->text($column)->nullable();
        }
        $table->timestamps();
    });
    foreach (['snp', 'ragab', 'rawas', 'djsn', 'eksternal'] as $module) {
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
            $table->foreignId('cluster_id')->constrained('tb_cluster')->cascadeOnDelete();
            $table->string('nama_sub_cluster');
            $table->string('status')->default('active');
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
        $schema->create($module === 'snp' ? 'tb_record' : 'tb_butir_'.$module, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cluster_id')->nullable();
            $table->unsignedBigInteger('sub_cluster_id')->nullable();
        });
        DB::connection('mysql_'.$module)->table('tb_cluster')->insert(['id' => 1, 'nama_cluster' => 'Cluster Awal']);
        DB::connection('mysql_'.$module)->table('tb_sub_cluster')->insert(['id' => 1, 'cluster_id' => 1, 'nama_sub_cluster' => 'Subcluster Awal']);
    }
    Schema::connection('mysql_ragab')->create('tb_butir_sub_cluster', function (Blueprint $table) {
        $table->id();
        $table->string('id_butir_ragab');
        $table->unsignedBigInteger('sub_cluster_id');
    });
    $role = Role::create(['name' => 'super_admin', 'display_name' => 'Super Admin', 'level' => 100, 'is_universal' => true]);
    $roleType = RoleType::create(['role_id' => $role->id, 'name' => 'super_admin']);
    $this->superAdmin = User::factory()->create(['status' => 'active']);
    $this->superAdmin->roleTypes()->attach($roleType->id, ['status' => 'active']);
    $this->actingAs($this->superAdmin);
});

test('module chooser is placed after directorate management and links to all five modules', function () {
    $response = $this->get(route('administrasi.manajemen-cluster.index'))->assertOk()
        ->assertSeeInOrder(['Manajemen Unit Kerja', 'Manajemen Cluster']);
    foreach (['snp', 'ragab', 'rawas', 'djsn', 'eksternal'] as $module) {
        $response->assertSee(route('administrasi.manajemen-cluster.show', $module));
    }
    $this->get(route('administrasi.manajemen-cluster.show', 'unknown'))->assertNotFound();
    $this->post(route('administrasi.manajemen-cluster.cluster.store', 'mysql'), ['nama_cluster' => 'Invalid'])->assertNotFound();
});

test('cluster and subcluster CRUD is scoped to the chosen module and audited', function (string $module) {
    $db = DB::connection('mysql_'.$module);
    $index = route('administrasi.manajemen-cluster.show', $module);
    $this->get($index)->assertOk()->assertSee('Cluster Awal')->assertSee('Subcluster Awal')->assertSee('Lihat Subcluster');
    $this->post(route('administrasi.manajemen-cluster.cluster.store', $module), [
        'nama_cluster' => 'Cluster Baru', 'keterangan' => 'Keterangan baru',
    ])->assertSessionHasNoErrors()->assertRedirect($index);
    $clusterId = $db->table('tb_cluster')->where('nama_cluster', 'Cluster Baru')->value('id');
    $this->post(route('administrasi.manajemen-cluster.subcluster.store', $module), [
        'cluster_id' => $clusterId, 'nama_sub_cluster' => 'Subcluster Baru',
    ])->assertSessionHasNoErrors()->assertRedirect($index)->assertSessionHas('open_cluster', $clusterId);
    $subClusterId = $db->table('tb_sub_cluster')->where('nama_sub_cluster', 'Subcluster Baru')->value('id');
    $this->patch(route('administrasi.manajemen-cluster.cluster.update', [$module, 1]), [
        'nama_cluster' => 'Cluster Diubah', 'keterangan' => 'Tetap terhubung',
    ])->assertSessionHasNoErrors()->assertRedirect($index);
    $this->patch(route('administrasi.manajemen-cluster.subcluster.update', [$module, 1, 1]), [
        'nama_sub_cluster' => 'Subcluster Diubah', 'keterangan' => 'Detail baru', 'cluster_id' => $clusterId,
    ])->assertSessionHasNoErrors()->assertRedirect($index);
    expect($db->table('tb_sub_cluster')->where('id', 1)->value('cluster_id'))->toBe(1);
    $this->get($index.'?keyword=Subcluster%20Diubah')->assertOk()
        ->assertViewHas('clusters', fn ($items) => $items->modelKeys() === [1]);
    foreach (array_diff(['snp', 'ragab', 'rawas', 'djsn', 'eksternal'], [$module]) as $other) {
        expect(DB::connection('mysql_'.$other)->table('tb_cluster')->count())->toBe(1);
        expect(DB::connection('mysql_'.$other)->table('tb_cluster')->value('nama_cluster'))->toBe('Cluster Awal');
        expect(DB::connection('mysql_'.$other)->table('tb_sub_cluster')->value('nama_sub_cluster'))->toBe('Subcluster Awal');
    }
    $this->delete(route('administrasi.manajemen-cluster.cluster.destroy', [$module, $clusterId]))->assertSessionHasErrors('cluster');
    $this->delete(route('administrasi.manajemen-cluster.subcluster.destroy', [$module, $clusterId, $subClusterId]))->assertRedirect($index);
    $this->delete(route('administrasi.manajemen-cluster.cluster.destroy', [$module, $clusterId]))->assertRedirect($index);
    expect($db->table('tb_cluster')->where('id', $clusterId)->exists())->toBeFalse();
    expect($db->table('tb_sub_cluster')->where('id', $subClusterId)->exists())->toBeFalse();
    expect(LogActivity::where('type_code', $module)->count())->toBe(6);
    $log = LogActivity::where('action', 'update_cluster')->firstOrFail();
    expect($log->old_values['nama_cluster'])->toBe('Cluster Awal');
    expect($log->new_values['nama_cluster'])->toBe('Cluster Diubah');
})->with('cluster modules');

test('all management endpoints reject users without super admin access', function (string $module) {
    $this->actingAs(User::factory()->create(['status' => 'active']));
    $this->get(route('administrasi.manajemen-cluster.index'))->assertForbidden();
    $this->get(route('administrasi.manajemen-cluster.show', $module))->assertForbidden();
    $this->post(route('administrasi.manajemen-cluster.cluster.store', $module))->assertForbidden();
    $this->patch(route('administrasi.manajemen-cluster.cluster.update', [$module, 1]))->assertForbidden();
    $this->patch(route('administrasi.manajemen-cluster.cluster.status', [$module, 1]), ['status' => 'inactive'])->assertForbidden();
    $this->delete(route('administrasi.manajemen-cluster.cluster.destroy', [$module, 1]))->assertForbidden();
    $this->post(route('administrasi.manajemen-cluster.subcluster.store', $module))->assertForbidden();
    $this->patch(route('administrasi.manajemen-cluster.subcluster.update', [$module, 1, 1]))->assertForbidden();
    $this->patch(route('administrasi.manajemen-cluster.subcluster.status', [$module, 1, 1]), ['status' => 'inactive'])->assertForbidden();
    $this->delete(route('administrasi.manajemen-cluster.subcluster.destroy', [$module, 1, 1]))->assertForbidden();
    expect(LogActivity::count())->toBe(0);
})->with('cluster modules');

test('used clusters and subclusters retain their operational references when edited or deletion is attempted', function (string $module) {
    $db = DB::connection('mysql_'.$module);
    $table = $module === 'snp' ? 'tb_record' : 'tb_butir_'.$module;
    $db->table('tb_cluster')->insert(['id' => 2, 'nama_cluster' => 'Cluster Tanpa Anak']);
    $db->table($table)->insert(['cluster_id' => 2, 'sub_cluster_id' => 1]);
    $this->delete(route('administrasi.manajemen-cluster.cluster.destroy', [$module, 2]))->assertSessionHasErrors('cluster');
    $this->delete(route('administrasi.manajemen-cluster.subcluster.destroy', [$module, 1, 1]))->assertSessionHasErrors('sub_cluster');
    $this->patch(route('administrasi.manajemen-cluster.cluster.update', [$module, 2]), ['nama_cluster' => 'Nama Baru'])->assertSessionHasNoErrors();
    $this->patch(route('administrasi.manajemen-cluster.subcluster.update', [$module, 1, 1]), ['nama_sub_cluster' => 'Nama Sub Baru'])->assertSessionHasNoErrors();
    expect($db->table($table)->first()->cluster_id)->toBe(2);
    expect($db->table($table)->first()->sub_cluster_id)->toBe(1);
})->with('cluster modules');

test('ragab protects every selected subcluster including secondary selections', function () {
    DB::connection('mysql_ragab')->table('tb_butir_sub_cluster')->insert(['id_butir_ragab' => 'RAGAB.01', 'sub_cluster_id' => 1]);
    $this->delete(route('administrasi.manajemen-cluster.subcluster.destroy', ['ragab', 1, 1]))->assertSessionHasErrors('sub_cluster');
    expect(DB::connection('mysql_ragab')->table('tb_butir_sub_cluster')->value('sub_cluster_id'))->toBe(1);
});

test('validation restores inputs and prevents duplicates or invalid parents', function () {
    $index = route('administrasi.manajemen-cluster.show', 'snp');
    $this->from($index)->post(route('administrasi.manajemen-cluster.cluster.store', 'snp'), [
        '_form' => 'create-cluster', 'nama_cluster' => 'Cluster Awal',
    ])->assertSessionHasErrors('nama_cluster')->assertSessionHasInput('nama_cluster', 'Cluster Awal');
    $this->get($index)->assertOk()->assertSee('create-cluster');
    $this->from($index)->post(route('administrasi.manajemen-cluster.subcluster.store', 'snp'), [
        '_form' => 'create-subcluster', 'cluster_id' => 1, 'nama_sub_cluster' => 'Subcluster Awal',
    ])->assertSessionHasErrors('nama_sub_cluster');
    $this->post(route('administrasi.manajemen-cluster.subcluster.store', 'snp'), [
        'cluster_id' => 999, 'nama_sub_cluster' => 'Valid Name',
    ])->assertSessionHasErrors('cluster_id');
    $this->post(route('administrasi.manajemen-cluster.cluster.store', 'snp'), ['nama_cluster' => ''])->assertSessionHasErrors('nama_cluster');
    $this->from($index)->patch(route('administrasi.manajemen-cluster.subcluster.update', ['snp', 1, 1]), [
        '_form' => 'edit-subcluster', 'cluster_id' => 1, 'sub_cluster_id' => 1,
        'nama_sub_cluster' => str_repeat('a', 256), 'keterangan' => 'Jangan hilang',
    ])->assertSessionHasErrors('nama_sub_cluster')->assertSessionHasInput('keterangan', 'Jangan hilang');
    $this->get($index)->assertOk()->assertSee('Jangan hilang');
    $this->patch(route('administrasi.manajemen-cluster.subcluster.update', ['snp', 999, 1]), ['nama_sub_cluster' => 'Wrong parent'])->assertNotFound();
    $this->delete(route('administrasi.manajemen-cluster.subcluster.destroy', ['snp', 999, 1]))->assertNotFound();
    $this->patch(route('administrasi.manajemen-cluster.cluster.update', ['snp', 999]), ['nama_cluster' => 'Missing'])->assertNotFound();
});

test('history including pending updates protects references only in its own module', function () {
    LogActivity::create(['type_code' => 'ragab', 'table_name' => 'tb_record', 'action' => 'request_update',
        'new_values' => ['payload' => ['butir' => ['sub_cluster_ids' => [1]]]],
    ]);
    $this->delete(route('administrasi.manajemen-cluster.subcluster.destroy', ['ragab', 1, 1]))->assertSessionHasErrors('sub_cluster');
    $this->delete(route('administrasi.manajemen-cluster.subcluster.destroy', ['snp', 1, 1]))->assertRedirect(route('administrasi.manajemen-cluster.show', 'snp'));
    LogActivity::create(['type_code' => 'snp', 'table_name' => 'tb_record', 'action' => 'delete', 'old_values' => ['cluster_id' => 1]]);
    $this->delete(route('administrasi.manajemen-cluster.cluster.destroy', ['snp', 1]))->assertSessionHasErrors('cluster');
});

test('rerunning module seeders preserves edits and deliberate deletions', function (string $module) {
    $seederClass = 'Database\\Seeders\\'.ucfirst($module).'MasterSeeder';
    (new $seederClass)->run();
    $db = DB::connection('mysql_'.$module);
    $this->patch(route('administrasi.manajemen-cluster.cluster.update', [$module, 1]), ['nama_cluster' => 'Edited Cluster'])->assertSessionHasNoErrors();
    $this->patch(route('administrasi.manajemen-cluster.subcluster.update', [$module, 1, 1]), ['nama_sub_cluster' => 'Edited Subcluster'])->assertSessionHasNoErrors();
    $this->delete(route('administrasi.manajemen-cluster.subcluster.destroy', [$module, 1, 2]))->assertSessionHasNoErrors();
    foreach ($db->table('tb_sub_cluster')->where('cluster_id', 12)->pluck('id') as $id) {
        $this->delete(route('administrasi.manajemen-cluster.subcluster.destroy', [$module, 12, $id]))->assertSessionHasNoErrors();
    }
    $this->delete(route('administrasi.manajemen-cluster.cluster.destroy', [$module, 12]))->assertSessionHasNoErrors();
    (new $seederClass)->run();
    expect($db->table('tb_cluster')->where('id', 1)->value('nama_cluster'))->toBe('Edited Cluster');
    expect($db->table('tb_sub_cluster')->where('id', 1)->value('nama_sub_cluster'))->toBe('Edited Subcluster');
    expect($db->table('tb_sub_cluster')->where('id', 2)->exists())->toBeFalse();
    expect($db->table('tb_cluster')->where('id', 12)->exists())->toBeFalse();
    expect($db->table('tb_sub_cluster')->where('cluster_id', 12)->exists())->toBeFalse();
})->with('cluster modules');

test('guest must log in before managing clusters', function () {
    auth()->logout();
    $this->get(route('administrasi.manajemen-cluster.index'))->assertRedirect(route('login'));
});

test('used cluster and subcluster can be deactivated without losing history and every status change is audited', function (string $module) {
    $db = DB::connection('mysql_'.$module);
    $table = $module === 'snp' ? 'tb_record' : 'tb_butir_'.$module;
    $db->table($table)->insert(['cluster_id' => 1, 'sub_cluster_id' => 1]);
    $this->patch(route('administrasi.manajemen-cluster.subcluster.status', [$module, 1, 1]), ['status' => 'inactive'])->assertSessionHasNoErrors();
    $this->patch(route('administrasi.manajemen-cluster.cluster.status', [$module, 1]), ['status' => 'inactive'])->assertSessionHasNoErrors();
    expect($db->table('tb_cluster')->value('status'))->toBe('inactive')
        ->and($db->table('tb_sub_cluster')->value('status'))->toBe('inactive')
        ->and($db->table($table)->first()->sub_cluster_id)->toBe(1);
    $this->get(route('administrasi.manajemen-cluster.show', $module))->assertOk()->assertSee('Cluster Awal')->assertSee('Subcluster Awal')->assertSee('Nonaktif');
    $this->post(route('administrasi.manajemen-cluster.subcluster.store', $module), ['cluster_id' => 1, 'nama_sub_cluster' => 'Tidak boleh'])->assertSessionHasErrors('cluster_id');
    $this->patch(route('administrasi.manajemen-cluster.subcluster.status', [$module, 1, 1]), ['status' => 'active'])->assertSessionHasErrors('status');
    $this->patch(route('administrasi.manajemen-cluster.cluster.status', [$module, 1]), ['status' => 'active'])->assertSessionHasNoErrors();
    $this->patch(route('administrasi.manajemen-cluster.subcluster.status', [$module, 1, 1]), ['status' => 'active'])->assertSessionHasNoErrors();
    $logs = LogActivity::where('type_code', $module)->where('action', 'like', 'change_%_status')->get();
    expect($logs)->toHaveCount(4)
        ->and($logs[0]->old_values['status'])->toBe('active')
        ->and($logs[0]->new_values['status'])->toBe('inactive');
    $this->patch(route('administrasi.manajemen-cluster.cluster.status', [$module, 1]), ['status' => 'invalid'])->assertSessionHasErrors('status');
    $this->patch(route('administrasi.manajemen-cluster.subcluster.status', [$module, 999, 1]), ['status' => 'inactive'])->assertNotFound();
})->with('cluster modules');

test('cluster and subcluster edit forms explain the effect of name changes', function () {
    $this->get(route('administrasi.manajemen-cluster.show', 'snp'))->assertOk()
        ->assertSee('Perubahan nama dapat mengubah label pada data lama dan laporan');
});
