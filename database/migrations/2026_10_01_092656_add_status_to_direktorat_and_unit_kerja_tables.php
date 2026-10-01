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
        Schema::table('tb_direktorat', function (Blueprint $table) {
            $table->enum('status', ['active', 'inactive'])->default('active')->after('keterangan');
            $table->boolean('managed_from_ui')->default(false)->after('status');
        });

        Schema::table('tb_unit_kerja', function (Blueprint $table) {
            $table->enum('status', ['active', 'inactive'])->default('active')->after('keterangan');
            $table->boolean('managed_from_ui')->default(false)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tb_unit_kerja', function (Blueprint $table) {
            $table->dropColumn(['status', 'managed_from_ui']);
        });

        Schema::table('tb_direktorat', function (Blueprint $table) {
            $table->dropColumn(['status', 'managed_from_ui']);
        });
    }
};
