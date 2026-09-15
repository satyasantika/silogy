<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Usulan perubahan CPMK dari Koordinator MK kepada Tim Kurikulum.
 *
 * CPMK terpetakan ke CPL lewat mk_cpmk, jadi mengubahnya menyentuh kontrak
 * kurikulum — bukan lagi urusan satu mata kuliah saja. Memakai ULANG CPMK
 * semester lalu tidak lewat sini; yang butuh persetujuan hanyalah menyusun
 * atau mengubah CPMK untuk semester berjalan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perubahan_cpmk_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('mk_id')->constrained('mk')->cascadeOnDelete();
            $table->foreignUuid('semester_id')->constrained('semesters')->restrictOnDelete();
            // Disalin dari mk.academic_unit_id saat diajukan: inilah yang
            // menentukan Tim Kurikulum mana yang berwenang menyetujui, dan
            // ia harus tetap stabil walau MK dipindah unit kemudian.
            $table->foreignUuid('academic_unit_id')->constrained('academic_units')->restrictOnDelete();
            $table->foreignUuid('diajukan_oleh_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('diajukan');
            $table->text('alasan');
            $table->json('ringkasan_usulan')->nullable();
            $table->foreignUuid('ditinjau_oleh_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ditinjau_pada')->nullable();
            $table->text('catatan_peninjau')->nullable();
            $table->timestamps();

            // Tidak ada partial unique di MySQL, jadi aturan "satu usulan
            // terbuka per (MK, semester)" ditegakkan di PerubahanCpmkService.
            $table->index(['mk_id', 'semester_id', 'status'], 'idx_pcr_mk_semester_status');
            $table->index(['academic_unit_id', 'status'], 'idx_pcr_unit_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perubahan_cpmk_requests');
    }
};
