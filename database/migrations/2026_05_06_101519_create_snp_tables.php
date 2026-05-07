<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected string $connectionName = 'mysql_snp';

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
            $table->foreignId('cluster_id')
                ->constrained('tb_cluster')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('nama_sub_cluster');
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::connection($this->connectionName)->create('tb_record', function (Blueprint $table) {
            $table->id();
            $table->string('id_snp', 50)->unique();
            $table->foreignId('cluster_id')
                ->nullable()
                ->constrained('tb_cluster')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('sub_cluster_id')
                ->nullable()
                ->constrained('tb_sub_cluster')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->string('nomor_surat')->nullable();
            $table->date('tanggal_surat')->nullable();
            $table->text('perihal_surat')->nullable();
            $table->date('jth_tempo')->nullable();
            $table->string('status', 50)->default('draft');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::connection($this->connectionName)->create('tb_butir_snp', function (Blueprint $table) {
            $table->id();
            $table->string('id_butir_snp', 70)->unique();
            $table->string('id_snp', 50);
            $table->text('butir_snp');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->foreign('id_snp')
                ->references('id_snp')
                ->on('tb_record')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });

        Schema::connection($this->connectionName)->create('tb_butir_pic', function (Blueprint $table) {
            $table->id();
            $table->string('id_butir_snp', 70);
            $table->unsignedBigInteger('unit_kerja_id')->nullable();
            $table->unsignedBigInteger('komite_id')->nullable();
            $table->enum('jenis_pic', ['utama', 'pendukung']);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->foreign('id_butir_snp')
                ->references('id_butir_snp')
                ->on('tb_butir_snp')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->index('unit_kerja_id');
            $table->index('komite_id');
            $table->unique(['id_butir_snp', 'unit_kerja_id', 'komite_id', 'jenis_pic'], 'tb_butir_pic_unique');
        });

        DB::connection($this->connectionName)->statement("
            ALTER TABLE tb_butir_pic
            ADD CONSTRAINT chk_butir_pic_source
            CHECK (
                (unit_kerja_id IS NOT NULL AND komite_id IS NULL)
                OR
                (unit_kerja_id IS NULL AND komite_id IS NOT NULL)
            )
        ");

        Schema::connection($this->connectionName)->create('tb_tanggapan', function (Blueprint $table) {
            $table->id();
            $table->string('id_butir_snp', 70);
            $table->text('tanggapan')->nullable();
            $table->text('deliverables')->nullable();
            $table->text('dokumen')->nullable();
            $table->date('ubah_tgl')->nullable();
            $table->enum('status_pengajuan_tgl', [
                'tidak_ada',
                'pending',
                'disetujui',
                'ditolak',
            ])->default('tidak_ada');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->foreign('id_butir_snp')
                ->references('id_butir_snp')
                ->on('tb_butir_snp')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });

        Schema::connection($this->connectionName)->create('tb_tindak_lanjut', function (Blueprint $table) {
            $table->id();
            $table->string('id_butir_snp', 70);
            $table->text('tindak_lanjut')->nullable();
            $table->text('deliverables')->nullable();
            $table->text('dokumen')->nullable();
            $table->date('jth_tempo')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->foreign('id_butir_snp')
                ->references('id_butir_snp')
                ->on('tb_butir_snp')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });

        Schema::connection($this->connectionName)->create('tb_review', function (Blueprint $table) {
            $table->id();
            $table->string('id_butir_snp', 70);
            $table->foreignId('id_tanggapan')
                ->nullable()
                ->constrained('tb_tanggapan')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('id_tindak_lanjut')
                ->nullable()
                ->constrained('tb_tindak_lanjut')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->enum('tahap_review', ['tanggapan', 'tindak_lanjut']);
            $table->unsignedBigInteger('komite_id')->nullable();
            $table->text('hasil_review')->nullable();
            $table->text('deliverables')->nullable();
            $table->enum('status', [
                'belum_ditanggapi',
                'dalam_proses_reviu_dewan_pengawas',
                'dalam_proses_tindak_lanjut_direksi',
                'selesai_tuntas',
            ])->default('belum_ditanggapi');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->foreign('id_butir_snp')
                ->references('id_butir_snp')
                ->on('tb_butir_snp')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->index('komite_id');
        });

        DB::connection($this->connectionName)->statement("
            ALTER TABLE tb_review
            ADD CONSTRAINT chk_review_reference
            CHECK (
                (id_tanggapan IS NOT NULL AND id_tindak_lanjut IS NULL)
                OR
                (id_tanggapan IS NULL AND id_tindak_lanjut IS NOT NULL)
            )
        ");
    }

    public function down(): void
    {
        Schema::connection($this->connectionName)->dropIfExists('tb_review');
        Schema::connection($this->connectionName)->dropIfExists('tb_tindak_lanjut');
        Schema::connection($this->connectionName)->dropIfExists('tb_tanggapan');
        Schema::connection($this->connectionName)->dropIfExists('tb_butir_pic');
        Schema::connection($this->connectionName)->dropIfExists('tb_butir_snp');
        Schema::connection($this->connectionName)->dropIfExists('tb_record');
        Schema::connection($this->connectionName)->dropIfExists('tb_sub_cluster');
        Schema::connection($this->connectionName)->dropIfExists('tb_cluster');
    }
};
