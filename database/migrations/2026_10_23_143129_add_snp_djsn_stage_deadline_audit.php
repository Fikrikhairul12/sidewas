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
            if (! $schema->hasTable('tb_surat_tanggal_riwayat')) {
                $schema->create('tb_surat_tanggal_riwayat', function (Blueprint $table): void {
                    $table->id();
                    $table->foreignId('record_id')->nullable()->constrained('tb_record')->nullOnDelete();
                    $table->foreignId('tahap_id')->constrained('tb_surat_tahap')->restrictOnDelete();
                    $table->unsignedBigInteger('kompilasi_id')->nullable();
                    $table->unsignedBigInteger('review_id')->nullable();
                    $table->string('kunci', 100)->unique();
                    $table->string('jenis', 40);
                    $table->date('tanggal_lama')->nullable();
                    $table->date('tanggal_baru')->nullable();
                    $table->text('alasan');
                    $table->unsignedBigInteger('pengguna_id')->nullable();
                    $table->timestamp('diputuskan_at');
                    $table->json('bukti');
                    $table->timestamps();
                    $table->index(['tahap_id', 'id']);
                });
            }
            if (! $schema->hasColumn('tb_kompilasi', 'alasan_pengajuan_tgl')) {
                $schema->table('tb_kompilasi', fn (Blueprint $table) => $table->text('alasan_pengajuan_tgl')->nullable());
            }
            foreach (['tb_tanggapan', 'tb_tindak_lanjut'] as $source) {
                if (! $schema->hasColumn($source, 'jatuh_tempo_saat_pengisian')) {
                    $schema->table($source, function (Blueprint $table): void {
                        $table->date('jatuh_tempo_saat_pengisian')->nullable();
                        $table->timestamp('submitted_late_at')->nullable();
                    });
                }
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
            if ($db->table('tb_surat_tanggal_riwayat')->exists()
                || $db->table('tb_kompilasi')->whereNotNull('alasan_pengajuan_tgl')->exists()
                || $db->table('tb_tanggapan')->whereNotNull('jatuh_tempo_saat_pengisian')->exists()
                || $db->table('tb_tindak_lanjut')->whereNotNull('jatuh_tempo_saat_pengisian')->exists()) {
                throw new RuntimeException('Rollback R5 dibatalkan karena tanggal atau bukti keterlambatan sudah digunakan.');
            }
        }
        foreach (['mysql_snp', 'mysql_djsn'] as $connection) {
            $schema = Schema::connection($connection);
            $schema->dropIfExists('tb_surat_tanggal_riwayat');
            $schema->table('tb_kompilasi', fn (Blueprint $table) => $table->dropColumn('alasan_pengajuan_tgl'));
            foreach (['tb_tanggapan', 'tb_tindak_lanjut'] as $source) {
                $schema->table($source, fn (Blueprint $table) => $table->dropColumn(['jatuh_tempo_saat_pengisian', 'submitted_late_at']));
            }
        }
    }
};
