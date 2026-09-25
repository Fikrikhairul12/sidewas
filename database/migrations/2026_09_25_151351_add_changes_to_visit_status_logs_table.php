<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_kunjungan';

    public function up(): void
    {
        Schema::connection($this->connection)->table('visit_status_logs', function (Blueprint $table): void {
            $table->json('changes')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('visit_status_logs', function (Blueprint $table): void {
            $table->dropColumn('changes');
        });
    }
};
