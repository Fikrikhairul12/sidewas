<?php

use App\Enums\ReviuStatus;
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
            $statuses = array_values(array_unique([...$this->legacyStatuses($module), ...array_keys(ReviuStatus::options())]));
            Schema::connection('mysql_'.$module)->table('tb_review', function (Blueprint $table) use ($statuses): void {
                $table->enum('status', $statuses)->default('belum_ditanggapi')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['snp', 'djsn'] as $module) {
            if (DB::connection('mysql_'.$module)->table('tb_review')->whereNotIn('status', $this->legacyStatuses($module))->exists()) {
                throw new RuntimeException('Rollback dibatalkan karena pilihan reviu baru sudah digunakan. Keputusan reviu harus dipertahankan.');
            }
        }
        foreach (['snp', 'djsn'] as $module) {
            $statuses = $this->legacyStatuses($module);
            Schema::connection('mysql_'.$module)->table('tb_review', function (Blueprint $table) use ($statuses): void {
                $table->enum('status', $statuses)->default('belum_ditanggapi')->change();
            });
        }
    }

    /** @return list<string> */
    private function legacyStatuses(string $module): array
    {
        return ['belum_ditanggapi', $module === 'snp' ? 'dalam_proses_reviu_dewas' : 'dalam_proses_reviu_dewan_pengawas', 'dalam_proses_tindak_lanjut_direksi', 'selesai_tuntas'];
    }
};
