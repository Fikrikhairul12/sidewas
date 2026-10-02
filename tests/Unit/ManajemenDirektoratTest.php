<?php

use App\Models\Direktorat;
use App\Models\Komite;
use App\Models\LogActivity;
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
        $table->string('kode_komite')->nullable()->unique();
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
    $this->post(route('administrasi.manajemen-direktorat.store'), [
        'nama_direktorat' => 'Direktorat Kedua',
    ])->assertRedirect(route('administrasi.manajemen-direktorat.index'));

    $this->get(route('administrasi.manajemen-direktorat.index'))
        ->assertOk()
        ->assertSee('Total Unit Kerja')
        ->assertSee('Lihat Unit Kerja')
        ->assertSee('Unit Baru')
        ->assertSee('color: #fff;">Tambah Direktorat', false)
        ->assertSee('style="height: auto; max-height: none; overflow: visible;"', false)
        ->assertSee('border-top-left-radius: 1rem;', false)
        ->assertSee('border-top-right-radius: 1rem;', false)
        ->assertSee('border-top-left-radius: 0.75rem;', false)
        ->assertSee('border-top-right-radius: 0.75rem;', false)
        ->assertSee('background-color: #ffffff;', false)
        ->assertSee('background-color: #eef5fb;', false)
        ->assertSee('x-show="openUnits[', false)
        ->assertSee('aria-controls="unit-kerja-', false)
        ->assertDontSee('grid-template-rows: 0fr', false)
        ->assertDontSee('overflow-x-auto rounded-2xl border border-blue-100', false)
        ->assertSee('name="_form" value="create-direktorat"', false)
        ->assertSee('name="_form" value="create-unit"', false)
        ->assertSee('name="_form" value="edit-direktorat"', false)
        ->assertSee('name="_form" value="edit-unit"', false)
        ->assertDontSee('name="kode_direktorat"', false);

    $this->patch(route('administrasi.manajemen-direktorat.update', $direktorat), [
        'nama_direktorat' => 'Direktorat Diperbarui',
        'keterangan' => 'Keterangan baru',
    ])->assertRedirect(route('administrasi.manajemen-direktorat.index'));
    expect($direktorat->fresh()->kode_direktorat)->toBe('BARU');

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

    $this->get(route('administrasi.manajemen-direktorat.index'))
        ->assertOk()
        ->assertSee('User Aktif')
        ->assertViewHas('direktorats', fn ($items) => $items->first()->unitKerja->first()->active_users_count === 1);

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

test('super admin manages komite inside the dewan pengawas unit details', function () {
    $dewas = Direktorat::create(['nama_direktorat' => 'Dewan Pengawas']);
    $other = Direktorat::create(['nama_direktorat' => 'Direktorat Pelayanan']);
    $index = route('administrasi.manajemen-direktorat.index');

    $this->post(route('administrasi.manajemen-direktorat.komite.store'), [
        'nama_komite' => 'Komite Baru', 'kode_komite' => 'KB', 'keterangan' => 'Keterangan awal',
    ])->assertRedirect($index)->assertSessionHas('open_komite', true);

    $komite = Komite::where('kode_komite', 'KB')->firstOrFail();
    $response = $this->get($index)->assertOk()->assertSee('Lihat Unit Kerja &amp; Komite', false);
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//tr[@id="unit-kerja-'.$dewas->id.'"]//section[@aria-labelledby="komite-heading"]')->length)->toBe(1);
    expect($xpath->query('//tr[@id="unit-kerja-'.$other->id.'"]//section[@aria-labelledby="komite-heading"]')->length)->toBe(0);

    $this->patch(route('administrasi.manajemen-direktorat.komite.update', $komite), [
        'nama_komite' => 'Komite Diperbarui', 'kode_komite' => 'KB', 'keterangan' => 'Keterangan baru',
    ])->assertRedirect($index);
    expect($komite->fresh()->nama_komite)->toBe('Komite Diperbarui');
    expect($komite->fresh()->keterangan)->toBe('Keterangan baru');

    $log = LogActivity::where('action', 'update_komite')->firstOrFail();
    expect($log->old_values['nama_komite'])->toBe('Komite Baru');
    expect($log->new_values['nama_komite'])->toBe('Komite Diperbarui');

    $this->get($index.'?keyword=kb')->assertOk()
        ->assertViewHas('direktorats', fn ($items) => $items->modelKeys() === [$dewas->id]);

    $this->delete(route('administrasi.manajemen-direktorat.komite.destroy', $komite))->assertRedirect($index);
    expect($komite->fresh())->toBeNull();
    expect(LogActivity::where('table_name', 'tb_komite')->pluck('action')->all())
        ->toBe(['create_komite', 'update_komite', 'delete_komite']);
});

