<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Urutan tampil untuk CPL/BoK ASING (milik unit lain, tersingkap lewat
 * adaptasi MK) — privat per unit yang mengadaptasi. Baris di sini dibuat
 * lazily persis seperti kode alias: hanya ada bila unit benar-benar
 * pernah menggeser urutan atau mengubah alias kode-nya. NULL berarti
 * "belum diurutkan manual" dan otomatis tersortir ke akhir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cpl_kode_overrides', function (Blueprint $table) {
            $table->unsignedInteger('urutan')->nullable()->after('kode');
        });

        Schema::table('bok_kode_overrides', function (Blueprint $table) {
            $table->unsignedInteger('urutan')->nullable()->after('kode');
        });
    }

    public function down(): void
    {
        Schema::table('bok_kode_overrides', function (Blueprint $table) {
            $table->dropColumn('urutan');
        });

        Schema::table('cpl_kode_overrides', function (Blueprint $table) {
            $table->dropColumn('urutan');
        });
    }
};
