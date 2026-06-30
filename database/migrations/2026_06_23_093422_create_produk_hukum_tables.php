<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected string $connectionName = 'mysql_produk_hukum';

    public function up(): void
    {
        Schema::connection($this->connectionName)->create('tb_jenis_peraturan', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->unique();
            $table->string('singkatan', 50)->nullable();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('urutan');
            $table->index('is_active');
        });

        Schema::connection($this->connectionName)->create('tb_produk_hukum', function (Blueprint $table) {
            $table->id();

            $table->string('kode_produk_hukum', 80)->unique();
            $table->text('judul');
            $table->string('nomor_peraturan_keputusan')->nullable();
            $table->year('tahun_peraturan')->nullable();
            $table->string('jenis_bentuk_peraturan')->nullable();
            $table->string('singkatan_peraturan', 50)->nullable();
            $table->date('tanggal_penetapan')->nullable();
            $table->date('tanggal_diundangkan')->nullable();
            $table->string('sumber_ln_tbn')->nullable();
            $table->string('sumber_tln_tbn')->nullable();
            $table->text('subjek')->nullable();
            $table->string('bidang_pengaturan')->nullable();
            $table->text('abstrak')->nullable();
            $table->text('keterangan')->nullable();
            $table->text('muatan_substansial')->nullable();
            $table->enum('status_peraturan', ['draft', 'berlaku', 'tidak_berlaku'])->default('draft');
            $table->enum('sifat_dokumen', ['publik', 'rahasia'])->default('publik');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            $table->index('kode_produk_hukum');
            $table->index('nomor_peraturan_keputusan');
            $table->index('tahun_peraturan');
            $table->index('jenis_bentuk_peraturan');
            $table->index('bidang_pengaturan');
            $table->index('status_peraturan');
            $table->index('sifat_dokumen');
        });

        Schema::connection($this->connectionName)->create('tb_produk_hukum_file', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('produk_hukum_id');
            $table->enum('bentuk_file', ['file', 'link'])->default('file');
            $table->string('nama_file')->nullable();
            $table->text('path_file')->nullable();
            $table->text('link_file')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('ukuran_file')->nullable();
            $table->string('jenis_file')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('produk_hukum_id');
            $table->index('bentuk_file');
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
            $table->string('nomor_produk_hukum_terkait')->nullable();
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
        Schema::connection($this->connectionName)->dropIfExists('tb_jenis_peraturan');
    }
};
