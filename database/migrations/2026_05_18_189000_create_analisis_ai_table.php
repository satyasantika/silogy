<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analisis_ai', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('academic_unit_id')->nullable()
                ->constrained('academic_units')->nullOnDelete();
            $table->foreignUuid('semester_id')->nullable()
                ->constrained('semesters')->nullOnDelete();
            $table->enum('jenis', [
                'ringkasan_cpl', 'rekomendasi_kurikulum', 'tren_capaian', 'lainnya',
            ]);
            $table->json('konteks')->nullable();
            $table->text('prompt');
            $table->longText('hasil')->nullable();
            $table->string('model_ai', 80)->nullable();
            $table->unsignedInteger('token_digunakan')->nullable();
            $table->unsignedInteger('durasi_ms')->nullable();
            $table->foreignUuid('dibuat_oleh')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analisis_ai');
    }
};
