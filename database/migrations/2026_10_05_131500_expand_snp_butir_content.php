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
        Schema::connection('mysql_snp')->table('tb_butir_snp', function (Blueprint $table) {
            $table->longText('butir_snp')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection('mysql_snp')->table('tb_butir_snp')->whereRaw('LENGTH(butir_snp) > 65535')->exists()) {
            throw new RuntimeException('Butir SNP melebihi kapasitas TEXT; rollback dibatalkan untuk mencegah kehilangan isi.');
        }
        Schema::connection('mysql_snp')->table('tb_butir_snp', function (Blueprint $table) {
            $table->text('butir_snp')->change();
        });
    }
};
