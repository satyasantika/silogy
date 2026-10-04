<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sandbox simulasi dibagi dua mode:
 *  - terisi : kurikulum sampai nilai sudah terisi (contoh hasil akhir).
 *  - kosong : hanya unit, akun, dan mahasiswa; pengunjung sendiri yang
 *             mengisi dari kurikulum sampai nilai (contoh proses).
 *
 * jumlah_mk menyimpan berapa MK yang dibangun pada mode terisi, supaya
 * sandbox lama (tanpa kolom ini) tetap terbaca sebagai paket penuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('simulasi_jalan', function (Blueprint $table): void {
            $table->string('mode', 10)->default('terisi')->after('status');
            $table->unsignedTinyInteger('jumlah_mk')->default(6)->after('mode');

            $table->index(['mode', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('simulasi_jalan', function (Blueprint $table): void {
            $table->dropIndex(['mode', 'status']);
            $table->dropColumn(['mode', 'jumlah_mk']);
        });
    }
};
