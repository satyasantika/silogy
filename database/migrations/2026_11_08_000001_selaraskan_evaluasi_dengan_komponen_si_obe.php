<?php

use App\Modules\Penilaian\Support\SelaraskanEvaluasiSiObe;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluasi', function (Blueprint $table) {
            $table->unsignedSmallInteger('urutan')->nullable()->after('nama');
        });

        SelaraskanEvaluasiSiObe::jalankan();
    }

    /**
     * Hanya kolom urutan yang dikembalikan. Peleburan Studi Kasus tidak
     * dapat dibalik (tidak tercatat asesmen mana yang semula Studi Kasus);
     * nama baru tetap dipakai.
     */
    public function down(): void
    {
        Schema::table('evaluasi', function (Blueprint $table) {
            $table->dropColumn('urutan');
        });
    }
};
