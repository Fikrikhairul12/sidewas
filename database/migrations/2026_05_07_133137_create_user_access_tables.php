<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_user_role_type');
        Schema::dropIfExists('tb_role_type');
        Schema::dropIfExists('tb_type');
        Schema::dropIfExists('tb_role');
    }
};
