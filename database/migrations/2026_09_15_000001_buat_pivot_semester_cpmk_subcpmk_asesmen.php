<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ikatan semester dipindah dari kolom pada baris entitas ke tabel pivot,
 * supaya SATU baris (satu ID) dapat berlaku di banyak semester. Ini yang
 * membuat "pakai CPMK/Sub-CPMK/Asesmen semester lalu" benar-benar memakai
 * ulang ID lama, bukan menyalin baris baru berisi kode lama.
 *
 * Migration ini murni aditif — pengisian datanya dan pembuangan kolom lama
 * ada di 2026_09_15_000002.
 */
return new class extends Migration
{
    public function up(): void
    {
        // semester_id sengaja restrictOnDelete (bukan cascade) supaya jaminan
        // lama "semester yang sudah berisi data tidak bisa dihapus" —
        // komponen_penilaian.semester_id hari ini restrictOnDelete — tidak
        // diam-diam turun jadi "menghapus semester ikut menghapus datanya".
        Schema::create('cpmk_semester', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('cpmk_id')
                ->constrained('cpmk')
                ->cascadeOnDelete();
            $table->foreignUuid('semester_id')
                ->constrained('semesters')
                ->restrictOnDelete();
            $table->timestamps();

            $table->unique(['cpmk_id', 'semester_id'], 'uq_cpmk_sem');
            $table->index('semester_id', 'idx_cpmk_sem_semester');
        });

        // bobot ikut pindah ke pivot: subcpmk.bobot adalah kolom TURUNAN yang
        // dihitung ulang dari seluruh pemetaan Sub-CPMK ↔ Asesmen. Tanpa
        // pemisahan per semester, menyimpan pemetaan di semester B akan
        // menimpa angka yang dibaca laporan semester A.
        Schema::create('subcpmk_semester', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('subcpmk_id')
                ->constrained('subcpmk')
                ->cascadeOnDelete();
            $table->foreignUuid('semester_id')
                ->constrained('semesters')
                ->restrictOnDelete();
            $table->double('bobot')->nullable();
            $table->timestamps();

            $table->unique(['subcpmk_id', 'semester_id'], 'uq_subcpmk_sem');
            $table->index('semester_id', 'idx_subcpmk_sem_semester');
        });

        // Idem untuk bobot asesmen terhadap nilai akhir MK: harus bisa beda
        // tiap semester walau asesmennya sama (lihat KelasMk::penugasanSelesai(),
        // BobotKomponenSama100Rule, NormalisasiBobotKomponenService yang
        // semuanya bercakupan mk_id + semester_id).
        Schema::create('komponen_penilaian_semester', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('komponen_penilaian_id')
                ->constrained('komponen_penilaian')
                ->cascadeOnDelete();
            $table->foreignUuid('semester_id')
                ->constrained('semesters')
                ->restrictOnDelete();
            $table->decimal('bobot', 5, 2)->default(100.00);
            $table->timestamps();

            $table->unique(['komponen_penilaian_id', 'semester_id'], 'uq_kp_sem');
            $table->index('semester_id', 'idx_kp_sem_semester');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('komponen_penilaian_semester');
        Schema::dropIfExists('subcpmk_semester');
        Schema::dropIfExists('cpmk_semester');
    }
};
