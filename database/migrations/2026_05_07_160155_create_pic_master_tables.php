<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_user_komite');
        Schema::dropIfExists('tb_user_unit_kerja');
        Schema::dropIfExists('tb_komite');
        Schema::dropIfExists('tb_unit_kerja');
        Schema::dropIfExists('tb_direktorat');
    }
};
