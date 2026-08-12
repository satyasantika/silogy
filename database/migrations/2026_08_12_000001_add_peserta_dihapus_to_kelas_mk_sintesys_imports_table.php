<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kelas_mk_sintesys_imports', function (Blueprint $table) {
            $table->unsignedInteger('peserta_dihapus')->default(0)->after('peserta_sudah_terdaftar');
        });
    }

    public function down(): void
    {
        Schema::table('kelas_mk_sintesys_imports', function (Blueprint $table) {
            $table->dropColumn('peserta_dihapus');
        });
    }
};
