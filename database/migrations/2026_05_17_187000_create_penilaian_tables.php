<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluasi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 30)->unique();
            $table->string('kategori', 100)->nullable()->index();
            $table->string('workcloud', 100)->nullable();
            $table->string('nama', 150);
            $table->timestamps();
        });

        Schema::create('komponen_penilaian', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('kelas_mk_id')
                ->constrained('kelas_mk')
                ->cascadeOnDelete();
            $table->foreignUuid('evaluasi_id')
                ->constrained('evaluasi')
                ->restrictOnDelete();
            $table->string('kode', 30)->nullable();
            $table->string('nama', 100);
            $table->decimal('bobot', 5, 2)->default(100.00);
            $table->timestamps();

            $table->index('kelas_mk_id', 'idx_komponen_kelas');
        });

        Schema::create('subcpmk_komponenpenilaian', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('subcpmk_id')
                ->constrained('subcpmk')
                ->cascadeOnDelete();
            $table->foreignUuid('komponen_penilaian_id')
                ->constrained('komponen_penilaian')
                ->cascadeOnDelete();
            $table->foreignUuid('semester_id')
                ->nullable()
                ->constrained('semesters')
                ->nullOnDelete();
            $table->double('bobot')->default(100);
            $table->timestamps();

            $table->unique(['subcpmk_id', 'komponen_penilaian_id'], 'uq_skp');
        });

        Schema::create('nilai_mahasiswas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('subcpmk_komponenpenilaian_id')
                ->constrained('subcpmk_komponenpenilaian')
                ->cascadeOnDelete();
            $table->foreignUuid('kelas_mk_mahasiswa_id')
                ->constrained('kelas_mk_mahasiswa')
                ->cascadeOnDelete();
            $table->decimal('nilai', 5, 2)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(
                ['subcpmk_komponenpenilaian_id', 'kelas_mk_mahasiswa_id'],
                'uq_nilai_skp_kmm'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nilai_mahasiswas');
        Schema::dropIfExists('subcpmk_komponenpenilaian');
        Schema::dropIfExists('komponen_penilaian');
        Schema::dropIfExists('evaluasi');
    }
};
