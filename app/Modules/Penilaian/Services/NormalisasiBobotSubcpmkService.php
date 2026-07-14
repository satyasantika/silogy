<?php

namespace App\Modules\Penilaian\Services;

use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Support\BobotNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * Menormalisasi bobot interaksi Sub-CPMK ↔ Asesmen milik satu komponen
 * penilaian, secara proporsional dan dibulatkan ke 2 desimal, agar totalnya
 * tepat sama dengan bobot Asesmen itu sendiri (bukan lagi selalu 100%).
 */
class NormalisasiBobotSubcpmkService
{
    /**
     * @return array{status: 'kosong'|'sudah_pas'|'dinormalisasi', jumlah: int, total_sebelum: float}
     */
    public function normalisasi(KomponenPenilaian $komponen): array
    {
        $target = (float) $komponen->bobot;

        $rows = $komponen->subcpmkKomponens()->get();

        $total = (float) $rows->sum('bobot');

        if ($rows->isEmpty() || $total <= 0 || $target <= 0) {
            return ['status' => 'kosong', 'jumlah' => $rows->count(), 'total_sebelum' => $total];
        }

        if (abs($total - $target) < 0.01) {
            return ['status' => 'sudah_pas', 'jumlah' => $rows->count(), 'total_sebelum' => $total];
        }

        $bobotPerId = $rows->mapWithKeys(fn ($row) => [$row->getKey() => (float) $row->bobot]);
        $dibulatkan = BobotNormalizer::keTarget($bobotPerId, $target);

        DB::transaction(function () use ($rows, $dibulatkan): void {
            foreach ($rows as $row) {
                $row->update(['bobot' => $dibulatkan[$row->getKey()]]);
            }
        });

        return ['status' => 'dinormalisasi', 'jumlah' => $rows->count(), 'total_sebelum' => $total];
    }
}
