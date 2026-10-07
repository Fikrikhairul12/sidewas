<?php

use App\Http\Controllers\Administrasi\PengajuanController;
use App\Models\DeleteRequest;
use App\Models\User;
use App\Services\SnpButirContent;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->withoutVite();
    config(['app.key' => 'base64:'.base64_encode(str_repeat('m', 32)), 'database.default' => 'mysql']);
    foreach (['mysql', 'mysql_ragab', 'mysql_rawas', 'mysql_djsn', 'mysql_eksternal'] as $connection) {
        config(['database.connections.'.$connection => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]]);
        DB::purge($connection);
    }
    (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
    (require database_path('migrations/2026_05_07_000000_create_main_custom_tables.php'))->up();
    Schema::table('tb_direktorat', fn (Blueprint $table) => $table->string('status')->default('active'));
    Schema::table('tb_unit_kerja', fn (Blueprint $table) => $table->string('status')->default('active'));
    DB::table('tb_direktorat')->insert(['id' => 1, 'nama_direktorat' => 'Direktorat Uji', 'status' => 'active']);
    foreach ([1, 2] as $id) {
        DB::table('tb_unit_kerja')->insert(['id' => $id, 'direktorat_id' => 1, 'nama_unit' => 'Unit '.$id, 'status' => 'active']);
    }
    DB::table('tb_komite')->insert(['id' => 1, 'nama_komite' => 'Komite Uji']);
    foreach (['ragab', 'rawas', 'djsn', 'eksternal'] as $module) {
        $schema = Schema::connection('mysql_'.$module);
        $schema->create('tb_cluster', function (Blueprint $table): void {
            $table->id();
            $table->string('nama_cluster');
            $table->string('status')->default('active');
        });
        $schema->create('tb_sub_cluster', function (Blueprint $table): void {
            $table->id();
            $table->integer('cluster_id');
            $table->string('nama_sub_cluster');
            $table->string('status')->default('active');
        });
        $schema->create('tb_record', function (Blueprint $table) use ($module): void {
            $table->id();
            foreach (['id_'.$module, 'nomor_surat', 'tanggal_surat', 'jth_tempo', 'perihal_surat', 'dokumen', 'dokumen_memo', 'status', 'nama_instansi_pengundang'] as $column) {
                $table->string($column)->nullable();
            }
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->timestamps();
        });
        $schema->create('tb_butir_'.$module, function (Blueprint $table) use ($module): void {
            $table->id();
            $table->string('id_'.$module);
            $table->string('id_butir_'.$module);
            $table->text(SnpButirContent::contentField($module))->nullable($module !== 'djsn');
            $table->string('status');
            foreach (['cluster_id', 'sub_cluster_id', 'created_by', 'updated_by'] as $column) {
                $table->integer($column)->nullable();
            }
            $table->string('tanggal_'.$module)->nullable();
            $table->string('agenda_'.$module)->nullable();
            $table->timestamps();
        });
        $schema->create('tb_butir_pic', function (Blueprint $table) use ($module): void {
            $table->id();
            $table->string('id_butir_'.$module);
            $table->string('jenis_pic');
            foreach (['unit_kerja_id', 'komite_id', 'created_by', 'updated_by'] as $column) {
                $table->integer($column)->nullable();
            }
            $table->timestamps();
        });
        foreach (['sub_cluster', 'direktorat'] as $master) {
            $schema->create('tb_butir_'.$master, function (Blueprint $table) use ($module, $master): void {
                $table->id();
                $table->string('id_butir_'.$module);
                $table->integer($master.'_id');
                $table->integer('created_by')->nullable();
                $table->integer('updated_by')->nullable();
                $table->timestamps();
            });
        }
        DB::connection('mysql_'.$module)->table('tb_cluster')->insert(['id' => 1, 'nama_cluster' => 'Cluster']);
        DB::connection('mysql_'.$module)->table('tb_sub_cluster')->insert(['id' => 1, 'cluster_id' => 1, 'nama_sub_cluster' => 'Subcluster']);
    }
    Storage::fake('local');
});

