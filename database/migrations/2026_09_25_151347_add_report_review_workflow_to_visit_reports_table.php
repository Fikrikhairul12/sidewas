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

        $singleVisitIndexes = collect($schema->getIndexes('visit_reports'))
            ->filter(fn (array $index): bool => ($index['unique'] ?? false)
                && ($index['columns'] ?? []) === ['visit_id']);

        foreach ($singleVisitIndexes as $index) {
            $schema->table('visit_reports', function (Blueprint $table) use ($index): void {
                $table->dropUnique($index['name']);
            });
        }

        $schema->table('visit_reports', function (Blueprint $table): void {
            $table->string('status', 30)->default('PENDING')->after('version')->index();
            $table->unsignedBigInteger('reviewed_by_user_id')->nullable()->after('uploaded_by_user_id')->index();
            $table->text('review_notes')->nullable()->after('reviewed_by_user_id');
            $table->timestamp('reviewed_at')->nullable()->after('uploaded_at');
            $table->unique(['visit_id', 'version'], 'visit_reports_visit_version_unique');
        });

        DB::connection($this->connection)->table('visit_reports')->update([
            'status' => 'APPROVED',
            'reviewed_at' => DB::raw('uploaded_at'),
        ]);
    }

    public function down(): void
    {
        $schema = Schema::connection($this->connection);
        $duplicateVisitId = DB::connection($this->connection)
            ->table('visit_reports')
            ->select('visit_id')
            ->groupBy('visit_id')
            ->havingRaw('COUNT(*) > 1')
            ->value('visit_id');

        if ($duplicateVisitId !== null) {
            throw new RuntimeException(
                'Rollback tidak dapat dilakukan karena kunjungan ID '.$duplicateVisitId.' memiliki beberapa versi laporan.',
            );
        }

        $schema->table('visit_reports', function (Blueprint $table): void {
            $table->dropUnique('visit_reports_visit_version_unique');
            $table->dropColumn(['status', 'reviewed_by_user_id', 'review_notes', 'reviewed_at']);
            $table->unique('visit_id');
        });
    }
};
