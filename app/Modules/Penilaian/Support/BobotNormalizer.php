<?php

namespace App\Modules\Penilaian\Support;

use Illuminate\Support\Collection;

/**
 * Membagi ulang sekumpulan bobot secara proporsional terhadap suatu target,
 * dibulatkan ke N desimal (default: satuan / 0 desimal), dengan sisa
 * pembulatan dikoreksi pada kunci berbobot terbesar, agar totalnya tepat
 * sama dengan target tersebut.
 */
class BobotNormalizer
{
    /**
     * @param  Collection<array-key, float>  $bobotPerKunci
     * @return array<array-key, float>
     */
    public static function keSeratus(Collection $bobotPerKunci, int $desimal = 0): array
    {
        return self::keTarget($bobotPerKunci, 100.0, $desimal);
    }

    /**
     * Sama seperti keSeratus(), tapi target totalnya bisa berupa angka
     * apa pun (mis. bobot Asesmen 7.5). Target ikut dibulatkan ke N desimal
     * terlebih dahulu, lalu bobot diredistribusi agar totalnya tepat.
     *
     * @param  Collection<array-key, float>  $bobotPerKunci
     * @return array<array-key, float>
     */
    public static function keTarget(Collection $bobotPerKunci, float $target, int $desimal = 0): array
    {
        $desimal = max(0, min(6, $desimal));
        $total = (float) $bobotPerKunci->sum();

        if ($bobotPerKunci->isEmpty() || $total <= 0 || $target <= 0) {
            return [];
        }

        $factor = 10 ** $desimal;
        $targetUnits = (int) round($target * $factor);

        if ($targetUnits <= 0) {
            return [];
        }

        $dibulatkan = $bobotPerKunci
            ->map(fn (float $bobot): int => (int) round($bobot * $targetUnits / $total))
            ->all();

        $selisih = $targetUnits - array_sum($dibulatkan);

        if ($selisih !== 0) {
            $kunciTerbesar = $bobotPerKunci->sortDesc()->keys()->first();
            $dibulatkan[$kunciTerbesar] += $selisih;
        }

        return array_map(
            fn (int $units): float => $units / $factor,
            $dibulatkan,
        );
    }

    /**
     * True bila total sudah sama target (dalam toleransi N desimal) dan
     * setiap bobot sudah tidak punya digit di luar N desimal.
     *
     * @param  Collection<array-key, float>  $bobotPerKunci
     */
    public static function sudahSesuai(Collection $bobotPerKunci, float $target, int $desimal = 0): bool
    {
        $desimal = max(0, min(6, $desimal));
        $factor = 10 ** $desimal;
        $epsilon = 1 / (2 * $factor);

        if (abs((float) $bobotPerKunci->sum() - $target) >= $epsilon) {
            return false;
        }

        return $bobotPerKunci->every(
            fn (float $bobot): bool => abs(($bobot * $factor) - round($bobot * $factor)) < 1e-6,
        );
    }
}
