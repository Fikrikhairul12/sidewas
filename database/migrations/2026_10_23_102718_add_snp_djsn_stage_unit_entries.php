<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['mysql_snp', 'mysql_djsn'] as $connection) {
            $schema = Schema::connection($connection);
            foreach (['tb_tanggapan', 'tb_tindak_lanjut'] as $source) {
                foreach (['submitted_after_compilation_at', 'changed_after_compilation_at'] as $column) {
                    if (! $schema->hasColumn($source, $column)) {
                        $schema->table($source, fn (Blueprint $table) => $table->timestamp($column)->nullable());
                    }
                }
            }
            foreach (['unit_kerja_id', 'butir_pic_id'] as $column) {
                if (! $schema->hasColumn('tb_tindak_lanjut', $column)) {
                    $schema->table('tb_tindak_lanjut', fn (Blueprint $table) => $table->unsignedBigInteger($column)->nullable()->index());
                }
            }
            if (! $schema->hasColumn('tb_tindak_lanjut', 'putaran_tl')) {
                $schema->table('tb_tindak_lanjut', fn (Blueprint $table) => $table->unsignedInteger('putaran_tl')->nullable());
            }
            if (! $schema->hasColumn('tb_tindak_lanjut', 'submitted_after_close_at')) {
                $schema->table('tb_tindak_lanjut', fn (Blueprint $table) => $table->timestamp('submitted_after_close_at')->nullable());
            }
            if (! $schema->hasTable('tb_isian_unit_tahap')) {
                $schema->create('tb_isian_unit_tahap', function (Blueprint $table): void {
                    $table->id();
                    $table->foreignId('tahap_id')->constrained('tb_surat_tahap')->restrictOnDelete();
                    $table->string('butir_key', 90);
                    $table->unsignedBigInteger('unit_kerja_id');
                    $table->string('tabel_sumber', 30);
                    $table->unsignedBigInteger('sumber_id');
                    $table->timestamps();
                    $table->unique(['tahap_id', 'butir_key', 'unit_kerja_id'], 'isian_tahap_butir_unit_unique');
                    $table->unique(['tabel_sumber', 'sumber_id'], 'isian_tahap_sumber_unique');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['mysql_snp', 'mysql_djsn'] as $connection) {
            $db = DB::connection($connection);
            if ($db->table('tb_isian_unit_tahap')->exists()) {
                throw new RuntimeException('Rollback R2 dibatalkan karena sudah ada isian tahap revisi.');
            }
            foreach (['tb_tanggapan', 'tb_tindak_lanjut'] as $source) {
                if ($db->table($source)->whereNotNull('submitted_after_compilation_at')->orWhereNotNull('changed_after_compilation_at')->exists()) {
                    throw new RuntimeException('Rollback R2 dibatalkan karena penanda isian sudah menjadi riwayat.');
                }
            }
        }
        foreach (['mysql_snp', 'mysql_djsn'] as $connection) {
            $schema = Schema::connection($connection);
            $schema->drop('tb_isian_unit_tahap');
            foreach (['tb_tanggapan', 'tb_tindak_lanjut'] as $source) {
                $schema->table($source, fn (Blueprint $table) => $table->dropColumn(['submitted_after_compilation_at', 'changed_after_compilation_at']));
            }
            // Keep nullable compatibility columns, which may belong to an earlier installation.
        }
    }
};
