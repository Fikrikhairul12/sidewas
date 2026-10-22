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
            if (! $schema->hasColumn('tb_record', 'closed_at')) {
                $schema->table('tb_record', function (Blueprint $table): void {
                    $table->timestamp('closed_at')->nullable();
                    $table->unsignedBigInteger('closed_by')->nullable();
                    $table->foreignId('closed_review_id')->nullable()->constrained('tb_review')->restrictOnDelete();
                    $table->foreignId('closed_putaran_id')->nullable()->constrained('tb_surat_putaran')->restrictOnDelete();
                });
            }
            if (! $schema->hasColumn('tb_tanggapan', 'submitted_after_close_at')) {
                $schema->table('tb_tanggapan', function (Blueprint $table): void {
                    $table->timestamp('submitted_after_close_at')->nullable();
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
            if (DB::connection($connection)->table('tb_record')->whereNotNull('closed_at')
                ->orWhereNotNull('closed_by')->orWhereNotNull('closed_review_id')->orWhereNotNull('closed_putaran_id')->exists()
                || DB::connection($connection)->table('tb_tanggapan')->whereNotNull('submitted_after_close_at')->exists()) {
                throw new RuntimeException('Rollback dibatalkan karena riwayat penutupan atau tanggapan setelah penutupan sudah digunakan.');
            }
        }
        foreach (['mysql_snp', 'mysql_djsn'] as $connection) {
            Schema::connection($connection)->table('tb_record', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('closed_review_id');
                $table->dropConstrainedForeignId('closed_putaran_id');
                $table->dropColumn(['closed_at', 'closed_by']);
            });
            Schema::connection($connection)->table('tb_tanggapan', fn (Blueprint $table) => $table->dropColumn('submitted_after_close_at'));
        }
    }
};
