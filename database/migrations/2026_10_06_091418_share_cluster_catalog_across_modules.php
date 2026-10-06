<?php

use App\Services\SharedClusterCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $catalog = app(SharedClusterCatalog::class);
        $plan = $catalog->plan();
        foreach (SharedClusterCatalog::MODULES as $module) {
            foreach (['tb_cluster', 'tb_sub_cluster'] as $tableName) {
                if (! Schema::connection('mysql_'.$module)->hasColumn($tableName, 'shared_key')) {
                    Schema::connection('mysql_'.$module)->table($tableName, function (Blueprint $table): void {
                        $table->uuid('shared_key')->nullable()->unique();
                    });
                }
            }
        }
        $catalog->synchronize($plan);
    }

    public function down(): void
    {
        foreach (SharedClusterCatalog::MODULES as $module) {
            foreach (['tb_cluster', 'tb_sub_cluster'] as $tableName) {
                Schema::connection('mysql_'.$module)->table($tableName, function (Blueprint $table): void {
                    $table->dropUnique(['shared_key']);
                    $table->dropColumn('shared_key');
                });
            }
        }
    }
};
