<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('simulasi_jalan', function (Blueprint $table): void {
            // Token ruang (6 karakter) yang dibagikan fasilitator. Hanya ruang
            // latihan (mode kosong, tidak bersama) yang memilikinya.
            $table->string('pin', 8)->nullable()->unique()->after('mode');
        });

        Schema::create('simulasi_peran_terisi', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('simulasi_jalan_id')->constrained('simulasi_jalan')->cascadeOnDelete();
            $table->string('peran', 40);
            $table->string('tab_token', 40);
            $table->timestamp('terakhir_aktif_pada')->nullable();
            $table->timestamps();

            // Penjaga balapan: satu peran hanya boleh dipegang satu tab per ruang.
            $table->unique(['simulasi_jalan_id', 'peran'], 'uq_ruang_peran');
            $table->index('tab_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulasi_peran_terisi');

        Schema::table('simulasi_jalan', function (Blueprint $table): void {
            $table->dropUnique(['pin']);
            $table->dropColumn('pin');
        });
    }
};
