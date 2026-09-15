<?php

use App\Modules\MK\Services\KonsolidasiLintasSemesterService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pindahkan ikatan semester (dan bobot yang menyertainya) dari kolom pada
 * baris entitas ke tabel pivot 2026_09_15_000001, sekaligus melebur baris
 * kembar lintas semester yang dulu lahir dari mekanisme salin-antar-semester.
 *
 * Jalankan `php artisan mk:konsolidasi-lintas-semester` lebih dulu pada
 * salinan data untuk melihat apa yang akan digabung — terutama bila
 * laporannya menyebut nilai mahasiswa yang akan dibuang.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(KonsolidasiLintasSemesterService::class)->terapkan();

        Schema::table('subcpmk', function (Blueprint $table): void {
            $table->dropForeign(['semester_id']);
            $table->dropIndex('idx_subcpmk_semester');
            $table->dropColumn(['semester_id', 'bobot']);

            // Dengan pakai-ulang berbasis ID, kode adalah identitas kanonik
            // Sub-CPMK dalam satu pemetaan CPMK — harus dijaga unik.
            $table->unique(['mk_cpmk_id', 'kode'], 'uq_subcpmk_mkcpmk_kode');
        });

        Schema::table('cpmk', function (Blueprint $table): void {
            $table->unique(['mk_id', 'kode'], 'uq_cpmk_mk_kode');
        });

        Schema::table('komponen_penilaian', function (Blueprint $table): void {
            $table->dropUnique('uq_komponen_mk_semester_kode');
            $table->dropForeign(['semester_id']);
            $table->dropColumn(['semester_id', 'bobot']);
        });

        Schema::table('komponen_penilaian', function (Blueprint $table): void {
            // kode sebelumnya nullable; MySQL mengizinkan NULL berulang pada
            // UNIQUE, jadi baris tanpa kode tidak akan pernah bisa dicocokkan.
            // Konsolidasi sudah menurunkan kode dari nama untuk baris kosong.
            $table->string('kode', 30)->nullable(false)->change();
            $table->unique(['mk_id', 'kode'], 'uq_komponen_mk_kode');
        });

        Schema::table('subcpmk_komponenpenilaian', function (Blueprint $table): void {
            $table->dropForeign(['semester_id']);
            $table->dropUnique('uq_skp');
        });

        Schema::table('subcpmk_komponenpenilaian', function (Blueprint $table): void {
            // nullOnDelete mustahil berdampingan dengan NOT NULL; semester
            // kini penentu tunggal berlakunya satu pemetaan.
            $table->uuid('semester_id')->nullable(false)->change();
            $table->foreign('semester_id')->references('id')->on('semesters')->restrictOnDelete();
            $table->unique(
                ['subcpmk_id', 'komponen_penilaian_id', 'semester_id'],
                'uq_skp_semester',
            );
        });
    }

    /**
     * Tidak mungkin setia sepenuhnya: baris yang sudah dilebur tidak bisa
     * dipecah kembali, dan satu baris yang berlaku di banyak semester harus
     * memilih satu. Mengikuti preseden 2026_07_13_000001 yang juga jujur
     * bahwa arah baliknya merusak data.
     */
    public function down(): void
    {
        Schema::table('subcpmk_komponenpenilaian', function (Blueprint $table): void {
            $table->dropUnique('uq_skp_semester');
            $table->dropForeign(['semester_id']);
        });

        Schema::table('subcpmk_komponenpenilaian', function (Blueprint $table): void {
            $table->uuid('semester_id')->nullable()->change();
            $table->foreign('semester_id')->references('id')->on('semesters')->nullOnDelete();
            $table->unique(['subcpmk_id', 'komponen_penilaian_id'], 'uq_skp');
        });

        Schema::table('komponen_penilaian', function (Blueprint $table): void {
            $table->dropUnique('uq_komponen_mk_kode');
            $table->string('kode', 30)->nullable()->change();
            $table->decimal('bobot', 5, 2)->default(100.00);
            $table->foreignUuid('semester_id')
                ->nullable()
                ->constrained('semesters')
                ->restrictOnDelete();
        });

        Schema::table('cpmk', function (Blueprint $table): void {
            $table->dropUnique('uq_cpmk_mk_kode');
        });

        Schema::table('subcpmk', function (Blueprint $table): void {
            $table->dropUnique('uq_subcpmk_mkcpmk_kode');
            $table->double('bobot')->nullable();
            $table->foreignUuid('semester_id')
                ->nullable()
                ->constrained('semesters')
                ->nullOnDelete();
            $table->index('semester_id', 'idx_subcpmk_semester');
        });
    }
};
