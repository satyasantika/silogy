<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Konsekuensi adaptasi MK lintas unit: CPL/BoK milik universitas/fakultas
 * yang terhubung ke MK yang diadaptasi prodi otomatis terlihat di menu
 * CPL/BoK prodi, tapi hanya kode-nya yang boleh diseragamkan per prodi —
 * bukan mengubah kode asli milik unit pemilik. Tabel ini menyimpan
 * override tsb, dibuat lazily hanya saat prodi benar-benar mengubahnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cpl_kode_overrides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('academic_unit_id')->constrained('academic_units')->cascadeOnDelete();
            $table->foreignUuid('cpl_id')->constrained('cpl')->cascadeOnDelete();
            $table->string('kode', 15);
            $table->timestamps();

            $table->unique(['academic_unit_id', 'cpl_id'], 'uq_cpl_kode_ov_unit_cpl');
            $table->unique(['academic_unit_id', 'kode'], 'uq_cpl_kode_ov_unit_kode');
        });

        Schema::create('bok_kode_overrides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('academic_unit_id')->constrained('academic_units')->cascadeOnDelete();
            $table->foreignUuid('bok_id')->constrained('bok')->cascadeOnDelete();
            $table->string('kode', 15);
            $table->timestamps();

            $table->unique(['academic_unit_id', 'bok_id'], 'uq_bok_kode_ov_unit_bok');
            $table->unique(['academic_unit_id', 'kode'], 'uq_bok_kode_ov_unit_kode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bok_kode_overrides');
        Schema::dropIfExists('cpl_kode_overrides');
    }
};
