<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda penugasan unit untuk peran "Validator Nilai": pengguna yang
 * memvalidasi nilai kelas sebelum laporan kelas boleh dicetak.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_unit_users', function (Blueprint $table) {
            $table->boolean('status_validator_nilai')->default(false)->after('status_tim_kurikulum');
        });
    }

    public function down(): void
    {
        Schema::table('academic_unit_users', function (Blueprint $table) {
            $table->dropColumn('status_validator_nilai');
        });
    }
};
