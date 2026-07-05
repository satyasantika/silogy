<?php

namespace App\Support\Filament;

/**
 * Menu kategori operasional akademik (Kurikulum, Mata Kuliah, Kelas,
 * Penilaian, Interaksi) didelegasikan ke Admin/Tim Kurikulum/Koordinator —
 * Super Admin fokus pada Institusi & Autentikasi sehingga menu-menu
 * tersebut disembunyikan darinya (akses via policy tetap sebagai pintu
 * darurat).
 */
final class DelegasiMenu
{
    public static function sembunyikanDariSuperAdmin(): bool
    {
        return auth()->user()?->hasRole('Super Admin') ?? false;
    }
}
