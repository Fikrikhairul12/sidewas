<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_kunjungan';

    public function up(): void
    {
        $schema = Schema::connection($this->connection);

        $schema->table('visits', function (Blueprint $table) {
            $table->dropForeign(['pic_employee_id']);
        });

        $schema->table('visits', function (Blueprint $table) {
            // Nullable menjaga data lama tetap valid; pengajuan baru tetap diwajibkan lewat FormRequest.
            $table->unsignedBigInteger('pic_unit_kerja_id')->nullable()->after('destination_unit_id')->index();
            $table->dropColumn('pic_employee_id');
        });
    }

    public function down(): void
    {
        $schema = Schema::connection($this->connection);

        $schema->table('visits', function (Blueprint $table) {
            $table->foreignId('pic_employee_id')->nullable()->after('destination_unit_id')->constrained('employees');
            $table->dropIndex(['pic_unit_kerja_id']);
            $table->dropColumn('pic_unit_kerja_id');
        });
    }
};
