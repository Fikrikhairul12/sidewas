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
            if (! $schema->hasColumn('tb_kompilasi', 'salinan_konteks')) {
                $schema->table('tb_kompilasi', fn (Blueprint $table) => $table->json('salinan_konteks')->nullable());
            }
            if (! $schema->hasColumn('tb_kompilasi', 'kunci_salinan')) {
                $schema->table('tb_kompilasi', fn (Blueprint $table) => $table->string('kunci_salinan', 64)->nullable());
            }
            if (! $schema->hasIndex('tb_kompilasi', 'kompilasi_salinan_tahap_butir_unique')) {
                $schema->table('tb_kompilasi', fn (Blueprint $table) => $table->unique('kunci_salinan', 'kompilasi_salinan_tahap_butir_unique'));
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['mysql_snp', 'mysql_djsn'] as $connection) {
            if (DB::connection($connection)->table('tb_kompilasi')->whereNotNull('salinan_konteks')->orWhereNotNull('kunci_salinan')->exists()) {
                throw new RuntimeException('Rollback R3 dibatalkan karena salinan kompilasi resmi sudah tersimpan.');
            }
        }
        foreach (['mysql_snp', 'mysql_djsn'] as $connection) {
            Schema::connection($connection)->table('tb_kompilasi', function (Blueprint $table): void {
                $table->dropUnique('kompilasi_salinan_tahap_butir_unique');
                $table->dropColumn(['salinan_konteks', 'kunci_salinan']);
            });
        }
    }
};
