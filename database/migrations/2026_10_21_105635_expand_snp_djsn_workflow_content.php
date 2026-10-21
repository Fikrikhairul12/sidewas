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
        foreach ($this->columns() as [$connection, $tableName, $column, $nullable]) {
            Schema::connection($connection)->table($tableName, function (Blueprint $table) use ($column, $nullable): void {
                $table->longText($column)->nullable($nullable)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->columns() as [$connection, $tableName, $column]) {
            if (DB::connection($connection)->table($tableName)->whereRaw('LENGTH('.$column.') > 65535')->exists()) {
                throw new RuntimeException('Hasil kompilasi atau reviu melebihi kapasitas TEXT; rollback dibatalkan untuk mencegah kehilangan isi.');
            }
        }
        foreach ($this->columns() as [$connection, $tableName, $column, $nullable]) {
            Schema::connection($connection)->table($tableName, function (Blueprint $table) use ($column, $nullable): void {
                $table->text($column)->nullable($nullable)->change();
            });
        }
    }

    /** @return list<array{string, string, string, bool}> */
    private function columns(): array
    {
        return [
            ['mysql_snp', 'tb_kompilasi', 'hasil_kompilasi', true],
            ['mysql_snp', 'tb_review', 'hasil_review', true],
            ['mysql_djsn', 'tb_review', 'hasil_review', true],
        ];
    }
};
