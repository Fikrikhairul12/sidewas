<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected string $connectionName = 'mysql_snp';

    public function up(): void
    {
        Schema::connection($this->connectionName)->create('tb_kompilasi', function (Blueprint $table) {
            $table->id();

            // Kompilasi dilakukan per butir SNP
            $table->string('id_butir_snp', 70);

            // Tahap data yang sedang dikompilasi:
            // tanggapan atau tindak_lanjut
            $table->string('tahap_kompilasi', 50)->default('tanggapan');

            $table->text('hasil_kompilasi')->nullable();
            $table->text('deliverables')->nullable();
            $table->text('dokumen')->nullable();

            $table->enum('status', [
                'belum_dikompilasi',
                'dalam_proses_reviu_dewas',
            ])->default('belum_dikompilasi');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            $table->index('id_butir_snp');
            $table->index('tahap_kompilasi');
            $table->index('status');

            $table->foreign('id_butir_snp')
                ->references('id_butir_snp')
                ->on('tb_butir_snp')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connectionName)->dropIfExists('tb_kompilasi');
    }
};