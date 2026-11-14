<?php

namespace App\Modules\Penilaian\Observers;

use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;
use App\Modules\Penilaian\Services\RincianNilaiService;
use App\Modules\Penilaian\Services\SubcpmkAsesmenPemetaanService;

/**
 * Setiap interaksi Sub-CPMK ↔ penugasan (asesmen) berubah — dipetakan,
 * bobot pivotnya diubah, atau pemetaannya dihapus — bobot Sub-CPMK dihitung
 * ulang otomatis dari seluruh interaksinya PADA SEMESTER PEMETAAN ITU.
 *
 * Semesternya wajib ikut: satu baris Sub-CPMK kini boleh dipakai di
 * beberapa semester, jadi menghitung ulang tanpa menyebut semester akan
 * menimpa bobot semester lain yang nilainya mungkin sudah final.
 */
class SubcpmkKomponenPenilaianObserver
{
    public function saved(SubcpmkKomponenPenilaian $pivot): void
    {
        $this->hitungUlang($pivot);
    }

    public function deleted(SubcpmkKomponenPenilaian $pivot): void
    {
        $this->hitungUlang($pivot);
    }

    private function hitungUlang(SubcpmkKomponenPenilaian $pivot): void
    {
        $this->rincianIkutBerubah($pivot);

        if (blank($pivot->semester_id)) {
            return;
        }

        SubcpmkAsesmenPemetaanService::recalculateBobotSubcpmk(
            (string) $pivot->subcpmk_id,
            (string) $pivot->semester_id,
        );
    }

    /**
     * Nilai asesmen yang dirinci per Sub-CPMK bergantung pada pivotnya; nilai
     * akhir tersimpan kelas yang merincinya ikut dihitung ulang. Bila pivot
     * dipindah ke asesmen/semester lain, pasangan lamanya juga terdampak.
     */
    private function rincianIkutBerubah(SubcpmkKomponenPenilaian $pivot): void
    {
        $pasangan = [
            [$pivot->komponen_penilaian_id, $pivot->semester_id],
            [$pivot->getOriginal('komponen_penilaian_id'), $pivot->getOriginal('semester_id')],
        ];

        $sudah = [];

        foreach ($pasangan as [$komponenId, $semesterId]) {
            if (blank($komponenId) || blank($semesterId) || isset($sudah[$komponenId.'|'.$semesterId])) {
                continue;
            }

            $sudah[$komponenId.'|'.$semesterId] = true;
            app(RincianNilaiService::class)->pemetaanAsesmenBerubah((string) $komponenId, (string) $semesterId);
        }
    }
}
