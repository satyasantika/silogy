<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cpl', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('academic_unit_id')
                ->constrained('academic_units')
                ->cascadeOnDelete();
            $table->string('kode', 15);
            $table->text('deskripsi');
            $table->enum('domain', ['kognitif', 'afektif', 'psikomotorik', 'gabungan'])->nullable();
            $table->timestamps();

            $table->index('academic_unit_id', 'idx_cpl_unit');
        });

        Schema::create('bok', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('academic_unit_id')
                ->constrained('academic_units')
                ->cascadeOnDelete();
            $table->string('kode', 15);
            $table->string('nama', 150);
            $table->text('deskripsi')->nullable();
            $table->timestamps();

            $table->index('academic_unit_id', 'idx_bok_unit');
        });

        Schema::create('mk', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('academic_unit_id')
                ->constrained('academic_units')
                ->cascadeOnDelete();
            $table->string('state', 50)->default('draft');
            $table->string('nama', 150);
            $table->unsignedTinyInteger('sks');
            $table->unsignedTinyInteger('sks_teori')->default(0);
            $table->unsignedTinyInteger('sks_praktik')->default(0);
            $table->unsignedTinyInteger('sks_lapangan')->default(0);
            $table->enum('jenis', ['wajib', 'pilihan', 'praktikum']);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('academic_unit_id', 'idx_mk_unit');
            $table->index('is_active', 'idx_mk_active');
        });

        Schema::create('mk_units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('academic_unit_id')
                ->constrained('academic_units')
                ->cascadeOnDelete();
            $table->foreignUuid('mk_id')
                ->constrained('mk')
                ->cascadeOnDelete();
            $table->string('kode', 20);
            $table->unsignedTinyInteger('semester_ke')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['academic_unit_id', 'mk_id'], 'uq_mu_unit_mk');
            $table->unique(['academic_unit_id', 'kode'], 'uq_mu_unit_kode');
            $table->index('semester_ke', 'idx_mu_semester');
            $table->index('is_active', 'idx_mu_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mk_units');
        Schema::dropIfExists('mk');
        Schema::dropIfExists('bok');
        Schema::dropIfExists('cpl');
    }
};
