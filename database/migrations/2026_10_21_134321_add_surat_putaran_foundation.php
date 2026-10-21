<?php

use App\Services\SuratPutaranBackfill;
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
        $backfill = new SuratPutaranBackfill;

        foreach (['mysql_snp', 'mysql_djsn'] as $connection) {
            $backfill->assertSourceRelations($connection);
        }

        foreach (['mysql_snp' => 'snp', 'mysql_djsn' => 'djsn'] as $connection => $module) {
            $schema = Schema::connection($connection);

            if (! $schema->hasTable('tb_surat_putaran')) {
                $schema->create('tb_surat_putaran', function (Blueprint $table): void {
                    $table->id();
                    $table->foreignId('record_id')->nullable()->constrained('tb_record')->nullOnDelete();
                    $table->unsignedInteger('nomor_putaran');
                    $table->string('nomor_surat_lama')->nullable();
                    $table->string('status_lama', 50)->nullable();
                    $table->timestamp('opened_at')->nullable();
                    $table->timestamp('archived_at')->nullable();
                    $table->unsignedBigInteger('created_by')->nullable();
                    $table->unsignedBigInteger('archived_by')->nullable();
                    $table->timestamps();
                    $table->unique(['record_id', 'nomor_putaran'], 'surat_nomor_putaran_unique');
                });
            }

            if (! $schema->hasColumn('tb_record', 'putaran_aktif_id')) {
                $schema->table('tb_record', function (Blueprint $table): void {
                    $table->foreignId('putaran_aktif_id')->nullable()->constrained('tb_surat_putaran')->nullOnDelete();
                });
            }

            if ($module === 'djsn' && ! $schema->hasTable('tb_kompilasi')) {
                $schema->create('tb_kompilasi', function (Blueprint $table): void {
                    $table->id();
                    $table->string('id_butir_djsn', 90);
                    $table->unsignedInteger('putaran_tl')->default(1);
                    $table->string('tahap_kompilasi', 30)->default('tanggapan');
                    $table->longText('hasil_kompilasi')->nullable();
                    $table->text('deliverables')->nullable();
                    $table->text('dokumen')->nullable();
                    $table->date('ubah_tgl')->nullable();
                    $table->string('status_pengajuan_tgl', 30)->nullable();
                    $table->string('status', 50)->default('belum_dikompilasi');
                    $table->unsignedBigInteger('created_by')->nullable();
                    $table->unsignedBigInteger('updated_by')->nullable();
                    $table->timestamps();
                    $table->foreign('id_butir_djsn')->references('id_butir_djsn')->on('tb_butir_djsn')
                        ->cascadeOnUpdate()->cascadeOnDelete();
                });
            }

            foreach (['tb_tanggapan', 'tb_kompilasi', 'tb_review', 'tb_tindak_lanjut'] as $tableName) {
                if (! $schema->hasColumn($tableName, 'putaran_id')) {
                    $schema->table($tableName, function (Blueprint $table) use ($module): void {
                        $table->foreignId('putaran_id')->nullable()->constrained('tb_surat_putaran')->restrictOnDelete();
                        $table->index(['putaran_id', 'id_butir_'.$module], $table->getTable().'_putaran_butir_index');
                    });
                }
            }

            if (! $schema->hasColumn('tb_tanggapan', 'unit_kerja_id')) {
                $schema->table('tb_tanggapan', fn (Blueprint $table) => $table->unsignedBigInteger('unit_kerja_id')->nullable()->index());
            }

            if ($module === 'djsn' && ! $schema->hasColumn('tb_tanggapan', 'butir_pic_id')) {
                $schema->table('tb_tanggapan', fn (Blueprint $table) => $table->unsignedBigInteger('butir_pic_id')->nullable()->index());
            }

            if (! $schema->hasColumn('tb_review', 'kompilasi_id')) {
                $schema->table('tb_review', function (Blueprint $table): void {
                    $table->foreignId('kompilasi_id')->nullable()->constrained('tb_kompilasi')->nullOnDelete();
                });
            }

            $backfill->run($connection);

            if ($module === 'snp') {
                if (! $schema->hasIndex('tb_tanggapan', 'tanggapan_pic_putaran_unique')) {
                    $schema->table('tb_tanggapan', fn (Blueprint $table) => $table->unique(
                        ['id_butir_snp', 'butir_pic_id', 'putaran_id'], 'tanggapan_pic_putaran_unique',
                    ));
                }
                if ($schema->hasIndex('tb_tanggapan', 'tb_tanggapan_butir_pic_unique')) {
                    $schema->table('tb_tanggapan', fn (Blueprint $table) => $table->dropUnique('tb_tanggapan_butir_pic_unique'));
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
            if (DB::connection($connection)->table('tb_surat_putaran')->where('nomor_putaran', '>', 0)->exists()
                || ($connection === 'mysql_djsn' && DB::connection($connection)->table('tb_kompilasi')->exists())) {
                throw new RuntimeException('Rollback dibatalkan karena sudah ada data putaran baru atau kompilasi DJSN. Riwayat harus dipertahankan.');
            }
        }

        foreach (['mysql_snp', 'mysql_djsn'] as $connection) {
            $schema = Schema::connection($connection);

            if ($connection === 'mysql_snp') {
                $schema->table('tb_tanggapan', function (Blueprint $table): void {
                    $table->unique(['id_butir_snp', 'butir_pic_id'], 'tb_tanggapan_butir_pic_unique');
                    $table->dropUnique('tanggapan_pic_putaran_unique');
                });
            }

            $schema->table('tb_review', fn (Blueprint $table) => $table->dropConstrainedForeignId('kompilasi_id'));
            $schema->table('tb_record', fn (Blueprint $table) => $table->dropConstrainedForeignId('putaran_aktif_id'));

            foreach (['tb_tanggapan', 'tb_kompilasi', 'tb_review', 'tb_tindak_lanjut'] as $tableName) {
                $schema->table($tableName, function (Blueprint $table) use ($tableName): void {
                    $table->dropForeign(['putaran_id']);
                    $table->dropIndex($tableName.'_putaran_butir_index');
                    $table->dropColumn('putaran_id');
                });
            }

            $schema->table('tb_tanggapan', function (Blueprint $table) use ($connection): void {
                $table->dropIndex(['unit_kerja_id']);
                $table->dropColumn('unit_kerja_id');

                if ($connection === 'mysql_djsn') {
                    $table->dropIndex(['butir_pic_id']);
                    $table->dropColumn('butir_pic_id');
                }
            });

            if ($connection === 'mysql_djsn') {
                $schema->drop('tb_kompilasi');
            }

            $schema->drop('tb_surat_putaran');
        }
    }
};
