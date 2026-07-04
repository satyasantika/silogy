<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profil_indikators', function (Blueprint $table) {
            $table->unsignedTinyInteger('urutan')->nullable()->after('deskripsi');
        });

        $profilIds = DB::table('profil_lulusan')->pluck('id');

        foreach ($profilIds as $profilId) {
            $indikatorIds = DB::table('profil_indikators')
                ->where('profil_id', $profilId)
                ->orderBy('created_at')
                ->orderBy('id')
                ->pluck('id');

            foreach ($indikatorIds as $index => $indikatorId) {
                DB::table('profil_indikators')
                    ->where('id', $indikatorId)
                    ->update(['urutan' => $index + 1]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('profil_indikators', function (Blueprint $table) {
            $table->dropColumn('urutan');
        });
    }
};
