<?php

namespace App\Modules\Penilaian\Support;

use Illuminate\Support\Collection;

/**
 * Membagi ulang sekumpulan bobot secara proporsional terhadap suatu target,
 * dibulatkan ke 2 desimal, dengan sisa pembulatan dikoreksi pada kunci
 * berbobot terbesar, agar totalnya tepat sama dengan target tersebut.
 */
class BobotNormalizer
{
    /**
     * @param  Collection<array-key, float>  $bobotPerKunci
     * @return array<array-key, float>
     */
    public static function keSeratus(Collection $bobotPerKunci): array
    {
        return self::keTarget($bobotPerKunci, 100.0);
    }

    /**
     * Sama seperti keSeratus(), tapi target totalnya bisa berupa angka
     * apa pun (mis. bobot suatu Asesmen yang bisa berupa desimal seperti
     * 7.5) — bekerja dalam satuan perseratus (hundredths) secara internal
     * supaya pembulatan tetap presisi 2 desimal, bukan dipaksa ke bilangan
     * bulat (yang tidak akan pernah pas totalnya bila target sendiri
     * berupa desimal).
     *
     * @param  Collection<array-key, float>  $bobotPerKunci
     * @return array<array-key, float>
     */
    public static function keTarget(Collection $bobotPerKunci, float $target): array
    {
        $total = (float) $bobotPerKunci->sum();

        if ($bobotPerKunci->isEmpty() || $total <= 0 || $target <= 0) {
            return [];
        }

        $targetHundredths = (int) round($target * 100);

        $dibulatkan = $bobotPerKunci
            ->map(fn (float $bobot): int => (int) round($bobot * $targetHundredths / $total))
            ->all();

        $selisih = $targetHundredths - array_sum($dibulatkan);

        if ($selisih !== 0) {
            $kunciTerbesar = $bobotPerKunci->sortDesc()->keys()->first();
            $dibulatkan[$kunciTerbesar] += $selisih;
        }

        return array_map(fn (int $hundredths): float => $hundredths / 100, $dibulatkan);
    }
}
