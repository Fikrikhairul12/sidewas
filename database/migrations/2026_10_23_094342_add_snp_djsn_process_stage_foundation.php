<?php

use App\Services\SuratTahapBackfill;
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
        $backfill = new SuratTahapBackfill;
        foreach (['mysql_snp', 'mysql_djsn'] as $connection) {
            $backfill->assertSourceRelations($connection);
        }

        foreach (['mysql_snp', 'mysql_djsn'] as $connection) {
            $schema = Schema::connection($connection);
            if (! $schema->hasTable('tb_surat_tahap')) {
                $schema->create('tb_surat_tahap', function (Blueprint $table): void {
                    $table->id();
                    $table->foreignId('record_id')->nullable()->constrained('tb_record')->nullOnDelete();
                    $table->foreignId('putaran_asal_id')->nullable()->constrained('tb_surat_putaran')->restrictOnDelete();
                    $table->string('kunci_asal', 100);
                    $table->string('tahap_proses', 30);
                    $table->unsignedInteger('nomor_putaran')->nullable();
                    $table->string('asal_pemetaan', 30)->default('aplikasi');
                    $table->string('status_pemetaan', 30)->default('terpetakan');
                    $table->json('bukti_pemetaan')->nullable();
                    $table->json('penugasan_unit')->nullable();
                    $table->timestamp('mulai_at')->nullable();
                    $table->date('jatuh_tempo')->nullable();
                    $table->json('bukti_tanggal')->nullable();
                    $table->timestamp('tanggal_ditetapkan_at')->nullable();
                    $table->unsignedBigInteger('tanggal_ditetapkan_by')->nullable();
                    $table->timestamp('archived_at')->nullable();
                    $table->unsignedBigInteger('created_by')->nullable();
                    $table->timestamps();
                    $table->unique(['record_id', 'kunci_asal'], 'surat_tahap_asal_unique');
                    $table->unique(['record_id', 'tahap_proses', 'nomor_putaran'], 'surat_tahap_nomor_unique');
                });
            }
            if (! $schema->hasColumn('tb_record', 'tahap_aktif_id')) {
                $schema->table('tb_record', fn (Blueprint $table) => $table->foreignId('tahap_aktif_id')->nullable()
                    ->constrained('tb_surat_tahap')->restrictOnDelete());
            }
            foreach (['tb_tanggapan', 'tb_tindak_lanjut', 'tb_kompilasi', 'tb_review'] as $source) {
                if (! $schema->hasColumn($source, 'tahap_id')) {
                    $schema->table($source, fn (Blueprint $table) => $table->foreignId('tahap_id')->nullable()
                        ->constrained('tb_surat_tahap')->restrictOnDelete());
                }
            }
            if (! $schema->hasColumn('tb_kompilasi', 'status_bahan')) {
                $schema->table('tb_kompilasi', function (Blueprint $table): void {
                    $table->string('status_bahan', 30)->default('perlu_rekonsiliasi');
                    $table->timestamp('bahan_dicatat_at')->nullable();
                });
            }
            if (! $schema->hasTable('tb_kompilasi_bahan')) {
                $schema->create('tb_kompilasi_bahan', function (Blueprint $table): void {
                    $table->id();
                    $table->foreignId('kompilasi_id')->constrained('tb_kompilasi')->restrictOnDelete();
                    $table->foreignId('tahap_id')->constrained('tb_surat_tahap')->restrictOnDelete();
                    $table->string('jenis_sumber', 30);
                    $table->unsignedBigInteger('sumber_id');
                    $table->string('butir_key', 90);
                    $table->unsignedBigInteger('unit_kerja_id')->nullable();
                    $table->unsignedBigInteger('pengisi_id')->nullable();
                    $table->timestamp('sumber_created_at')->nullable();
                    $table->timestamp('sumber_updated_at')->nullable();
                    $table->json('salinan');
                    $table->json('bukti');
                    $table->timestamps();
                    $table->unique(['kompilasi_id', 'jenis_sumber', 'sumber_id'], 'kompilasi_bahan_sumber_unique');
                });
            }
            if (! $schema->hasTable('tb_surat_rekonsiliasi')) {
                $schema->create('tb_surat_rekonsiliasi', function (Blueprint $table): void {
                    $table->id();
                    $table->foreignId('record_id')->nullable()->constrained('tb_record')->nullOnDelete();
                    $table->foreignId('tahap_id')->nullable()->constrained('tb_surat_tahap')->restrictOnDelete();
                    $table->string('tabel_sumber', 40);
                    $table->unsignedBigInteger('sumber_id');
                    $table->string('kode', 60);
                    $table->text('alasan');
                    $table->json('bukti')->nullable();
                    $table->string('status', 30)->default('terbuka');
                    $table->unsignedBigInteger('diselesaikan_by')->nullable();
                    $table->timestamp('diselesaikan_at')->nullable();
                    $table->text('catatan_penyelesaian')->nullable();
                    $table->timestamps();
                    $table->unique(['tabel_sumber', 'sumber_id', 'kode'], 'surat_rekonsiliasi_sumber_unique');
                    $table->index(['record_id', 'status']);
                });
            }
            $backfill->run($connection);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['mysql_snp', 'mysql_djsn'] as $connection) {
            $db = DB::connection($connection);
            if ($db->table('tb_kompilasi_bahan')->exists()
                || $db->table('tb_kompilasi')->whereNotNull('bahan_dicatat_at')->exists()
                || $db->table('tb_kompilasi')->where('status_bahan', '!=', 'perlu_rekonsiliasi')->exists()
                || $db->table('tb_record')->whereNotNull('tahap_aktif_id')->exists()
                || $db->table('tb_surat_tahap')->where('asal_pemetaan', '!=', 'historis')->exists()
                || $db->table('tb_surat_tahap')->whereNotNull('jatuh_tempo')->exists()
                || $db->table('tb_surat_tahap')->whereNotNull('tanggal_ditetapkan_at')->exists()
                || $db->table('tb_surat_rekonsiliasi')->where('status', '!=', 'terbuka')->exists()) {
                throw new RuntimeException('Rollback R1 dibatalkan karena fondasi tahap sudah digunakan atau direkonsiliasi. Riwayat harus dipertahankan.');
            }
        }
        foreach (['mysql_snp', 'mysql_djsn'] as $connection) {
            $schema = Schema::connection($connection);
            $schema->drop('tb_kompilasi_bahan');
            $schema->drop('tb_surat_rekonsiliasi');
            $schema->table('tb_record', fn (Blueprint $table) => $table->dropConstrainedForeignId('tahap_aktif_id'));
            foreach (['tb_review', 'tb_kompilasi', 'tb_tindak_lanjut', 'tb_tanggapan'] as $source) {
                $schema->table($source, fn (Blueprint $table) => $table->dropConstrainedForeignId('tahap_id'));
            }
            $schema->table('tb_kompilasi', fn (Blueprint $table) => $table->dropColumn(['status_bahan', 'bahan_dicatat_at']));
            $schema->drop('tb_surat_tahap');
        }
    }
};
