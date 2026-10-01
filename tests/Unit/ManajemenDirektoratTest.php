<?php

use App\Models\Direktorat;
use App\Models\Role;
use App\Models\RoleType;
use App\Models\UnitKerja;
use App\Models\User;
use Database\Seeders\PicMasterSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->withoutVite();
    config()->set('app.key', 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    config()->set('database.default', 'mysql');
    DB::purge('mysql');
    foreach (['mysql_snp', 'mysql_ragab', 'mysql_rawas', 'mysql_djsn', 'mysql_eksternal', 'mysql_kunjungan'] as $connection) {
        config()->set("database.connections.{$connection}", config('database.connections.sqlite'));
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
    Schema::create('tb_direktorat', function (Blueprint $table) {
        $table->id();
        $table->string('nama_direktorat');
        $table->string('kode_direktorat')->nullable()->unique();
        $table->text('keterangan')->nullable();
        $table->string('status')->default('active');
        $table->boolean('managed_from_ui')->default(false);
        $table->timestamps();
    });
    Schema::create('tb_unit_kerja', function (Blueprint $table) {
        $table->id();
        $table->foreignId('direktorat_id')->nullable();
        $table->string('nama_unit');
        $table->string('kode_unit')->nullable()->unique();
        $table->text('keterangan')->nullable();
        $table->string('status')->default('active');
        $table->boolean('managed_from_ui')->default(false);
        $table->timestamps();
    });
    Schema::create('tb_user_unit_kerja', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id');
        $table->foreignId('unit_kerja_id');
        $table->string('status');
        $table->timestamps();
        $table->unique(['user_id', 'unit_kerja_id']);
    });
    Schema::create('tb_komite', function (Blueprint $table) {
        $table->id();
        $table->string('nama_komite');
        $table->string('kode_komite')->nullable();
        $table->text('keterangan')->nullable();
        $table->timestamps();
    });
    Schema::create('tb_user_komite', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id');
        $table->foreignId('komite_id');
        $table->string('status');
        $table->timestamps();
    });
    Schema::create('tb_log_activity', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->nullable();
        $table->string('type_code')->nullable();
        $table->string('database_name')->nullable();
        $table->string('table_name')->nullable();
        $table->string('record_key')->nullable();
        $table->string('action');
        $table->text('description')->nullable();
        $table->text('old_values')->nullable();
        $table->text('new_values')->nullable();
        $table->string('ip_address')->nullable();
        $table->text('user_agent')->nullable();
        $table->timestamps();
    });

    $role = Role::create(['name' => 'super_admin', 'display_name' => 'Super Admin', 'level' => 100, 'is_universal' => true]);
    $this->superRoleType = RoleType::create(['role_id' => $role->id, 'name' => 'super_admin']);
    $this->superAdmin = User::factory()->create(['status' => 'active']);
    $this->superAdmin->roleTypes()->attach($this->superRoleType->id, ['status' => 'active']);
    $this->actingAs($this->superAdmin);
});

test('super admin can create a directorate and a unit under it', function () {
    $this->post(route('administrasi.manajemen-direktorat.store'), [
        'nama_direktorat' => 'Direktorat Baru',
        'kode_direktorat' => 'BARU',
    ])->assertRedirect(route('administrasi.manajemen-direktorat.index'));

    $direktorat = Direktorat::where('kode_direktorat', 'BARU')->firstOrFail();
    $this->post(route('administrasi.manajemen-direktorat.unit.store'), [
        'direktorat_id' => $direktorat->id,
        'nama_unit' => 'Unit Baru',
        'kode_unit' => 'UB',
    ])->assertRedirect(route('administrasi.manajemen-direktorat.index'));

    expect(UnitKerja::where('kode_unit', 'UB')->firstOrFail()->direktorat_id)->toBe($direktorat->id);
    expect(DB::table('tb_log_activity')->count())->toBe(2);
    $this->get(route('administrasi.manajemen-direktorat.index'))->assertOk()->assertSee('Unit Baru');

    $unit = UnitKerja::where('kode_unit', 'UB')->firstOrFail();
    $this->delete(route('administrasi.manajemen-direktorat.unit.destroy', $unit))
        ->assertRedirect(route('administrasi.manajemen-direktorat.index'));
    $this->delete(route('administrasi.manajemen-direktorat.destroy', $direktorat))
        ->assertRedirect(route('administrasi.manajemen-direktorat.index'));
});

