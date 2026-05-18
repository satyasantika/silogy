<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hasil_subcpmk', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('subcpmk_id')->constrained('subcpmk')->cascadeOnDelete();
            $table->foreignUuid('kelas_mk_mahasiswa_id')
                ->constrained('kelas_mk_mahasiswa')->cascadeOnDelete();
            $table->foreignUuid('kelas_mk_id')->constrained('kelas_mk')->cascadeOnDelete();
            $table->decimal('nilai_akhir', 5, 2)->nullable();
            $table->timestamps();
            $table->unique(['subcpmk_id', 'kelas_mk_mahasiswa_id'], 'uq_hsub');
        });

        Schema::create('hasil_cpmk', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cpmk_id')->constrained('cpmk')->cascadeOnDelete();
            $table->foreignUuid('kelas_mk_mahasiswa_id')
                ->constrained('kelas_mk_mahasiswa')->cascadeOnDelete();
            $table->foreignUuid('kelas_mk_id')->constrained('kelas_mk')->cascadeOnDelete();
            $table->decimal('nilai_akhir', 5, 2)->nullable();
            $table->timestamps();
            $table->unique(['cpmk_id', 'kelas_mk_mahasiswa_id'], 'uq_hcpmk');
        });

        Schema::create('hasil_cpl_mk', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cpl_id')->constrained('cpl')->cascadeOnDelete();
            $table->foreignUuid('mk_unit_id')->constrained('mk_units')->cascadeOnDelete();
            $table->foreignUuid('kelas_mk_mahasiswa_id')
                ->constrained('kelas_mk_mahasiswa')->cascadeOnDelete();
            $table->foreignUuid('semester_id')->constrained('semesters')->restrictOnDelete();
            $table->decimal('nilai_akhir', 5, 2)->nullable();
            $table->decimal('nilai_berbobot', 5, 2)->nullable();
            $table->timestamps();
            $table->unique(
                ['cpl_id', 'mk_unit_id', 'kelas_mk_mahasiswa_id', 'semester_id'],
                'uq_hcm'
            );
        });

        Schema::create('hasil_cpl_mk_unit', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cpl_id')->constrained('cpl')->cascadeOnDelete();
            $table->foreignUuid('mk_id')->constrained('mk')->cascadeOnDelete();
            $table->foreignUuid('academic_unit_id')
                ->constrained('academic_units')->cascadeOnDelete();
            $table->foreignUuid('semester_id')->constrained('semesters')->restrictOnDelete();
            $table->decimal('rata_rata', 5, 2)->nullable();
            $table->decimal('persentase_tercapai', 5, 2)->nullable();
            $table->unsignedSmallInteger('jumlah_mahasiswa')->nullable();
            $table->timestamps();
            $table->unique(
                ['cpl_id', 'mk_id', 'academic_unit_id', 'semester_id'],
                'uq_hcmu'
            );
            $table->index('academic_unit_id', 'idx_hcmu_unit');
        });

        Schema::create('hasil_cpl_unit', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cpl_id')->constrained('cpl')->cascadeOnDelete();
            $table->foreignUuid('academic_unit_id')
                ->constrained('academic_units')->cascadeOnDelete();
            $table->foreignUuid('semester_id')->constrained('semesters')->restrictOnDelete();
            $table->decimal('rata_rata', 5, 2)->nullable();
            $table->decimal('persentase_tercapai', 5, 2)->nullable();
            $table->unsignedSmallInteger('jumlah_mahasiswa')->nullable();
            $table->timestamps();
            $table->unique(
                ['cpl_id', 'academic_unit_id', 'semester_id'],
                'uq_hcu'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hasil_cpl_unit');
        Schema::dropIfExists('hasil_cpl_mk_unit');
        Schema::dropIfExists('hasil_cpl_mk');
        Schema::dropIfExists('hasil_cpmk');
        Schema::dropIfExists('hasil_subcpmk');
    }
};
