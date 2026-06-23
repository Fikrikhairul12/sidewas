<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected string $connectionName = 'mysql_produk_hukum';

    public function up(): void
    {
        Schema::connection($this->connectionName)->create('tb_produk_hukum', function (Blueprint $table) {
            $table->id();

            $table->string('kode_produk_hukum', 80)->unique();
            $table->string('tipe_dokumen')->nullable();
            $table->text('judul');
            $table->string('nomor_peraturan')->nullable();
            $table->year('tahun_peraturan')->nullable();
            $table->string('jenis_bentuk_peraturan')->nullable();
            $table->string('singkatan_peraturan', 50)->nullable();
            $table->string('tempat_penetapan')->nullable();
            $table->date('tanggal_penetapan')->nullable();
            $table->date('tanggal_diundangkan')->nullable();
            $table->string('sumber_ln')->nullable();
            $table->string('sumber_tln')->nullable();
            $table->text('subjek')->nullable();
            $table->string('bahasa')->default('Indonesia');
            $table->string('lokasi')->nullable();
            $table->string('bidang_hukum')->nullable();
            $table->text('abstrak')->nullable();
            $table->string('status_peraturan')->nullable();
            $table->enum('sifat_dokumen', ['publik', 'rahasia'])->default('publik');
            $table->enum('status_publish', ['draft', 'terbit', 'arsip'])->default('draft');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            $table->index('kode_produk_hukum');
            $table->index('nomor_peraturan');
            $table->index('tahun_peraturan');
            $table->index('status_peraturan');
            $table->index('sifat_dokumen');
            $table->index('status_publish');
        });

        Schema::connection($this->connectionName)->create('tb_produk_hukum_file', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('produk_hukum_id');
            $table->string('nama_file');
            $table->text('path_file');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('ukuran_file')->nullable();
            $table->string('jenis_file')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('produk_hukum_id');
            $table->index('jenis_file');

            $table->foreign('produk_hukum_id')
                ->references('id')
                ->on('tb_produk_hukum')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::connection($this->connectionName)->create('tb_produk_hukum_relasi', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('produk_hukum_id');
            $table->enum('jenis_relasi', [
                'mencabut',
                'dicabut_oleh',
                'mengubah',
                'diubah_oleh',
                'terkait',
            ]);
            $table->unsignedBigInteger('produk_hukum_terkait_id')->nullable();
            $table->string('nomor_peraturan_terkait')->nullable();
            $table->text('judul_terkait')->nullable();
            $table->text('keterangan')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('produk_hukum_id');
            $table->index('produk_hukum_terkait_id');
            $table->index('jenis_relasi');

            $table->foreign('produk_hukum_id')
                ->references('id')
                ->on('tb_produk_hukum')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('produk_hukum_terkait_id')
                ->references('id')
                ->on('tb_produk_hukum')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connectionName)->dropIfExists('tb_produk_hukum_relasi');
        Schema::connection($this->connectionName)->dropIfExists('tb_produk_hukum_file');
        Schema::connection($this->connectionName)->dropIfExists('tb_produk_hukum');
    }
};