test('non super admin cannot use directorate management', function () {
    $regularUser = User::factory()->create(['status' => 'active']);
    $this->actingAs($regularUser);

    $this->get(route('administrasi.manajemen-direktorat.index'))->assertForbidden();
    $this->post(route('administrasi.manajemen-direktorat.store'), ['nama_direktorat' => 'Tidak Boleh'])->assertForbidden();
    expect(Direktorat::count())->toBe(0);
});

test('a unit with an active user cannot be deactivated or deleted', function () {
    $direktorat = Direktorat::create(['nama_direktorat' => 'Operasi', 'status' => 'active']);
    $unit = UnitKerja::create(['direktorat_id' => $direktorat->id, 'nama_unit' => 'Unit Lama', 'status' => 'active']);
    $member = User::factory()->create(['status' => 'active']);
    $member->unitKerja()->attach($unit->id, ['status' => 'active']);

    $this->get(route('administrasi.manajemen-direktorat.index'))->assertOk()->assertSee('1 user aktif');

    $this->patch(route('administrasi.manajemen-direktorat.unit.status', $unit), ['status' => 'inactive'])
        ->assertSessionHasErrors('status');
    $this->delete(route('administrasi.manajemen-direktorat.unit.destroy', $unit))
        ->assertSessionHasErrors('unit_kerja');

    expect($unit->fresh()->status)->toBe('active');
    expect(UnitKerja::whereKey($unit->id)->exists())->toBeTrue();
});

test('user transfer retains old assignment and then old unit can only be deactivated', function () {
    $direktorat = Direktorat::create(['nama_direktorat' => 'Operasi', 'status' => 'active']);
    $oldUnit = UnitKerja::create(['direktorat_id' => $direktorat->id, 'nama_unit' => 'Lama', 'status' => 'active']);
    $newUnit = UnitKerja::create(['direktorat_id' => $direktorat->id, 'nama_unit' => 'Baru', 'status' => 'active']);
    $member = User::factory()->create(['status' => 'active']);
    $member->unitKerja()->attach($oldUnit->id, ['status' => 'active']);

    $this->patch(route('administrasi.manajemen-user.update', $member), [
        'role_type_ids' => [$this->superRoleType->id],
        'direktorat_id' => $direktorat->id,
        'assignment' => 'unit:'.$newUnit->id,
    ])->assertRedirect(route('administrasi.manajemen-user.index'));

    expect(DB::table('tb_user_unit_kerja')->where('user_id', $member->id)->where('unit_kerja_id', $oldUnit->id)->value('status'))->toBe('inactive');
    expect(DB::table('tb_user_unit_kerja')->where('user_id', $member->id)->where('unit_kerja_id', $newUnit->id)->value('status'))->toBe('active');

    $this->patch(route('administrasi.manajemen-direktorat.unit.status', $oldUnit), ['status' => 'inactive'])
        ->assertRedirect(route('administrasi.manajemen-direktorat.index'));
    $this->delete(route('administrasi.manajemen-direktorat.unit.destroy', $oldUnit))
        ->assertSessionHasErrors('unit_kerja');
    expect($oldUnit->fresh()->status)->toBe('inactive');
});

test('unused unit can be deleted and inactive directorate cannot accept a new unit', function () {
    $direktorat = Direktorat::create(['nama_direktorat' => 'Operasi', 'status' => 'active']);
    $unit = UnitKerja::create(['direktorat_id' => $direktorat->id, 'nama_unit' => 'Belum Dipakai', 'status' => 'active', 'managed_from_ui' => true]);

    $this->delete(route('administrasi.manajemen-direktorat.unit.destroy', $unit))
        ->assertRedirect(route('administrasi.manajemen-direktorat.index'));
    expect(UnitKerja::whereKey($unit->id)->exists())->toBeFalse();

    $this->patch(route('administrasi.manajemen-direktorat.status', $direktorat), ['status' => 'inactive'])
        ->assertRedirect(route('administrasi.manajemen-direktorat.index'));
    $this->post(route('administrasi.manajemen-direktorat.unit.store'), [
        'direktorat_id' => $direktorat->id,
        'nama_unit' => 'Tidak Bisa',
    ])->assertSessionHasErrors('direktorat_id');
});

