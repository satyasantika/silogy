<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contoh terisi kini satu salinan bersama yang hanya-baca untuk semua
 * pengunjung, bukan satu sandbox per pengunjung. Kolom `bersama` menandai
 * sandbox itu supaya tidak masuk kolam, tidak diklaim, dan tidak kedaluwarsa
 * karena tak ada aktivitas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('simulasi_jalan', function (Blueprint $table): void {
            $table->boolean('bersama')->default(false)->after('mode');
        });
    }

    public function down(): void
    {
        Schema::table('simulasi_jalan', function (Blueprint $table): void {
            $table->dropColumn('bersama');
        });
    }
};
