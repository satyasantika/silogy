<?php

namespace App\Modules\Kalkulasi\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Pemangkasan berkunci eksplisit untuk tabel hasil turunan: baris pada cakupan
 * sempit yang kuncinya tidak lagi dihasilkan dihapus, sedangkan baris yang
 * masih dihasilkan tidak disentuh (id dan created_at tetap).
 */
trait PangkasHasilTurunan
{
    /**
     * @param  Collection<string, covariant Model>  $existing  baris cakupan saat ini, kunci komposit => model
     * @param  list<string>  $kunciDihasilkan  kunci komposit baris yang dihasilkan kali ini
     */
    protected function pangkasBarisBasi(string $modelClass, Collection $existing, array $kunciDihasilkan): void
    {
        // filter() dengan kunci koleksi: Eloquent\Collection::except() mencocokkan
        // kunci utama model, bukan kunci koleksi, jadi tidak dipakai di sini.
        $dihasilkan = array_flip($kunciDihasilkan);

        $idBasi = $existing
            ->filter(fn (Model $baris, string $kunci): bool => ! isset($dihasilkan[$kunci]))
            ->map(fn (Model $baris): mixed => $baris->getKey())
            ->values();

        foreach ($idBasi->chunk(500) as $potongan) {
            $modelClass::query()->whereKey($potongan->all())->delete();
        }
    }
}
