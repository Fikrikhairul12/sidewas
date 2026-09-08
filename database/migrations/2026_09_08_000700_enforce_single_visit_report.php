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

        if (! $schema->hasTable('visit_reports')) {
            return;
        }

        $hasSingleVisitUniqueIndex = collect($schema->getIndexes('visit_reports'))
            ->contains(fn (array $index): bool => ($index['unique'] ?? false)
                && ($index['columns'] ?? []) === ['visit_id']);

        if ($hasSingleVisitUniqueIndex) {
            return;
        }

        $duplicateVisitId = DB::connection($this->connection)
            ->table('visit_reports')
            ->select('visit_id')
            ->groupBy('visit_id')
            ->havingRaw('COUNT(*) > 1')
            ->value('visit_id');

        if ($duplicateVisitId !== null) {
            throw new RuntimeException(
                'Terdapat lebih dari satu laporan pada kunjungan ID '.$duplicateVisitId
                .'. Tentukan satu laporan yang dipertahankan sebelum menjalankan migrasi kembali.',
            );
        }

        $schema->table('visit_reports', function (Blueprint $table): void {
            $table->unique('visit_id', 'visit_reports_single_visit_unique');
        });
    }

    public function down(): void
    {
        $schema = Schema::connection($this->connection);

        if (! $schema->hasTable('visit_reports')) {
            return;
        }

        $hasManagedIndex = collect($schema->getIndexes('visit_reports'))
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === 'visit_reports_single_visit_unique');

        if ($hasManagedIndex) {
            $schema->table('visit_reports', function (Blueprint $table): void {
                $table->dropUnique('visit_reports_single_visit_unique');
            });
        }
    }
};