test('komite mutations are restricted to super admin', function () {
    $komite = Komite::create(['nama_komite' => 'Komite Tetap']);
    $this->actingAs(User::factory()->create(['status' => 'active']));
    $this->post(route('administrasi.manajemen-direktorat.komite.store'), ['nama_komite' => 'Tidak Boleh'])->assertForbidden();
    $this->patch(route('administrasi.manajemen-direktorat.komite.update', $komite), ['nama_komite' => 'Tidak Boleh'])->assertForbidden();
    $this->delete(route('administrasi.manajemen-direktorat.komite.destroy', $komite))->assertForbidden();
    expect(Komite::count())->toBe(1);
    expect($komite->fresh()->nama_komite)->toBe('Komite Tetap');
    expect(LogActivity::count())->toBe(0);
});

test('komite validation rejects invalid fields and restores the edit form', function () {
    Direktorat::create(['nama_direktorat' => 'Dewan Pengawas']);
    Komite::create(['nama_komite' => 'Komite Pertama', 'kode_komite' => 'K1']);
    $second = Komite::create(['nama_komite' => 'Komite Kedua', 'kode_komite' => 'K2']);
    $index = route('administrasi.manajemen-direktorat.index');

    $this->from($index)->post(route('administrasi.manajemen-direktorat.komite.store'), [
        '_form' => 'create-komite', 'nama_komite' => '', 'kode_komite' => 'K1',
    ])->assertSessionHasErrors(['nama_komite', 'kode_komite']);

    $this->from($index)->patch(route('administrasi.manajemen-direktorat.komite.update', $second), [
        '_form' => 'edit-komite', '_komite_id' => $second->id,
        'nama_komite' => 'Nama yang diketik', 'kode_komite' => 'K1',
    ])->assertRedirect($index)->assertSessionHasErrors('kode_komite')->assertSessionHasInput('nama_komite', 'Nama yang diketik');
    $this->get($index)->assertOk()->assertSee('Nama yang diketik')->assertSee('Edit Komite');
    expect($second->fresh()->kode_komite)->toBe('K2');

    $this->post(route('administrasi.manajemen-direktorat.komite.store'), [
        'nama_komite' => str_repeat('a', 256), 'kode_komite' => str_repeat('b', 101), 'keterangan' => ['invalid'],
    ])->assertSessionHasErrors(['nama_komite', 'kode_komite', 'keterangan']);
    $this->post(route('administrasi.manajemen-direktorat.komite.store'), ['nama_komite' => 'Tanpa Kode'])
        ->assertSessionHasNoErrors()->assertRedirect($index);
});

test('komite membership prevents deletion regardless of membership status', function (string $status) {
    $komite = Komite::create(['nama_komite' => 'Komite Anggota']);
    $member = User::factory()->create();
    $member->komite()->attach($komite->id, ['status' => $status]);

    $this->delete(route('administrasi.manajemen-direktorat.komite.destroy', $komite))->assertSessionHasErrors('komite');
    expect($komite->fresh())->not->toBeNull();
    expect($member->komite()->first()->pivot->status)->toBe($status);
})->with(['active', 'inactive']);

