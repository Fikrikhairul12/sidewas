<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected string $connectionName = 'mysql_rawas';

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
            $table->string('id_rawas', 50)->unique();

            $table->unsignedBigInteger('cluster_id')->nullable();
            $table->unsignedBigInteger('sub_cluster_id')->nullable();

            $table->string('nomor_surat')->nullable();
            $table->date('tanggal_surat')->nullable();
            $table->text('perihal_surat')->nullable();
            $table->text('dokumen')->nullable();
            $table->date('jth_tempo')->nullable();
            $table->string('status', 50)->default('draft');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

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

        Schema::connection($this->connectionName)->create('tb_butir_rawas', function (Blueprint $table) {
            $table->id();
            $table->string('id_butir_rawas', 70)->unique();
            $table->string('id_rawas', 50);
            $table->text('butir_rawas');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('id_rawas')
                ->references('id_rawas')
                ->on('tb_record')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::connection($this->connectionName)->create('tb_butir_pic', function (Blueprint $table) {
            $table->id();
            $table->string('id_butir_rawas', 70);
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

            $table->index('id_butir_rawas');
            $table->index('unit_kerja_id');
            $table->index('komite_id');

            $table->unique([
                'id_butir_rawas',
                'unit_kerja_id',
                'komite_id',
                'jenis_pic',
            ], 'tb_butir_pic_rawas_unique');

            $table->foreign('id_butir_rawas')
                ->references('id_butir_rawas')
                ->on('tb_butir_rawas')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::connection($this->connectionName)->create('tb_tindak_lanjut', function (Blueprint $table) {
            $table->id();
            $table->string('id_butir_rawas', 70);
            $table->text('tindak_lanjut')->nullable();
            $table->text('deliverables')->nullable();
            $table->text('dokumen')->nullable();
            $table->date('jth_tempo')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('id_butir_rawas');

            $table->foreign('id_butir_rawas')
                ->references('id_butir_rawas')
                ->on('tb_butir_rawas')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::connection($this->connectionName)->create('tb_review', function (Blueprint $table) {
            $table->id();
            $table->string('id_butir_rawas', 70);
            $table->unsignedBigInteger('id_tindak_lanjut')->nullable();
            $table->unsignedBigInteger('komite_id')->nullable();
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

            $table->index('id_butir_rawas');
            $table->index('id_tindak_lanjut');
            $table->index('komite_id');

            $table->foreign('id_butir_rawas')
                ->references('id_butir_rawas')
                ->on('tb_butir_rawas')
                ->cascadeOnDelete()
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
        Schema::connection($this->connectionName)->dropIfExists('tb_butir_pic');
        Schema::connection($this->connectionName)->dropIfExists('tb_butir_rawas');
        Schema::connection($this->connectionName)->dropIfExists('tb_record');
        Schema::connection($this->connectionName)->dropIfExists('tb_sub_cluster');
        Schema::connection($this->connectionName)->dropIfExists('tb_cluster');
    }
};
