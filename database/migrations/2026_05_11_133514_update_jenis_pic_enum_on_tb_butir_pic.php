<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected string $connectionName = 'mysql_snp';

    public function up(): void
    {
        DB::connection($this->connectionName)->statement("
            ALTER TABLE tb_butir_pic
            MODIFY jenis_pic ENUM('utama', 'pendukung', 'komite') NOT NULL
        ");
    }

    public function down(): void
    {
        DB::connection($this->connectionName)->statement("
            ALTER TABLE tb_butir_pic
            MODIFY jenis_pic ENUM('utama', 'pendukung') NOT NULL
        ");
    }
};