function multiModuleWriter(string $module, bool $super = false): User
{
    $user = User::factory()->create(['status' => 'active']);
    $role = DB::table('tb_role')->insertGetId(['name' => $super ? 'super_admin' : 'moderator', 'display_name' => 'Uji', 'is_universal' => $super]);
    $roleType = DB::table('tb_role_type')->insertGetId(['role_id' => $role, 'name' => $super ? 'super_admin' : 'moderator_'.$module]);
    $user->roleTypes()->attach($roleType, ['status' => 'active']);

    return $user;
}

/** @return array<string, mixed> */
function multiModuleButirInput(string $module, string $content): array
{
    $input = ['cluster_id' => 1, 'sub_cluster_id' => 1, SnpButirContent::contentField($module) => $content, 'komite_id' => 1];
    if ($module === 'djsn') {
        return $input + ['unit_kerja_utama_id' => 1, 'unit_kerja_pendukung_id' => [2]];
    }
    $input += ['tanggal_'.$module => '2026-10-07', 'agenda_'.$module => 'Agenda contoh'];
    if ($module === 'rawas') {
        return $input + ['pic_ids' => ['unit:1', 'komite:1']];
    }

    return $input + ['sub_cluster_ids' => [1], 'direktorat_ids' => [1], 'unit_kerja_ids' => [1]];
}

test('formatted content and images survive creation pending approval and reopening in each module', function (string $module) {
    $writer = multiModuleWriter($module);
    $this->actingAs($writer);
    $recordClass = 'App\\Models\\'.ucfirst($module).'Record';
    $butirClass = 'App\\Models\\'.ucfirst($module).'Butir';
    $field = SnpButirContent::contentField($module);
    $record = $recordClass::create(['nomor_surat' => 'UJI', 'tanggal_surat' => '2026-10-07', 'perihal_surat' => 'Pengujian toolbar', 'nama_instansi_pengundang' => 'Instansi Uji']);
    $legacy = $butirClass::create(['id_'.$module => $record->{'id_'.$module}, $field => "Teks lama <literal>\nTetap utuh.", 'status' => 'terbit']);
    (require database_path('migrations/2026_10_07_081945_expand_multi_module_butir_content.php'))->up();
    (require database_path('migrations/2026_10_05_132825_expand_snp_edit_request_content.php'))->up();
    $upload = $this->postJson(route($module.'.butir-images.store', $record->id), ['image' => UploadedFile::fake()->image('gambar.png')])->assertCreated();
    $image = $upload->json('url');
    expect($image)->toStartWith('/'.$module.'/perekaman/'.$record->id.'/gambar/');
    $this->get($image)->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
    $initial = SnpButirContent::PREFIX.'<p style="text-align:right"><strong>Butir baru</strong><img src="'.$image.'" data-width="25"></p>';
    $this->post(route($module.'.perekaman.butir.store', $record->id), multiModuleButirInput($module, $initial))->assertSessionHasNoErrors()->assertRedirect(route($module.'.perekaman'));
    $butir = $butirClass::orderByDesc('id')->firstOrFail();
    expect($butir->{$field})->toContain('<strong>Butir baru</strong>', 'data-width="25"');
    $stored = $butir->{$field};
    $page = $this->get(route($module.'.perekaman'))->assertOk();
    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true, flags: JSON_THROW_ON_ERROR);
    $browser = new Process(['node', base_path('tests/Unit/MultiModulePerekamanEditor.browser.mjs')], base_path());
    $browser->setInput(json_encode([
        'html' => $page->getContent(), 'module' => $module, 'field' => $field,
        'butirId' => $butir->id, 'butirLabel' => $butir->{'id_butir_'.$module},
        'recordId' => $record->id, 'image' => $image,
        'css' => $manifest['resources/css/app.css']['file'], 'js' => $manifest['resources/js/app.js']['file'],
    ], JSON_THROW_ON_ERROR));
    $browser->setTimeout(90);
    $browser->run();
    expect($browser->isSuccessful())->toBeTrue($browser->getOutput().$browser->getErrorOutput());
    $edited = SnpButirContent::PREFIX.'<p><u>'.str_repeat('Revisi panjang. ', 5000).'</u></p><p style="text-align:center"><img src="'.$image.'" data-width="75"></p>';
    $input = multiModuleButirInput($module, $edited) + ['nomor_surat' => 'UJI', 'tanggal_surat' => '2026-10-07', 'perihal_surat' => 'Surat uji', 'nama_instansi_pengundang' => 'Instansi Uji', 'status' => 'dalam_proses', 'butir_id' => $butir->id, 'butir_status' => 'terbit'];
    $this->patch(route($module.'.perekaman.update', $record->id), $input)->assertSessionHasNoErrors()->assertRedirect(route($module.'.perekaman'));
    $pending = DeleteRequest::firstOrFail();
    expect($pending->status)->toBe('pending_admin_verification')
        ->and($butir->fresh()->{$field})->toBe($stored)
        ->and(json_decode($pending->reason, true)['payload']['butir'][$field])->toContain('data-width="75"');
    $admin = multiModuleWriter($module, true);
    $this->actingAs($admin);
    $pending->update(['status' => 'pending_super_admin_approval']);
    app(PengajuanController::class)->approve($pending);
    expect($pending->fresh()->status)->toBe('approved')
        ->and($butir->fresh()->{$field})->toBe(app(SnpButirContent::class)->normalize($edited, (int) $record->id, $module))
        ->and($legacy->fresh()->{$field})->toBe("Teks lama <literal>\nTetap utuh.");
    $this->get(route($module.'.perekaman'))->assertOk()->assertSee('data-snp-editor', false)->assertSee('data-width', false);
    $this->postJson(route($module.'.butir-images.store', $record->id), ['image' => UploadedFile::fake()->create('bad.svg', 1, 'image/svg+xml')])->assertUnprocessable();
    $this->postJson(route($module.'.butir-images.store', $record->id), ['image' => UploadedFile::fake()->image('large.jpg')->size(2049)])->assertUnprocessable();
    $this->get('/'.$module.'/perekaman/999/gambar/'.basename($image))->assertNotFound();
    $outsider = User::factory()->create(['status' => 'active']);
    $this->actingAs($outsider);
    $this->postJson(route($module.'.butir-images.store', $record->id), ['image' => UploadedFile::fake()->image('gambar.png')])->assertForbidden();
    $this->get($image)->assertForbidden();
})->with(['ragab', 'rawas', 'djsn', 'eksternal']);

