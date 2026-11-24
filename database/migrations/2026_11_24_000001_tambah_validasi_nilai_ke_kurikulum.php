<?php

use App\Modules\Institusi\Support\AcademicUnitScope;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Validasi nilai per kurikulum: switch `validasi_nilai_aktif` dan pivot
 * `kurikulum_validator` (validator tambahan di luar pimpinan/tim kurikulum).
 * Penanda lama academic_unit_users.status_validator_nilai hanya dibaca untuk
 * penyalinan satu kali; penyalinan idempoten (insertOrIgnore).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('kurikulum', 'validasi_nilai_aktif')) {
            Schema::table('kurikulum', function (Blueprint $table) {
                $table->boolean('validasi_nilai_aktif')->default(true);
            });
        }

        if (! Schema::hasTable('kurikulum_validator')) {
            Schema::create('kurikulum_validator', function (Blueprint $table) {
                $table->foreignUuid('kurikulum_id')->constrained('kurikulum')->cascadeOnDelete();
                $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
                $table->primary(['kurikulum_id', 'user_id']);
            });
        }

        $this->salinValidatorLama();
    }

    public function down(): void
    {
        Schema::dropIfExists('kurikulum_validator');

        if (Schema::hasColumn('kurikulum', 'validasi_nilai_aktif')) {
            Schema::table('kurikulum', function (Blueprint $table) {
                $table->dropColumn('validasi_nilai_aktif');
            });
        }
    }

    private function salinValidatorLama(): void
    {
        if (! Schema::hasColumn('academic_unit_users', 'status_validator_nilai')) {
            return;
        }

        $penugasan = DB::table('academic_unit_users')->where('status_validator_nilai', true)->get();
        $sekarang = now();

        foreach ($penugasan as $baris) {
            $unitIds = AcademicUnitScope::descendantIdsIncludingSelf($baris->academic_unit_id)->all();
            $kurikulum = DB::table('kurikulum')->whereIn('academic_unit_id', $unitIds)->get(['id', 'academic_unit_id']);

            foreach ($kurikulum as $k) {
                $sudahPengelola = DB::table('academic_unit_users')
                    ->where('academic_unit_id', $k->academic_unit_id)
                    ->where('user_id', $baris->user_id)
                    ->where(fn ($q) => $q->where('status_pimpinan', true)->orWhere('status_tim_kurikulum', true))
                    ->exists();

                if ($sudahPengelola) {
                    continue;
                }

                DB::table('kurikulum_validator')->insertOrIgnore([
                    'kurikulum_id' => $k->id,
                    'user_id' => $baris->user_id,
                    'created_at' => $sekarang,
                    'updated_at' => $sekarang,
                ]);
            }
        }
    }
};
