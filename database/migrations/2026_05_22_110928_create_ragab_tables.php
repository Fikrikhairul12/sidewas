<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected string $connectionName = 'mysql_ragab';

    public function up(): void
    {
        Schema::connection($this->connectionName)->create('tb_cluster', function (Blueprint $table) {
            $table->id();
            $table->string('nama_cluster');
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::connection($this->connectionName)->create('tb_sub_cluster', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cluster_id');
            $table->string('nama_sub_cluster');
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->foreign('cluster_id')
                ->references('id')
                ->on('tb_cluster')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::connection($this->connectionName)->create('tb_record', function (Blueprint $table) {
            $table->id();
            $table->string('id_ragab', 50)->unique();
            $table->string('nomor_surat')->nullable();
            $table->date('tanggal_surat')->nullable();
            $table->text('perihal_surat')->nullable();
            $table->text('dokumen')->nullable();
            $table->text('dokumen_memo')->nullable();
            $table->date('jth_tempo')->nullable();

            $table->enum('status', [
                'draft',
                'terbit',
                'dalam_proses',
                'diusulkan_tuntas',
                'tuntas',
            ])->default('draft');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::connection($this->connectionName)->create('tb_butir_ragab', function (Blueprint $table) {
            $table->id();
            $table->string('id_butir_ragab', 70)->unique();
            $table->string('id_ragab', 50);

            $table->unsignedBigInteger('cluster_id')->nullable();
            $table->unsignedBigInteger('sub_cluster_id')->nullable();

            $table->date('tanggal_ragab')->nullable();
            $table->text('agenda_ragab')->nullable();
            $table->text('keputusan_ragab')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('id_ragab');
            $table->index('cluster_id');
            $table->index('sub_cluster_id');
            $table->index('tanggal_ragab');

            $table->foreign('id_ragab')
                ->references('id_ragab')
                ->on('tb_record')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('cluster_id')
                ->references('id')
                ->on('tb_cluster')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('sub_cluster_id')
                ->references('id')
                ->on('tb_sub_cluster')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::connection($this->connectionName)->create('tb_butir_direktorat', function (Blueprint $table) {
            $table->id();
            $table->string('id_butir_ragab', 70);
            $table->unsignedBigInteger('direktorat_id');
            $table->timestamps();

            $table->index('id_butir_ragab');
            $table->index('direktorat_id');

            $table->unique([
                'id_butir_ragab',
                'direktorat_id',
            ], 'tb_butir_direktorat_unique');

            $table->foreign('id_butir_ragab')
                ->references('id_butir_ragab')
                ->on('tb_butir_ragab')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::connection($this->connectionName)->create('tb_butir_pic', function (Blueprint $table) {
            $table->id();
            $table->string('id_butir_ragab', 70);
            $table->unsignedBigInteger('unit_kerja_id')->nullable();
            $table->unsignedBigInteger('komite_id')->nullable();

            $table->enum('jenis_pic', [
                'unit',
                'komite',
            ]);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('id_butir_ragab');
            $table->index('unit_kerja_id');
            $table->index('komite_id');
            $table->index('jenis_pic');

            $table->unique([
                'id_butir_ragab',
                'unit_kerja_id',
                'jenis_pic',
            ], 'tb_butir_pic_unit_unique');

            $table->unique([
                'id_butir_ragab',
                'komite_id',
                'jenis_pic',
            ], 'tb_butir_pic_komite_unique');

            $table->foreign('id_butir_ragab')
                ->references('id_butir_ragab')
                ->on('tb_butir_ragab')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });

        DB::connection($this->connectionName)->statement("
            ALTER TABLE tb_butir_pic
            ADD CONSTRAINT chk_ragab_butir_pic_source
            CHECK (
                (jenis_pic = 'unit' AND unit_kerja_id IS NOT NULL AND komite_id IS NULL)
                OR
                (jenis_pic = 'komite' AND unit_kerja_id IS NULL AND komite_id IS NOT NULL)
            )
        ");

        Schema::connection($this->connectionName)->create('tb_tindak_lanjut', function (Blueprint $table) {
            $table->id();
            $table->string('id_butir_ragab', 70);

            // Direktorat dan PIC Unit yang dipilih pada saat input tindak lanjut.
            $table->unsignedBigInteger('unit_kerja_id')->nullable();

            $table->text('tindak_lanjut')->nullable();
            $table->text('deliverables')->nullable();
            $table->text('dokumen')->nullable();
            $table->date('jth_tempo')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('id_butir_ragab');
            $table->index('unit_kerja_id');
            $table->index('created_at');

            $table->foreign('id_butir_ragab')
                ->references('id_butir_ragab')
                ->on('tb_butir_ragab')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::connection($this->connectionName)->create('tb_review', function (Blueprint $table) {
            $table->id();

            // Review RAGAB dilakukan per butir, bukan per tindak lanjut.
            $table->string('id_butir_ragab', 70);

            $table->string('tahap_review', 50)->default('tindak_lanjut');
            $table->text('hasil_review')->nullable();
            $table->text('deliverables')->nullable();
            $table->text('dokumen')->nullable();

            $table->enum('status', [
                'belum_ditanggapi',
                'dalam_proses_reviu_dewan_pengawas',
                'selesai_tuntas',
            ])->default('belum_ditanggapi');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('id_butir_ragab');
            $table->index('tahap_review');
            $table->index('status');

            $table->unique([
                'id_butir_ragab',
                'tahap_review',
            ], 'tb_review_ragab_butir_tahap_unique');

            $table->foreign('id_butir_ragab')
                ->references('id_butir_ragab')
                ->on('tb_butir_ragab')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connectionName)->dropIfExists('tb_review');
        Schema::connection($this->connectionName)->dropIfExists('tb_tindak_lanjut');
        Schema::connection($this->connectionName)->dropIfExists('tb_butir_pic');
        Schema::connection($this->connectionName)->dropIfExists('tb_butir_direktorat');
        Schema::connection($this->connectionName)->dropIfExists('tb_butir_ragab');
        Schema::connection($this->connectionName)->dropIfExists('tb_record');
        Schema::connection($this->connectionName)->dropIfExists('tb_sub_cluster');
        Schema::connection($this->connectionName)->dropIfExists('tb_cluster');
    }
};
