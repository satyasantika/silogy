<?php

namespace App\Modules\Penilaian\Services;

use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Support\BobotNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * Menormalisasi bobot interaksi Sub-CPMK ↔ Asesmen milik satu komponen
 * penilaian, secara proporsional dan dibulatkan ke bilangan bulat
 * terdekat, agar totalnya tepat 100%.
 */
class NormalisasiBobotSubcpmkService
{
    /**
     * @return array{status: 'kosong'|'sudah_pas'|'dinormalisasi', jumlah: int, total_sebelum: float}
     */
    public function normalisasi(KomponenPenilaian $komponen): array
    {
        $rows = $komponen->subcpmkKomponens()->get();

        $total = (float) $rows->sum('bobot');

        if ($rows->isEmpty() || $total <= 0) {
            return ['status' => 'kosong', 'jumlah' => $rows->count(), 'total_sebelum' => $total];
        }

        if (abs($total - 100) < 0.01) {
            return ['status' => 'sudah_pas', 'jumlah' => $rows->count(), 'total_sebelum' => $total];
        }

        $bobotPerId = $rows->mapWithKeys(fn ($row) => [$row->getKey() => (float) $row->bobot]);
        $dibulatkan = BobotNormalizer::keSeratus($bobotPerId);

        DB::transaction(function () use ($rows, $dibulatkan): void {
            foreach ($rows as $row) {
                $row->update(['bobot' => $dibulatkan[$row->getKey()]]);
            }
        });

        return ['status' => 'dinormalisasi', 'jumlah' => $rows->count(), 'total_sebelum' => $total];
    }
}
