<?php

use App\Models\DjsnButir;
use App\Models\DjsnRecord;
use App\Models\DjsnReview;
use App\Models\DjsnTanggapan;
use App\Models\SnpButir;
use App\Models\SnpRecord;
use App\Models\SnpReview;
use App\Models\SnpTanggapan;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

function setUpWorkflowTestDatabase(): void
{
    config(['app.key' => 'base64:'.base64_encode(str_repeat('w', 32)), 'database.default' => 'mysql']);
    foreach (['mysql', 'mysql_snp', 'mysql_djsn'] as $connection) {
        config(['database.connections.'.$connection => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]]);
        DB::purge($connection);
    }
    (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
    (require database_path('migrations/2026_05_07_000000_create_main_custom_tables.php'))->up();
    foreach (['tb_direktorat', 'tb_unit_kerja'] as $tableName) {
        Schema::table($tableName, fn (Blueprint $table) => $table->string('status')->default('active'));
    }
    DB::table('tb_komite')->insert(['id' => 1, 'nama_komite' => 'Komite Uji']);
    DB::table('tb_direktorat')->insert(['id' => 1, 'nama_direktorat' => 'Direktorat Uji']);
    DB::table('tb_unit_kerja')->insert(['id' => 1, 'direktorat_id' => 1, 'nama_unit' => 'Unit Uji']);
    foreach (['snp', 'djsn'] as $module) {
        $schema = Schema::connection('mysql_'.$module);
        foreach (['tb_cluster' => 'nama_cluster', 'tb_sub_cluster' => 'nama_sub_cluster'] as $name => $label) {
            $schema->create($name, function (Blueprint $table) use ($label): void {
                $table->id();
                $table->string($label);
                $table->integer('cluster_id')->nullable();
                $table->string('status')->default('active');
            });
        }
        $schema->create('tb_record', function (Blueprint $table) use ($module): void {
            $table->id();
            foreach (['id_'.$module, 'nomor_surat', 'tanggal_surat', 'jth_tempo', 'perihal_surat', 'dokumen', 'dokumen_memo', 'status'] as $column) {
                $table->string($column)->nullable();
            }
            foreach (['cluster_id', 'sub_cluster_id', 'created_by', 'updated_by'] as $column) {
                $table->integer($column)->nullable();
            }
            $table->timestamps();
        });
        $schema->create('tb_butir_'.$module, function (Blueprint $table) use ($module): void {
            $table->id();
            $table->string('id_'.$module);
            $table->string('id_butir_'.$module);
            $table->text('butir_'.$module);
            $table->string('status')->default('terbit');
            foreach (['cluster_id', 'sub_cluster_id', 'created_by', 'updated_by'] as $column) {
                $table->integer($column)->nullable();
            }
            $table->timestamps();
        });
        $schema->create('tb_butir_pic', function (Blueprint $table) use ($module): void {
            $table->id();
            $table->string('id_butir_'.$module);
            $table->string('jenis_pic')->nullable();
            foreach (['unit_kerja_id', 'komite_id', 'created_by', 'updated_by'] as $column) {
                $table->integer($column)->nullable();
            }
            $table->timestamps();
        });
        foreach (['tb_tanggapan', 'tb_tindak_lanjut'] as $name) {
            $schema->create($name, function (Blueprint $table) use ($module): void {
                $table->id();
                $table->string('id_butir_'.$module);
                $table->integer('putaran_tl')->default(1);
                foreach (['tanggapan', 'tindak_lanjut', 'deliverables', 'dokumen', 'ubah_tgl', 'status_pengajuan_tgl'] as $column) {
                    $table->text($column)->nullable();
                }
                foreach (['butir_pic_id', 'unit_kerja_id', 'created_by', 'updated_by'] as $column) {
                    $table->integer($column)->nullable();
                }
                $table->timestamps();
            });
        }
        $schema->create('tb_review', function (Blueprint $table) use ($module): void {
            $table->id();
            $table->string('id_butir_'.$module);
            $table->integer('putaran_tl')->default(1);
            $table->string('tahap_review')->default('tanggapan');
            $table->string('status')->default('belum_ditanggapi');
            foreach (['hasil_review', 'deliverables', 'dokumen', 'dokumen_memo'] as $column) {
                $table->text($column)->nullable();
            }
            foreach (['id_tanggapan', 'id_tindak_lanjut', 'komite_id', 'created_by', 'updated_by'] as $column) {
                $table->integer($column)->nullable();
            }
            $table->timestamps();
        });
    }
    Schema::connection('mysql_snp')->create('tb_kompilasi', function (Blueprint $table): void {
        $table->id();
        $table->string('id_butir_snp');
        $table->integer('putaran_tl')->default(1);
        $table->string('tahap_kompilasi')->default('tanggapan');
        $table->string('status')->default('belum_dikompilasi');
        foreach (['hasil_kompilasi', 'deliverables', 'dokumen', 'ubah_tgl', 'status_pengajuan_tgl'] as $column) {
            $table->text($column)->nullable();
        }
        $table->integer('created_by')->nullable();
        $table->integer('updated_by')->nullable();
        $table->timestamps();
    });
    (require database_path('migrations/2026_10_21_134321_add_surat_putaran_foundation.php'))->up();
    (require database_path('migrations/2026_10_21_141454_enable_snp_djsn_initial_letter_statuses.php'))->up();
    Storage::fake('local');
}

