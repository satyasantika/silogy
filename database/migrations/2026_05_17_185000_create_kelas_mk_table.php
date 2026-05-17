<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kelas_mk', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('mk_unit_id')
                ->constrained('mk_units')
                ->cascadeOnDelete();
            $table->foreignUuid('semester_id')
                ->constrained('semesters')
                ->restrictOnDelete();
            $table->string('kode_kelas', 10);
            $table->foreignUuid('dosen_pengampu_id')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignUuid('koordinator_mk_id')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->unsignedSmallInteger('kapasitas')->nullable();
            $table->timestamps();

            $table->unique(['mk_unit_id', 'semester_id', 'kode_kelas'], 'uq_kmk_unit_sem_kls');
        });

        Schema::create('kelas_mk_mahasiswa', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('kelas_mk_id')
                ->constrained('kelas_mk')
                ->cascadeOnDelete();
            $table->foreignUuid('mahasiswa_id')
                ->constrained('mahasiswas')
                ->cascadeOnDelete();
            $table->decimal('nilai_angka', 5, 2)->nullable();
            $table->string('nilai_huruf', 5)->nullable();
            $table->timestamps();

            $table->unique(['kelas_mk_id', 'mahasiswa_id'], 'uq_kmm');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kelas_mk_mahasiswa');
        Schema::dropIfExists('kelas_mk');
    }
};
