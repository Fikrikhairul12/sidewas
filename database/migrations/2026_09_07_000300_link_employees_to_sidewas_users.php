<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_kunjungan';

    public function up(): void
    {
        $schema = Schema::connection($this->connection);

        if (! $schema->hasColumn('employees', 'sidewas_user_id')) {
            $schema->table('employees', function (Blueprint $table) {
                $table->unsignedBigInteger('sidewas_user_id')->nullable()->unique()->after('id');
                $table->string('organizational_unit')->nullable()->after('unit_id');
                $table->string('directorate')->nullable()->after('organizational_unit');
                $table->json('sidewas_roles')->nullable()->after('directorate');
            });
        }

        $coreConnection = (string) env('DB_CONNECTION', 'mysql');
        $usersByEmail = DB::connection($coreConnection)->table('users')->get(['id', 'email'])->keyBy('email');

        DB::connection($this->connection)->table('employees')
            ->orderBy('id')
            ->each(function (object $employee) use ($usersByEmail): void {
                $user = $usersByEmail->get($employee->email);

                if ($user) {
                    DB::connection($this->connection)->table('employees')
                        ->where('id', $employee->id)
                        ->update(['sidewas_user_id' => $user->id]);
                }
            });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('employees', function (Blueprint $table) {
            $table->dropUnique(['sidewas_user_id']);
            $table->dropColumn(['sidewas_user_id', 'organizational_unit', 'directorate', 'sidewas_roles']);
        });
    }
};