test('content capacity migration retains historical values and blocks destructive rollback', function () {
    $migration = require database_path('migrations/2026_10_07_081945_expand_multi_module_butir_content.php');
    foreach (['ragab', 'rawas', 'djsn', 'eksternal'] as $module) {
        DB::connection('mysql_'.$module)->table('tb_butir_'.$module)->insert(['id_'.$module => 'LAMA', 'id_butir_'.$module => 'LAMA.01', SnpButirContent::contentField($module) => 'Data lama', 'status' => 'terbit']);
    }
    $migration->up();
    foreach (['ragab', 'rawas', 'djsn', 'eksternal'] as $module) {
        expect(DB::connection('mysql_'.$module)->table('tb_butir_'.$module)->value(SnpButirContent::contentField($module)))->toBe('Data lama');
        $column = collect(Schema::connection('mysql_'.$module)->getColumns('tb_butir_'.$module))->firstWhere('name', SnpButirContent::contentField($module));
        expect($column['nullable'])->toBe($module !== 'djsn');
    }
    $migration->down();
    $migration->up();
    DB::connection('mysql_djsn')->table('tb_butir_djsn')->update(['butir_djsn' => str_repeat('a', 65536)]);
    expect(fn () => $migration->down())->toThrow(RuntimeException::class)
        ->and(strlen(DB::connection('mysql_djsn')->table('tb_butir_djsn')->value('butir_djsn')))->toBe(65536);
});
