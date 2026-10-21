<?php

use App\Services\SuratAwalStatusService;
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
        foreach (['snp', 'djsn'] as $module) {
            $schema = Schema::connection('mysql_'.$module);
            if (! $schema->hasColumn('tb_record', 'pernah_terbit')) {
                $schema->table('tb_record', fn (Blueprint $table) => $table->boolean('pernah_terbit')->default(false));
            }
            (new SuratAwalStatusService)->synchronizeModule($module);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['mysql_snp', 'mysql_djsn'] as $connection) {
            if (DB::connection($connection)->table('tb_record')->whereIn('status', ['terbit', 'proses', 'belum_ditindaklanjuti'])->exists()) {
                throw new RuntimeException('Rollback status awal dibatalkan karena status baru sudah digunakan. Status dan penanda penerbitan harus dipertahankan.');
            }
        }
        foreach (['mysql_snp', 'mysql_djsn'] as $connection) {
            Schema::connection($connection)->table('tb_record', fn (Blueprint $table) => $table->dropColumn('pernah_terbit'));
        }
    }
};
