<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['snp', 'ragab', 'rawas', 'djsn', 'eksternal'] as $module) {
            foreach (['tb_cluster', 'tb_sub_cluster'] as $tableName) {
                Schema::connection('mysql_'.$module)->table($tableName, function (Blueprint $table): void {
                    $table->enum('status', ['active', 'inactive'])->default('active');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['snp', 'ragab', 'rawas', 'djsn', 'eksternal'] as $module) {
            foreach (['tb_cluster', 'tb_sub_cluster'] as $tableName) {
                Schema::connection('mysql_'.$module)->table($tableName, function (Blueprint $table): void {
                    $table->dropColumn('status');
                });
            }
        }
    }
};
