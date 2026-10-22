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
            if (! $schema->hasColumn('tb_surat_putaran', 'penugasan_unit')) {
                $schema->table('tb_surat_putaran', function (Blueprint $table): void {
                    $table->json('penugasan_unit')->nullable();
                    $table->timestamp('penugasan_dicatat_at')->nullable();
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
            if (DB::connection($connection)->table('tb_surat_putaran')->whereNotNull('penugasan_unit')->exists()) {
                throw new RuntimeException('Rollback dibatalkan karena penugasan unit sudah menjadi bagian riwayat putaran.');
            }
        }
        foreach (['mysql_snp', 'mysql_djsn'] as $connection) {
            Schema::connection($connection)->table('tb_surat_putaran', fn (Blueprint $table) => $table->dropColumn(['penugasan_unit', 'penugasan_dicatat_at']));
        }
    }
};
