<?php

use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\Institusi\Support\AcademicUnitUserRoleSync;
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
        $this->sinkronkanPeranValidator();
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

    /**
     * Pimpinan/tim kurikulum menjadi berhak validasi lewat migrasi ini, tetapi
     * perannya belum ada; sinkronkan. No-op bila role Validator Nilai belum
     * dibuat (migrasi 2026_11_13_000002) atau tabel pendukung belum ada.
     */
    private function sinkronkanPeranValidator(): void
    {
        foreach (['roles', 'model_has_roles', 'users', 'academic_unit_users'] as $tabel) {
            if (! Schema::hasTable($tabel)) {
                return;
            }
        }

        if (! DB::table('roles')->where('name', 'Validator Nilai')->exists()) {
            return;
        }

        $sinkron = app(AcademicUnitUserRoleSync::class);

        foreach ($sinkron->userIdsTerkait() as $userId) {
            $sinkron->syncForUser($userId);
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