test('a unit assigned to unfinished work cannot be deactivated', function () {
    $direktorat = Direktorat::create(['nama_direktorat' => 'Operasi', 'status' => 'active']);
    $unit = UnitKerja::create(['direktorat_id' => $direktorat->id, 'nama_unit' => 'PIC Aktif', 'status' => 'active']);

    Schema::connection('mysql_snp')->create('tb_butir_snp', function (Blueprint $table) {
        $table->id();
        $table->string('id_butir_snp')->unique();
        $table->string('status');
    });
    Schema::connection('mysql_snp')->create('tb_butir_pic', function (Blueprint $table) {
        $table->id();
        $table->string('id_butir_snp');
        $table->foreignId('unit_kerja_id');
    });
    DB::connection('mysql_snp')->table('tb_butir_snp')->insert(['id_butir_snp' => 'SNP.01', 'status' => 'dalam_proses']);
    DB::connection('mysql_snp')->table('tb_butir_pic')->insert(['id_butir_snp' => 'SNP.01', 'unit_kerja_id' => $unit->id]);

    $this->patch(route('administrasi.manajemen-direktorat.unit.status', $unit), ['status' => 'inactive'])
        ->assertSessionHasErrors('status');
    expect($unit->fresh()->status)->toBe('active');
});

test('an active directorate with units stays active and a used unit cannot move directorates', function () {
    $first = Direktorat::create(['nama_direktorat' => 'Pertama', 'status' => 'active']);
    $second = Direktorat::create(['nama_direktorat' => 'Kedua', 'status' => 'active']);
    $unit = UnitKerja::create(['direktorat_id' => $first->id, 'nama_unit' => 'Unit', 'status' => 'active']);
    $member = User::factory()->create(['status' => 'active']);
    $member->unitKerja()->attach($unit->id, ['status' => 'active']);

    $this->patch(route('administrasi.manajemen-direktorat.status', $first), ['status' => 'inactive'])
        ->assertSessionHasErrors('status');
    $this->patch(route('administrasi.manajemen-direktorat.unit.update', $unit), [
        'direktorat_id' => $second->id,
        'nama_unit' => 'Unit',
    ])->assertSessionHasErrors('direktorat_id');

    expect($first->fresh()->status)->toBe('active');
    expect($unit->fresh()->direktorat_id)->toBe($first->id);
});

test('rerunning the master seeder preserves edits and deliberate deletions', function () {
    (new PicMasterSeeder)->run();
    Direktorat::findOrFail(1)->update(['nama_direktorat' => 'Nama Diubah']);
    UnitKerja::findOrFail(1)->update(['nama_unit' => 'Unit Diubah']);
    UnitKerja::findOrFail(2)->delete();
    DB::table('tb_log_activity')->insert([
        'table_name' => 'tb_unit_kerja',
        'record_key' => '2',
        'action' => 'delete_unit_kerja',
    ]);

    (new PicMasterSeeder)->run();

    expect(Direktorat::findOrFail(1)->nama_direktorat)->toBe('Nama Diubah');
    expect(UnitKerja::findOrFail(1)->nama_unit)->toBe('Unit Diubah');
    expect(UnitKerja::whereKey(2)->exists())->toBeFalse();
});

test('an activity log prevents deletion after a user assignment row disappears', function () {
    $direktorat = Direktorat::create(['nama_direktorat' => 'Operasi', 'status' => 'active']);
    $unit = UnitKerja::create(['direktorat_id' => $direktorat->id, 'nama_unit' => 'Pernah Dipakai', 'status' => 'active', 'managed_from_ui' => true]);
    DB::table('tb_log_activity')->insert([
        'table_name' => 'users',
        'record_key' => '88',
        'action' => 'create_user',
        'new_values' => json_encode(['assignment' => ['type' => 'unit', 'id' => $unit->id]]),
    ]);

    $this->delete(route('administrasi.manajemen-direktorat.unit.destroy', $unit))
        ->assertSessionHasErrors('unit_kerja');
    expect($unit->fresh())->not->toBeNull();
});