test('operational komite references prevent deletion across modules', function (string $connection, string $tableName) {
    $komite = Komite::create(['nama_komite' => 'Komite Digunakan']);
    Schema::connection($connection)->create($tableName, function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('komite_id');
    });
    DB::connection($connection)->table($tableName)->insert(['komite_id' => $komite->id]);

    $this->delete(route('administrasi.manajemen-direktorat.komite.destroy', $komite))->assertSessionHasErrors('komite');
    expect($komite->fresh())->not->toBeNull();
    expect(DB::connection($connection)->table($tableName)->value('komite_id'))->toBe($komite->id);
})->with([
    ['mysql_snp', 'tb_butir_pic'], ['mysql_snp', 'tb_review'],
    ['mysql_ragab', 'tb_butir_pic'], ['mysql_rawas', 'tb_butir_pic'],
    ['mysql_rawas', 'tb_review'], ['mysql_djsn', 'tb_butir_pic'],
    ['mysql_djsn', 'tb_review'], ['mysql_eksternal', 'tb_butir_pic'],
]);

test('komite assignment history prevents deletion after membership was removed', function (array $history) {
    $komite = Komite::create(['nama_komite' => 'Komite Riwayat']);
    LogActivity::create(['table_name' => 'users', 'action' => 'update_user', 'old_values' => $history]);
    $this->delete(route('administrasi.manajemen-direktorat.komite.destroy', $komite))->assertSessionHasErrors('komite');
    expect($komite->fresh())->not->toBeNull();
})->with([
    [['assignment' => ['type' => 'komite', 'id' => 1]]],
    [['komite_ids' => [1]]],
]);

test('editing komite preserves cross directorate assignment and existing review permissions', function () {
    $direktorat = Direktorat::create(['nama_direktorat' => 'Direktorat Pelayanan']);
    $komite = Komite::create(['nama_komite' => 'Komite Lama', 'kode_komite' => 'KL']);
    $otherKomite = Komite::create(['nama_komite' => 'Komite Lain']);
    $role = Role::create(['name' => 'pic', 'display_name' => 'PIC', 'level' => 10, 'is_universal' => false]);
    $roles = collect(['pic_snp', 'pic_djsn'])->map(fn ($name) => RoleType::create(['role_id' => $role->id, 'name' => $name]));
    $member = User::factory()->create(['status' => 'active']);

    $this->patch(route('administrasi.manajemen-user.update', $member), [
        'role_type_ids' => $roles->pluck('id')->all(),
        'direktorat_id' => $direktorat->id,
        'assignment' => 'komite:'.$komite->id,
    ])->assertRedirect(route('administrasi.manajemen-user.index'));

    $this->patch(route('administrasi.manajemen-direktorat.komite.update', $komite), [
        'nama_komite' => 'Komite Diperbarui', 'kode_komite' => 'KB',
    ])->assertSessionHasNoErrors();

    $member = $member->fresh();
    expect($member->komiteIds())->toBe([$komite->id]);
    expect($member->unitKerjaIds())->toBe([]);
    expect($member->canReviewSnpByKomite($komite->id))->toBeTrue();
    expect($member->canReviewDjsnByKomite($komite->id))->toBeTrue();
    expect($member->canReviewSnpByKomite($otherKomite->id))->toBeFalse();
    expect($member->canReviewDjsnByKomite($otherKomite->id))->toBeFalse();
    expect($member->komite()->first()->nama_komite)->toBe('Komite Diperbarui');

    $member->komite()->updateExistingPivot($komite->id, ['status' => 'inactive']);
    expect($member->canReviewSnpByKomite($komite->id))->toBeFalse();
    expect($member->canReviewDjsnByKomite($komite->id))->toBeFalse();
});

test('master seeder retains komite edits and does not recreate a deleted komite', function () {
    (new PicMasterSeeder)->run();
    $first = Komite::findOrFail(1);
    $this->patch(route('administrasi.manajemen-direktorat.komite.update', $first), [
        'nama_komite' => 'Nama Komite Diubah', 'kode_komite' => 'BARU', 'keterangan' => 'Tetap tersimpan',
    ])->assertSessionHasNoErrors();
    $this->delete(route('administrasi.manajemen-direktorat.komite.destroy', Komite::findOrFail(2)))->assertSessionHasNoErrors();

    (new PicMasterSeeder)->run();
    expect($first->fresh()->nama_komite)->toBe('Nama Komite Diubah');
    expect($first->fresh()->kode_komite)->toBe('BARU');
    expect($first->fresh()->keterangan)->toBe('Tetap tersimpan');
    expect(Komite::whereKey(2)->exists())->toBeFalse();
});
