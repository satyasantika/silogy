<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mahasiswas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nim', 20)->unique();
            $table->string('nama', 150)->nullable();
            $table->enum('jenis_kelamin', ['L', 'P'])->nullable();
            $table->string('angkatan', 4)->nullable();
            $table->foreignUuid('academic_unit_id')
                ->constrained('academic_units')
                ->restrictOnDelete();
            $table->string('email', 100)->nullable();
            $table->string('nomor_wa', 20)->nullable();
            $table->enum('status', ['aktif', 'cuti', 'lulus', 'do', 'nonaktif'])->default('aktif');
            $table->timestamps();

            $table->index('nim', 'idx_mhs_nim');
            $table->index('academic_unit_id', 'idx_mhs_unit');
            $table->index('angkatan', 'idx_mhs_angkatan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mahasiswas');
    }
};
