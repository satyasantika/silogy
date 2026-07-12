<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Komponen penilaian tidak lagi milik satu kelas MK, melainkan satu
        // definisi per mata kuliah + semester (dipakai bersama semua kelas
        // MK-nya). Data uji coba yang ada dikosongkan agar migrasi skema
        // tidak perlu logika dedup yang rumit.
        DB::table('nilai_mahasiswas')->delete();
        DB::table('subcpmk_komponenpenilaian')->delete();
        DB::table('komponen_penilaian')->delete();

        Schema::table('komponen_penilaian', function (Blueprint $table): void {
            $table->dropForeign(['kelas_mk_id']);
            $table->dropIndex('idx_komponen_kelas');
            $table->dropColumn('kelas_mk_id');

            $table->foreignUuid('mk_id')
                ->after('id')
                ->constrained('mk')
                ->cascadeOnDelete();
            $table->foreignUuid('semester_id')
                ->after('mk_id')
                ->constrained('semesters')
                ->restrictOnDelete();

            $table->unique(['mk_id', 'semester_id', 'kode'], 'uq_komponen_mk_semester_kode');
        });
    }

    public function down(): void
    {
        Schema::table('komponen_penilaian', function (Blueprint $table): void {
            $table->dropUnique('uq_komponen_mk_semester_kode');
            $table->dropForeign(['mk_id']);
            $table->dropForeign(['semester_id']);
            $table->dropColumn(['mk_id', 'semester_id']);

            $table->foreignUuid('kelas_mk_id')
                ->after('id')
                ->constrained('kelas_mk')
                ->cascadeOnDelete();
            $table->index('kelas_mk_id', 'idx_komponen_kelas');
        });
    }
};
