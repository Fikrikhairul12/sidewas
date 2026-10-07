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
        foreach ($this->columns() as $module => [$tableName, $column, $nullable]) {
            Schema::connection('mysql_'.$module)->table($tableName, function (Blueprint $table) use ($column, $nullable): void {
                $table->longText($column)->nullable($nullable)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->columns() as $module => [$tableName, $column]) {
            if (DB::connection('mysql_'.$module)->table($tableName)->whereRaw('LENGTH('.$column.') > 65535')->exists()) {
                throw new RuntimeException('Isi butir melebihi kapasitas TEXT; rollback dibatalkan untuk mencegah kehilangan isi.');
            }
        }
        foreach ($this->columns() as $module => [$tableName, $column, $nullable]) {
            Schema::connection('mysql_'.$module)->table($tableName, function (Blueprint $table) use ($column, $nullable): void {
                $table->text($column)->nullable($nullable)->change();
            });
        }
    }

    /** @return array<string, array{string, string, bool}> */
    private function columns(): array
    {
        return [
            'ragab' => ['tb_butir_ragab', 'keputusan_ragab', true],
            'rawas' => ['tb_butir_rawas', 'keputusan_rawas', true],
            'djsn' => ['tb_butir_djsn', 'butir_djsn', false],
            'eksternal' => ['tb_butir_eksternal', 'keputusan_eksternal', true],
        ];
    }
};
