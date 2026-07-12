<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kelas_mk_sintesys_imports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('semester_id')->nullable()
                ->constrained('semesters')->nullOnDelete();
            $table->foreignUuid('academic_unit_id')->nullable()
                ->constrained('academic_units')->nullOnDelete();
            $table->string('tahun_akademik', 10);
            $table->string('kode_prodi', 30);
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
            $table->unsignedInteger('total')->nullable();
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('kelas_dibuat')->default(0);
            $table->unsignedInteger('kelas_diperbarui')->default(0);
            $table->unsignedInteger('peserta_terdaftar')->default(0);
            $table->unsignedInteger('peserta_sudah_terdaftar')->default(0);
            $table->json('errors')->nullable();
            $table->text('pesan_gagal')->nullable();
            $table->foreignUuid('dibuat_oleh')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kelas_mk_sintesys_imports');
    }
};
