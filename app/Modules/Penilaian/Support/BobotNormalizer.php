<?php

namespace App\Modules\Penilaian\Support;

use Illuminate\Support\Collection;

/**
 * Membagi ulang sekumpulan bobot secara proporsional lalu membulatkannya ke
 * bilangan bulat terdekat, dengan sisa pembulatan dikoreksi pada kunci
 * berbobot terbesar, agar totalnya tepat 100.
 */
class BobotNormalizer
{
    /**
     * @param  Collection<array-key, float>  $bobotPerKunci
     * @return array<array-key, int>
     */
    public static function keSeratus(Collection $bobotPerKunci): array
    {
        $total = (float) $bobotPerKunci->sum();

        if ($bobotPerKunci->isEmpty() || $total <= 0) {
            return [];
        }

        $dibulatkan = $bobotPerKunci
            ->map(fn (float $bobot): int => (int) round($bobot * 100 / $total))
            ->all();

        $selisih = 100 - array_sum($dibulatkan);

        if ($selisih !== 0) {
            $kunciTerbesar = $bobotPerKunci->sortDesc()->keys()->first();
            $dibulatkan[$kunciTerbesar] += $selisih;
        }

        return $dibulatkan;
    }
}
