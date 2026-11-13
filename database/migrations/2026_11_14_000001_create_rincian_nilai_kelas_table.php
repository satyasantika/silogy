<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda rincian nilai per Sub-CPMK: satu baris per (kelas, asesmen) yang
 * nilainya dirinci. Baris ada = kolom asesmen di kelas itu tampil sebagai
 * sub-kolom per Sub-CPMK pada Input Nilai.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rincian_nilai_kelas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('kelas_mk_id')->constrained('kelas_mk')->cascadeOnDelete();
            $table->foreignUuid('komponen_penilaian_id')->constrained('komponen_penilaian')->cascadeOnDelete();
            $table->foreignUuid('dinyalakan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['kelas_mk_id', 'komponen_penilaian_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rincian_nilai_kelas');
    }
};
