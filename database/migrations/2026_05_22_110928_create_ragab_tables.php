<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

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
            $table->foreignId('cluster_id')
                ->constrained('tb_cluster')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->string('nama_sub_cluster');
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::connection($this->connectionName)->create('tb_record', function (Blueprint $table) {
            $table->id();
            $table->string('id_ragab', 50)->unique();
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
            $table->text('dokumen')->nullable();
            $table->date('jth_tempo')->nullable();
            $table->string('status', 50)->default('draft');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::connection($this->connectionName)->create('tb_butir_ragab', function (Blueprint $table) {
            $table->id();
            $table->string('id_butir_ragab', 70)->unique();
            $table->string('id_ragab', 50);
            $table->text('butir_ragab');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->foreign('id_ragab')
                ->references('id_ragab')
                ->on('tb_record')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::connection($this->connectionName)->create('tb_butir_pic', function (Blueprint $table) {
            $table->id();
            $table->string('id_butir_ragab', 70)->unique();
            $table->unsignedBigInteger('unit_kerja_id')->nullable()->index();
            $table->unsignedBigInteger('komite_id')->nullable()->index();
            $table->enum('jenis_pic', [
                'utama',
                'pendukung',
                'komite'
            ]);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique([
                'id_butir_ragab',
                'unit_kerja_id',
                'komite_id',
                'jenis_pic'
            ], 'tb_butir_pic_ragab_unique');
            $table->foreign('id_butir_ragab')
                ->references('id_butir_ragab')
                ->on('tb_butir_ragab')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::connection($this->connectionName)->create('tb_tindak_lanjut', function (Blueprint $table) {
            $table->id();
            $table->string('id_butir_ragab', 70);
            $table->text('tindak_lanjut')->nullable();
            $table->text('deliverables')->nullable();
            $table->text('dokumen')->nullable();
            $table->date('jth_tempo')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->foreign('id_butir_ragab')
                ->references('id_butir_ragab')
                ->on('tb_butir_ragab')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::connection($this->connectionName)->create('tb_review', function (Blueprint $table) {
            $table->id();
            $table->string('id_butir_ragab', 70);
            $table->foreignId('id_tindak_lanjut')
                ->nullable()
                ->constrained('tb_tindak_lanjut')
                ->nullOnDelete()
                ->cascadeOnDelete();
            $table->unsignedBigInteger('komite_id')->nullable()->index();
            $table->text('hasil_review')->nullable();
            $table->text('deliverables')->nullable();
            $table->text('dokumen')->nullable();
            $table->enum('status', [
                'belum_ditanggapi',
                'dalam_proses_reviu_dewan_pengawas',
                'selesai_tuntas'
            ])->default('belum_ditanggapi');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->foreign('id_butir_ragab')
                ->references('id_butir_ragab')
                ->on('tb_butir_ragab')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connectionName)->dropIfExists('tb_review');
        Schema::connection($this->connectionName)->dropIfExists('tb_tindak_lanjut');
        Schema::connection($this->connectionName)->dropIfExists('tb_butir_pic');
        Schema::connection($this->connectionName)->dropIfExists('tb_butir_ragab');
        Schema::connection($this->connectionName)->dropIfExists('tb_record');
        Schema::connection($this->connectionName)->dropIfExists('tb_sub_cluster');
        Schema::connection($this->connectionName)->dropIfExists('tb_cluster');
    }
};
