<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kurikulum', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('academic_unit_id')
                ->constrained('academic_units')
                ->cascadeOnDelete();
            $table->string('nama', 150);
            $table->string('kode', 30)->nullable();
            $table->year('tahun');
            $table->unsignedTinyInteger('target_capaian_lulusan')->default(75);
            $table->text('deskripsi')->nullable();
            $table->string('state', 50)->default('draft');
            $table->boolean('is_active')->default(false);
            $table->foreignUuid('dibuat_oleh')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index('academic_unit_id', 'idx_kur_unit');
            $table->index('state', 'idx_kur_state');
        });

        Schema::create('profil_lulusan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('kurikulum_id')
                ->constrained('kurikulum')
                ->cascadeOnDelete();
            $table->string('kode', 10);
            $table->string('nama', 150)->nullable();
            $table->text('deskripsi');
            $table->unsignedTinyInteger('urutan')->nullable();
            $table->timestamps();
        });

        Schema::create('profil_indikators', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('profil_id')
                ->constrained('profil_lulusan')
                ->cascadeOnDelete();
            $table->text('nama')->nullable();
            $table->text('deskripsi')->nullable();
            $table->timestamps();
        });

        Schema::create('state_transitions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('model_type', 100);
            $table->uuid('model_id');
            $table->string('from_state', 50)->nullable();
            $table->string('to_state', 50);
            $table->foreignUuid('actor_id')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('keterangan')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['model_type', 'model_id'], 'idx_st_model');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('state_transitions');
        Schema::dropIfExists('profil_indikators');
        Schema::dropIfExists('profil_lulusan');
        Schema::dropIfExists('kurikulum');
    }
};
