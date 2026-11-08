<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan evaluasi & Rencana Tindak Lanjut (RTL) per kelas, ditulis dosen
 * pengampu: satu baris per CPL yang tidak tercapai (cpl_id terisi) plus
 * satu baris catatan umum (cpl_id null). Ikut tercetak di blok F laporan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catatan_rtl_kelas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('kelas_mk_id')->constrained('kelas_mk')->cascadeOnDelete();
            $table->foreignUuid('cpl_id')->nullable()->constrained('cpl')->cascadeOnDelete();
            $table->text('analisis')->nullable();
            $table->text('rencana_tindak_lanjut')->nullable();
            $table->foreignUuid('diubah_oleh_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['kelas_mk_id', 'cpl_id'], 'uq_catatan_rtl_kelas_cpl');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catatan_rtl_kelas');
    }
};