function workflowUser(string $roleType): User
{
    $user = User::factory()->create(['status' => 'active']);
    $typeId = DB::table('tb_role_type')->where('name', $roleType)->value('id');
    if ($typeId === null) {
        $roleId = DB::table('tb_role')->insertGetId(['name' => $roleType === 'super_admin' ? 'super_admin' : 'role-'.$user->id, 'display_name' => 'Uji', 'is_universal' => $roleType === 'super_admin']);
        $typeId = DB::table('tb_role_type')->insertGetId(['role_id' => $roleId, 'name' => $roleType]);
    }
    $user->roleTypes()->attach($typeId, ['status' => 'active']);
    $user->komite()->attach(1, ['status' => 'active']);
    $user->unitKerja()->attach(1, ['status' => 'active']);

    return $user;
}

function workflowButir(string $module): SnpButir|DjsnButir
{
    $recordClass = $module === 'snp' ? SnpRecord::class : DjsnRecord::class;
    $butirClass = $module === 'snp' ? SnpButir::class : DjsnButir::class;
    $record = $recordClass::create(['nomor_surat' => 'SURAT-UJI', 'tanggal_surat' => '2026-10-21']);
    $butir = $butirClass::create(['id_'.$module => $record->{'id_'.$module}, 'butir_'.$module => 'Isi butir lama']);
    DB::connection('mysql_'.$module)->table('tb_butir_pic')->insert(['id_butir_'.$module => $butir->{'id_butir_'.$module}, 'jenis_pic' => 'komite', 'komite_id' => 1]);
    DB::connection('mysql_'.$module)->table('tb_butir_pic')->insert(['id_butir_'.$module => $butir->{'id_butir_'.$module}, 'jenis_pic' => 'utama', 'unit_kerja_id' => 1]);

    return $butir;
}

function workflowReview(string $module, SnpButir|DjsnButir $butir): SnpReview|DjsnReview
{
    $reviewClass = $module === 'snp' ? SnpReview::class : DjsnReview::class;
    $responseClass = $module === 'snp' ? SnpTanggapan::class : DjsnTanggapan::class;
    $picId = $butir->butirPics()->where('jenis_pic', 'utama')->firstOrFail()->id;
    $tanggapanId = $responseClass::create(['id_butir_'.$module => $butir->{'id_butir_'.$module}, 'butir_pic_id' => $picId, 'tanggapan' => 'Tanggapan sumber'])->id;

    return $reviewClass::create(['id_butir_'.$module => $butir->{'id_butir_'.$module}, 'id_tanggapan' => $tanggapanId, 'komite_id' => 1, 'tahap_review' => 'tanggapan', 'status' => 'belum_ditanggapi', 'hasil_review' => 'Reviu lama']);
}
