<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Validasi nilai kelas oleh Validator Nilai: satu baris per kelas. Baris ada =
 * pernah divalidasi; gugur_at terisi = data laporan berubah sejak divalidasi.
 * sidik = sha256 bentuk kanonik data laporan saat divalidasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('validasi_nilai_kelas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('kelas_mk_id')->unique()->constrained('kelas_mk')->cascadeOnDelete();
            $table->foreignUuid('validator_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('divalidasi_at');
            $table->char('sidik', 64);
            $table->timestamp('gugur_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('validasi_nilai_kelas');
    }
};
