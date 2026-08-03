<?php

namespace App\Support\Filament;

use App\Models\User;
use App\Modules\Auth\Support\PeranUnitFormFields;

/**
 * Peran operasional (Tim Kurikulum, Koordinator Mata Kuliah, Dosen
 * Pengampu) hanya punya sedikit menu — dikelompokkan ke kategori
 * (Kurikulum, Mata Kuliah, dst.) cuma menambah klik tanpa manfaat
 * navigasi. Untuk peran-peran ini seluruh menu digabung rata dengan
 * Dasbor (tanpa kategori); peran lain (Admin, Pimpinan, dst.) tetap
 * terkelompok seperti biasa.
 */
final class NavigationGroupPeran
{
    private const PERAN_TANPA_KATEGORI = [
        'Tim Kurikulum',
        'Koordinator Mata Kuliah',
        'Dosen Pengampu',
    ];

    public static function resolve(string $grup): ?string
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return $grup;
        }

        $peranAktif = PeranUnitFormFields::defaultRole($user);

        return in_array($peranAktif, self::PERAN_TANPA_KATEGORI, true) ? null : $grup;
    }
}
