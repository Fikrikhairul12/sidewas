<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected string $connectionName = 'mysql_djsn';

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
            $table->string('id_djsn', 70)->unique();
            $table->string('nomor_surat')->nullable();
            $table->date('tanggal_surat')->nullable();
            $table->text('perihal_surat')->nullable();
            $table->text('dokumen')->nullable();
            $table->date('jth_tempo')->nullable();
            $table->string('status', 50)->default('draft');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->index('status');

        });

        Schema::connection($this->connectionName)->create('tb_butir_djsn', function (Blueprint $table) {
            $table->id();
            $table->string('id_butir_djsn', 90)->unique();
            $table->string('id_djsn', 70);
            $table->text('butir_djsn');
            $table->unsignedBigInteger('cluster_id')->nullable();
            $table->unsignedBigInteger('sub_cluster_id')->nullable();
            $table->enum('status', [
                'terbit',
                'dalam_proses',
                'diusulkan_tuntas',
                'selesai_tuntas',
            ])->default('terbit');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->index('status');

            $table->foreign('id_djsn')
                ->references('id_djsn')
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

        Schema::connection($this->connectionName)->create('tb_butir_pic', function (Blueprint $table) {
            $table->id();
            $table->string('id_butir_djsn', 90);
            $table->unsignedBigInteger('unit_kerja_id')->nullable();
            $table->unsignedBigInteger('komite_id')->nullable();

            $table->enum('jenis_pic', [
                'utama',
                'pendukung',
                'komite',
            ]);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('id_butir_djsn');
            $table->index('unit_kerja_id');
            $table->index('komite_id');

            $table->unique([
                'id_butir_djsn',
                'unit_kerja_id',
                'komite_id',
                'jenis_pic',
            ], 'tb_butir_pic_djsn_unique');

            $table->foreign('id_butir_djsn')
                ->references('id_butir_djsn')
                ->on('tb_butir_djsn')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::connection($this->connectionName)->create('tb_tanggapan', function (Blueprint $table) {
            $table->id();
            $table->string('id_butir_djsn', 90);
            $table->text('tanggapan')->nullable();
            $table->text('deliverables')->nullable();
            $table->text('dokumen')->nullable();
            $table->date('ubah_tgl')->nullable();

            $table->enum('status_pengajuan_tgl', [
                'pending',
                'disetujui',
                'ditolak',
            ])->default('pending');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('id_butir_djsn');

            $table->foreign('id_butir_djsn')
                ->references('id_butir_djsn')
                ->on('tb_butir_djsn')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::connection($this->connectionName)->create('tb_tindak_lanjut', function (Blueprint $table) {
            $table->id();
            $table->string('id_butir_djsn', 90);
            $table->text('tindak_lanjut')->nullable();
            $table->text('deliverables')->nullable();
            $table->text('dokumen')->nullable();
            $table->date('jth_tempo')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('id_butir_djsn');

            $table->foreign('id_butir_djsn')
                ->references('id_butir_djsn')
                ->on('tb_butir_djsn')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::connection($this->connectionName)->create('tb_review', function (Blueprint $table) {
            $table->id();

            $table->string('id_butir_djsn', 90);
            $table->unsignedBigInteger('id_tanggapan')->nullable();
            $table->unsignedBigInteger('id_tindak_lanjut')->nullable();
            $table->unsignedBigInteger('komite_id')->nullable();

            $table->enum('tahap_review', [
                'tanggapan',
                'tindak_lanjut',
            ]);

            $table->text('hasil_review')->nullable();
            $table->text('deliverables')->nullable();
            $table->text('dokumen')->nullable();

            $table->enum('status', [
                'belum_ditanggapi',
                'dalam_proses_reviu_dewan_pengawas',
                'dalam_proses_tindak_lanjut_direksi',
                'selesai_tuntas',
            ])->default('belum_ditanggapi');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('id_butir_djsn');
            $table->index('id_tanggapan');
            $table->index('id_tindak_lanjut');
            $table->index('komite_id');
            $table->index('tahap_review');
            $table->index('status');

            $table->foreign('id_butir_djsn')
                ->references('id_butir_djsn')
                ->on('tb_butir_djsn')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('id_tanggapan')
                ->references('id')
                ->on('tb_tanggapan')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('id_tindak_lanjut')
                ->references('id')
                ->on('tb_tindak_lanjut')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connectionName)->dropIfExists('tb_review');
        Schema::connection($this->connectionName)->dropIfExists('tb_tindak_lanjut');
        Schema::connection($this->connectionName)->dropIfExists('tb_tanggapan');
        Schema::connection($this->connectionName)->dropIfExists('tb_butir_pic');
        Schema::connection($this->connectionName)->dropIfExists('tb_butir_djsn');
        Schema::connection($this->connectionName)->dropIfExists('tb_record');
        Schema::connection($this->connectionName)->dropIfExists('tb_sub_cluster');
        Schema::connection($this->connectionName)->dropIfExists('tb_cluster');
    }
};
