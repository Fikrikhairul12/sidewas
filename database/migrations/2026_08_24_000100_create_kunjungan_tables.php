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

        $schema->create('units', function (Blueprint $table) {
            $table->id();
            $table->string('kode_wil', 20)->index();
            $table->string('nama_kapu_kanda')->nullable();
            $table->string('kode_unit', 30)->unique();
            $table->string('nama_unit_kerja');
            $table->string('provinsi')->nullable()->index();
            $table->string('kab_kota')->nullable()->index();
            $table->decimal('latitude', 11, 8)->nullable();
            $table->decimal('longitude', 12, 8)->nullable();
            $table->timestamps();
        });

        $schema->create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_number', 30)->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('position')->nullable();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        $schema->create('visits', function (Blueprint $table) {
            $table->id();
            $table->string('visit_number', 30)->unique();
            $table->string('title', 150);
            $table->foreignId('destination_unit_id')->constrained('units');
            $table->foreignId('pic_employee_id')->constrained('employees');
            $table->unsignedBigInteger('created_by_user_id')->index();
            $table->dateTime('start_at')->index();
            $table->dateTime('end_at')->index();
            $table->text('purpose');
            $table->string('status', 30)->index();
            $table->text('cancelled_reason')->nullable();
            $table->unsignedInteger('approval_round')->default(1);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'start_at']);
        });

        $schema->create('visit_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained('visits')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['visit_id', 'employee_id']);
            $table->index('employee_id');
        });

        $schema->create('visit_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained('visits')->cascadeOnDelete();
            $table->unsignedInteger('round');
            $table->string('decision', 20);
            $table->unsignedBigInteger('acted_by_user_id');
            $table->text('notes')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();
            $table->unique(['visit_id', 'round']);
        });

        $schema->create('visit_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained('visits')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('original_filename');
            $table->string('stored_filename');
            $table->string('file_path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->unsignedBigInteger('uploaded_by_user_id');
            $table->boolean('is_current')->default(true);
            $table->timestamp('uploaded_at');
            $table->timestamps();
            $table->unique('visit_id');
            $table->index(['visit_id', 'is_current']);
        });

        $schema->create('visit_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained('visits')->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['visit_id', 'created_at']);
        });
    }

    public function down(): void
    {
        $schema = Schema::connection($this->connection);
        $schema->dropIfExists('visit_status_logs');
        $schema->dropIfExists('visit_reports');
        $schema->dropIfExists('visit_approvals');
        $schema->dropIfExists('visit_participants');
        $schema->dropIfExists('visits');
        $schema->dropIfExists('employees');
        $schema->dropIfExists('units');
    }
};
