<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Main database custom tables + users custom fields.
     *
     * Laravel default migrations users/cache/jobs tetap dipisah.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'google_id')) {
                $table->string('google_id')->nullable()->unique()->after('id');
            }

            if (! Schema::hasColumn('users', 'avatar')) {
                $table->string('avatar')->nullable()->after('email');
            }

            if (! Schema::hasColumn('users', 'provider')) {
                $table->string('provider')->nullable()->after('avatar');
            }

            if (! Schema::hasColumn('users', 'status')) {
                $table->enum('status', ['pending', 'active', 'blocked'])
                    ->default('pending')
                    ->after('provider');
            }
        });

        Schema::create('tb_role', function (Blueprint $table) {
            $table->id();

            $table->string('name')->unique();
            $table->string('display_name');
            $table->integer('level')->default(0);
            $table->boolean('is_universal')->default(false);
            $table->text('keterangan')->nullable();

            $table->timestamps();
        });

        Schema::create('tb_type', function (Blueprint $table) {
            $table->id();

            $table->string('code')->unique();
            $table->string('name');
            $table->string('database_connection')->nullable();
            $table->string('database_name')->nullable();
            $table->text('keterangan')->nullable();

            $table->timestamps();
        });

        Schema::create('tb_role_type', function (Blueprint $table) {
            $table->id();

            $table->foreignId('role_id')
                ->constrained('tb_role')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('type_id')
                ->nullable()
                ->constrained('tb_type')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('name')->unique();
            $table->text('keterangan')->nullable();

            $table->timestamps();

            $table->unique(['role_id', 'type_id'], 'tb_role_type_role_type_unique');
        });

        Schema::create('tb_user_role_type', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('role_type_id')
                ->constrained('tb_role_type')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->enum('status', ['active', 'inactive'])->default('active');

            $table->timestamps();

            $table->unique(['user_id', 'role_type_id'], 'tb_user_role_type_unique');
        });

        Schema::create('tb_direktorat', function (Blueprint $table) {
            $table->id();
            $table->string('nama_direktorat');
            $table->string('kode_direktorat', 50)->nullable()->unique();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('tb_unit_kerja', function (Blueprint $table) {
            $table->id();

            $table->foreignId('direktorat_id')
                ->nullable()
                ->constrained('tb_direktorat')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('nama_unit');
            $table->string('kode_unit', 100)->nullable()->unique();
            $table->text('keterangan')->nullable();

            $table->timestamps();
        });

        Schema::create('tb_komite', function (Blueprint $table) {
            $table->id();
            $table->string('nama_komite');
            $table->string('kode_komite', 100)->nullable()->unique();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('tb_user_unit_kerja', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('unit_kerja_id')
                ->constrained('tb_unit_kerja')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->enum('status', ['active', 'inactive'])->default('active');

            $table->timestamps();

            $table->unique(['user_id', 'unit_kerja_id'], 'tb_user_unit_kerja_unique');
        });

        Schema::create('tb_user_komite', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('komite_id')
                ->constrained('tb_komite')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->enum('status', ['active', 'inactive'])->default('active');

            $table->timestamps();

            $table->unique(['user_id', 'komite_id'], 'tb_user_komite_unique');
        });

        Schema::create('tb_log_activity', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('type_code', 50)->nullable();
            $table->string('database_name')->nullable();
            $table->string('table_name')->nullable();
            $table->string('record_key')->nullable();
            $table->string('action', 100);
            $table->text('description')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 100)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();
        });

        Schema::create('tb_delete_requests', function (Blueprint $table) {
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
        Schema::dropIfExists('tb_delete_requests');
        Schema::dropIfExists('tb_log_activity');

        Schema::dropIfExists('tb_user_komite');
        Schema::dropIfExists('tb_user_unit_kerja');
        Schema::dropIfExists('tb_komite');
        Schema::dropIfExists('tb_unit_kerja');
        Schema::dropIfExists('tb_direktorat');

        Schema::dropIfExists('tb_user_role_type');
        Schema::dropIfExists('tb_role_type');
        Schema::dropIfExists('tb_type');
        Schema::dropIfExists('tb_role');

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'google_id')) {
                $table->dropUnique(['google_id']);
            }

            $columns = [];

            foreach (['google_id', 'avatar', 'provider', 'status'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $columns[] = $column;
                }
            }

            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
