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
        Schema::connection('mysql')->table('tb_delete_requests', function (Blueprint $table) {
            $table->longText('reason')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection('mysql')->table('tb_delete_requests')->whereRaw('LENGTH(reason) > 65535')->exists()) {
            throw new RuntimeException('Pengajuan melebihi kapasitas TEXT; rollback dibatalkan untuk mencegah kehilangan isi.');
        }
        Schema::connection('mysql')->table('tb_delete_requests', function (Blueprint $table) {
            $table->text('reason')->nullable()->change();
        });
    }
};
