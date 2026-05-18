<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected string $connectionName = 'mysql_snp';

    public function up(): void
    {
        Schema::connection($this->connectionName)->table('tb_tanggapan', function (Blueprint $table) {
            $table->unique('id_butir_snp', 'tb_tanggapan_id_butir_snp_unique');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connectionName)->table('tb_tanggapan', function (Blueprint $table) {
            $table->dropUnique('tb_tanggapan_id_butir_snp_unique');
        });
    }
};