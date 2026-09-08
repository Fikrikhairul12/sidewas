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

        $schema->table('visits', function (Blueprint $table) {
            $table->string('letter_number', 100)->nullable()->after('title')->index();
        });

        $schema->create('visit_destinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained('visits')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['visit_id', 'unit_id']);
            $table->index('unit_id');
        });

        $connection = DB::connection($this->connection);
        $now = now();

        $connection->table('visits')
            ->select(['id', 'destination_unit_id'])
            ->orderBy('id')
            ->chunkById(500, function ($visits) use ($connection, $now) {
                $connection->table('visit_destinations')->insert(
                    $visits->map(fn ($visit) => [
                        'visit_id' => $visit->id,
                        'unit_id' => $visit->destination_unit_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all(),
                );
            });
    }

    public function down(): void
    {
        $schema = Schema::connection($this->connection);
        $schema->dropIfExists('visit_destinations');
        $schema->table('visits', fn (Blueprint $table) => $table->dropColumn('letter_number'));
    }
};
