<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected string $connectionName = 'mysql';

    public function up(): void
    {
        Schema::connection($this->connectionName)->create('tb_delete_requests', function (Blueprint $table) {
            $table->id();

            $table->string('type_code', 50)->nullable();
            $table->string('database_name')->nullable();
            $table->string('table_name')->nullable();
            $table->string('record_key')->nullable();
            $table->string('record_label')->nullable();

            $table->text('reason')->nullable();

            $table->foreignId('requested_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('verified_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('rejected_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->enum('status', [
                'pending_admin_verification',
                'pending_super_admin_approval',
                'approved',
                'rejected',
                'cancelled',
            ])->default('pending_super_admin_approval');

            $table->timestamp('requested_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();

            $table->timestamps();

            $table->index(['type_code', 'table_name', 'record_key']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connectionName)->dropIfExists('tb_delete_requests');
    }
};
