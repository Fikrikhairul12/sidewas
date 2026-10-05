<?php

use App\Http\Controllers\Administrasi\PengajuanController;
use App\Models\DeleteRequest;
use App\Models\SnpButir;
use App\Models\SnpRecord;
use App\Models\User;
use App\Services\SnpButirContent;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('s', 32)), 'database.default' => 'mysql']);
    foreach (['mysql', 'mysql_snp'] as $connection) {
        config(['database.connections.'.$connection => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]]);
        DB::purge($connection);
    }
    (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
    (require database_path('migrations/2026_05_07_000000_create_main_custom_tables.php'))->up();
    Schema::table('tb_unit_kerja', fn (Blueprint $table) => $table->string('status')->default('active'));
    $snp = Schema::connection('mysql_snp');
    $snp->create('tb_cluster', function (Blueprint $table) {
        $table->id();
        $table->string('nama_cluster');
    });
    $snp->create('tb_sub_cluster', function (Blueprint $table) {
        $table->id();
        $table->integer('cluster_id');
        $table->string('nama_sub_cluster');
    });
    $snp->create('tb_record', function (Blueprint $table) {
        $table->id();
        foreach (['id_snp', 'nomor_surat', 'tanggal_surat', 'jth_tempo', 'perihal_surat', 'dokumen', 'dokumen_memo', 'status'] as $column) {
            $table->string($column)->nullable();
        }
        foreach (['cluster_id', 'sub_cluster_id', 'created_by', 'updated_by'] as $column) {
            $table->integer($column)->nullable();
        }
        $table->timestamps();
    });
    $snp->create('tb_butir_snp', function (Blueprint $table) {
        $table->id();
        $table->string('id_snp');
        $table->string('id_butir_snp');
        $table->text('butir_snp');
        $table->string('status');
        $table->integer('created_by')->nullable();
        $table->integer('updated_by')->nullable();
        $table->timestamps();
    });
    $snp->create('tb_butir_pic', function (Blueprint $table) {
        $table->id();
        $table->string('id_butir_snp');
        $table->string('jenis_pic');
        foreach (['unit_kerja_id', 'komite_id', 'created_by', 'updated_by'] as $column) {
            $table->integer($column)->nullable();
        }
        $table->timestamps();
    });
    DB::table('tb_unit_kerja')->insert(['id' => 1, 'nama_unit' => 'Unit Uji', 'status' => 'active']);
    DB::table('tb_komite')->insert(['id' => 1, 'nama_komite' => 'Komite Uji']);
    DB::connection('mysql_snp')->table('tb_cluster')->insert(['id' => 1, 'nama_cluster' => 'Cluster']);
    DB::connection('mysql_snp')->table('tb_sub_cluster')->insert(['id' => 1, 'cluster_id' => 1, 'nama_sub_cluster' => 'Sub Cluster']);
    Storage::fake('local');
});

function snpWriter(bool $super = false): User
{
    $user = User::factory()->create(['status' => 'active']);
    $roleId = DB::table('tb_role')->insertGetId(['name' => $super ? 'super_admin' : 'moderator', 'display_name' => 'Uji', 'is_universal' => $super]);
    $roleTypeId = DB::table('tb_role_type')->insertGetId(['role_id' => $roleId, 'name' => $super ? 'super_admin' : 'moderator_snp']);
    $user->roleTypes()->attach($roleTypeId, ['status' => 'active']);

    return $user;
}

test('rich butir survives create pending approval and reopen without changing legacy siblings', function () {
    $writer = snpWriter();
    $this->actingAs($writer);
    $record = SnpRecord::create(['nomor_surat' => 'UJI', 'tanggal_surat' => '2026-10-01', 'cluster_id' => 1, 'sub_cluster_id' => 1]);
    $legacy = SnpButir::create(['id_snp' => $record->id_snp, 'butir_snp' => "Teks lama <literal>\nTetap utuh.", 'status' => 'terbit']);
    (require database_path('migrations/2026_10_05_131500_expand_snp_butir_content.php'))->up();
    (require database_path('migrations/2026_10_05_132825_expand_snp_edit_request_content.php'))->up();
    expect($legacy->fresh()->butir_snp)->toBe("Teks lama <literal>\nTetap utuh.");
    $upload = $this->postJson(route('snp.butir-images.store', $record), ['image' => UploadedFile::fake()->image('gambar.png')])->assertCreated();
    $image = $upload->json('url');
    $initial = SnpButirContent::PREFIX.'<p style="text-align:right"><strong>Butir baru</strong><img src="'.$image.'" data-width="25"></p>';
    $this->post(route('snp.perekaman.butir.store', $record), ['butir_snp' => $initial, 'unit_kerja_utama_id' => 1, 'komite_id' => 1])->assertRedirect(route('snp.perekaman'));
    $butir = SnpButir::orderByDesc('id')->first();
    expect($butir->butir_snp)->toContain('<strong>Butir baru</strong>', 'data-width="25"');
    $stored = $butir->butir_snp;
    $edited = SnpButirContent::PREFIX.'<p><u>'.str_repeat('Revisi panjang. ', 5000).'</u></p><p style="text-align:center"><img src="'.$image.'" data-width="75"></p>';
    $this->patch(route('snp.perekaman.update', $record), [
        'tanggal_surat' => '2026-10-01', 'perihal_surat' => 'Surat uji', 'cluster_id' => 1, 'sub_cluster_id' => 1, 'status' => 'dalam_proses',
        'butir_id' => $butir->id, 'butir_snp' => $edited, 'butir_status' => 'terbit', 'unit_kerja_utama_id' => 1, 'komite_id' => 1,
    ])->assertRedirect(route('snp.perekaman'));
    $pending = DeleteRequest::firstOrFail();
    expect($pending->status)->toBe('pending_admin_verification')
        ->and($butir->fresh()->butir_snp)->toBe($stored)
        ->and(json_decode($pending->reason, true)['payload']['butir']['butir_snp'])->toContain('data-width="75"');
    $admin = snpWriter(true);
    $this->actingAs($admin);
    $pending->update(['status' => 'pending_super_admin_approval']);
    $controller = app(PengajuanController::class);
    $controller->approve($pending);
    expect($pending->fresh()->status)->toBe('approved')
        ->and($butir->fresh()->butir_snp)->toBe(app(SnpButirContent::class)->normalize($edited, (int) $record->id))
        ->and($legacy->fresh()->butir_snp)->toBe("Teks lama <literal>\nTetap utuh.");
    $this->get($image)->assertOk()->assertHeader('Content-Type', 'image/png');
    Storage::disk('local')->assertExists(SnpButirContent::imageReference($image)['path']);
});
