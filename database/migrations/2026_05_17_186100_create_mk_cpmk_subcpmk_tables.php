<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mk_cpmk', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cpl_mk_id')
                ->constrained('cpl_mk')
                ->cascadeOnDelete();
            $table->foreignUuid('cpmk_id')
                ->constrained('cpmk')
                ->cascadeOnDelete();
            $table->decimal('bobot', 5, 2);
            $table->timestamps();

            $table->unique(['cpl_mk_id', 'cpmk_id'], 'uq_mk_cpmk');
        });

        Schema::create('subcpmk', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('mk_cpmk_id')
                ->constrained('mk_cpmk')
                ->cascadeOnDelete();
            $table->foreignUuid('semester_id')
                ->nullable()
                ->constrained('semesters')
                ->nullOnDelete();
            $table->string('kode', 15);
            $table->text('deskripsi');
            $table->text('indikator')->nullable();
            $table->text('evaluasi')->nullable();
            $table->double('bobot')->nullable();
            $table->enum('bloom_kognitif', ['C1', 'C2', 'C3', 'C4', 'C5', 'C6'])->nullable();
            $table->enum('bloom_afektif', ['A1', 'A2', 'A3', 'A4', 'A5'])->nullable();
            $table->enum('bloom_psikomotorik', ['P1', 'P2', 'P3', 'P4', 'P5', 'P6', 'P7'])->nullable();
            $table->timestamps();

            $table->index('mk_cpmk_id', 'idx_subcpmk_mkcpmk');
            $table->index('semester_id', 'idx_subcpmk_semester');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subcpmk');
        Schema::dropIfExists('mk_cpmk');
    }
};
