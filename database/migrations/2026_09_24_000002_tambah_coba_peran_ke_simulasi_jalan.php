<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sakelar "Coba sebagai ‹peran›" dipindahkan dari berkas .env ke basis data.
 *
 * Alasannya praktis: pada banyak pemasangan, orang yang berwenang memutuskan
 * boleh-tidaknya mode latihan dibuka justru tidak punya akses menyunting .env
 * di peladen. Menaruh sakelarnya di sini membuat keputusan itu bisa diambil —
 * dan DICABUT kembali dalam hitungan detik — oleh Super Admin lewat antarmuka.
 *
 * Sakelar sengaja menempel pada JALAN simulasi, bukan pada tabel pengaturan
 * global. Konsekuensinya baik: menghapus data simulasi otomatis mematikan
 * jalur masuk tanpa kata sandi, karena sakelarnya ikut terhapus bersamanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('simulasi_jalan', function (Blueprint $table): void {
            $table->boolean('coba_peran')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('simulasi_jalan', function (Blueprint $table): void {
            $table->dropColumn('coba_peran');
        });
    }
};
