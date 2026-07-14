<?php

use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;
use Illuminate\Database\Migrations\Migration;

/**
 * Mengonversi bobot pivot Sub-CPMK <-> Asesmen dari skema lama (0-100, berbagi
 * 100 di antara Sub-CPMK yang sama-sama berinteraksi dengan satu Asesmen) ke
 * skema baru (langsung berupa kontribusi nyata, skala sama dengan
 * KomponenPenilaian.bobot, berjumlah maksimal bobot Asesmen itu).
 *
 * bobot_baru = bobot_lama * KomponenPenilaian.bobot / 100 — supaya hasil
 * SubcpmkAsesmenPemetaanService::recalculateBobotSubcpmk() (dan nilai akhir
 * mahasiswa yang bergantung padanya) tetap identik sebelum/sesudah migrasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        SubcpmkKomponenPenilaian::query()
            ->with('komponenPenilaian')
            ->chunkById(200, function ($pivots): void {
                foreach ($pivots as $pivot) {
                    $bobotKomponen = (float) $pivot->komponenPenilaian->bobot;

                    if ($bobotKomponen <= 0) {
                        continue;
                    }

                    $pivot->update([
                        'bobot' => round((float) $pivot->bobot * $bobotKomponen / 100, 2),
                    ]);
                }
            });
    }

    public function down(): void
    {
        SubcpmkKomponenPenilaian::query()
            ->with('komponenPenilaian')
            ->chunkById(200, function ($pivots): void {
                foreach ($pivots as $pivot) {
                    $bobotKomponen = (float) $pivot->komponenPenilaian->bobot;

                    if ($bobotKomponen <= 0) {
                        continue;
                    }

                    $pivot->update([
                        'bobot' => round((float) $pivot->bobot * 100 / $bobotKomponen, 2),
                    ]);
                }
            });
    }
};
