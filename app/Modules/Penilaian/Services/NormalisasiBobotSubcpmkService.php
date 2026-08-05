<?php

namespace App\Modules\Penilaian\Services;

use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Support\BobotNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * Menormalisasi bobot interaksi Sub-CPMK ↔ Asesmen milik satu komponen
 * penilaian, secara proporsional dan dibulatkan ke N desimal (default: satuan),
 * agar totalnya tepat sama dengan bobot Asesmen itu sendiri.
 */
class NormalisasiBobotSubcpmkService
{
    /**
     * @return array{status: 'kosong'|'sudah_pas'|'dinormalisasi', jumlah: int, total_sebelum: float}
     */
    public function normalisasi(KomponenPenilaian $komponen, int $desimal = 0): array
    {
        $target = (float) $komponen->bobot;

        $rows = $komponen->subcpmkKomponens()->get();

        $total = (float) $rows->sum('bobot');

        if ($rows->isEmpty() || $total <= 0 || $target <= 0) {
            return ['status' => 'kosong', 'jumlah' => $rows->count(), 'total_sebelum' => $total];
        }

        $bobotPerId = $rows->mapWithKeys(fn ($row) => [$row->getKey() => (float) $row->bobot]);

        if (BobotNormalizer::sudahSesuai($bobotPerId, $target, $desimal)) {
            return ['status' => 'sudah_pas', 'jumlah' => $rows->count(), 'total_sebelum' => $total];
        }

        $dibulatkan = BobotNormalizer::keTarget($bobotPerId, $target, $desimal);

        DB::transaction(function () use ($rows, $dibulatkan): void {
            foreach ($rows as $row) {
                $row->update(['bobot' => $dibulatkan[$row->getKey()]]);
            }
        });

        return ['status' => 'dinormalisasi', 'jumlah' => $rows->count(), 'total_sebelum' => $total];
    }
}
