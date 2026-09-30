<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Buku besar kepemilikan data simulasi.
 *
 * Tanpa tabel ini tidak ada cara membuktikan baris mana yang lahir dari
 * simulasi dan baris mana yang memang data nyata: SimulasiAkademikBuilder
 * memakai firstOrCreate di mana-mana sehingga ia bisa "mengadopsi" baris
 * yang sudah ada sebelumnya. Pencocokan lewat awalan kode (SIM-, CPL-SIM-)
 * tidak cukup — basis data produksi sudah memuat sisa simulasi lama yang
 * yatim berdampingan dengan kurikulum nyata.
 *
 * Invariannya: sebuah baris hanya dicatat di sini bila simulasi benar-benar
 * MEMBUATNYA. Baris yang cuma diadopsi tidak pernah masuk, karena itu tidak
 * pernah ikut terhapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simulasi_jalan', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // berjalan | selesai | gagal | dibongkar
            $table->string('status', 20)->default('berjalan');
            $table->foreignUuid('dipicu_oleh_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignUuid('semester_id')->nullable()
                ->constrained('semesters')->nullOnDelete();
            $table->json('ringkasan')->nullable();
            $table->json('peringatan')->nullable();
            $table->timestamp('mulai_pada')->nullable();
            $table->timestamp('selesai_pada')->nullable();
            $table->timestamp('dibongkar_pada')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('simulasi_artefak', function (Blueprint $table): void {
            // Sengaja auto-increment, bukan UUID: urutan penyisipan adalah
            // urutan ketergantungan, dan pembongkaran berjalan mundur di atasnya.
            $table->bigIncrements('id');
            $table->foreignUuid('simulasi_jalan_id')
                ->constrained('simulasi_jalan')->cascadeOnDelete();
            $table->string('model_type');
            // Bukan foreign key: satu kolom ini menunjuk ke 25 tabel berbeda.
            $table->char('model_uuid', 36);
            $table->timestamp('created_at')->nullable();

            $table->unique(
                ['simulasi_jalan_id', 'model_type', 'model_uuid'],
                'uq_simulasi_artefak',
            );
            $table->index(['model_type', 'model_uuid'], 'idx_simulasi_artefak_model');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulasi_artefak');
        Schema::dropIfExists('simulasi_jalan');
    }
};
