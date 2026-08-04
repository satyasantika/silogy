<?php

namespace App\Support\Filament;

use App\Models\User;
use App\Modules\Auth\Support\PeranUnitFormFields;
use Illuminate\Support\Facades\Auth;

/**
 * Menu kategori operasional akademik (Kurikulum, Mata Kuliah, Kelas,
 * Penilaian, Interaksi, AI Analisis, Laporan) didelegasikan ke
 * Admin/Tim Kurikulum/Koordinator — menu Super Admin dibatasi hanya
 * Dashboard, Peran, Unit Akademik, Pengguna, Mahasiswa, dan Log Aktivitas.
 * Menu-menu di luar itu disembunyikan DAN policy/canAccess() terkait juga
 * menolak Super Admin secara langsung (tidak ada lagi jalur darurat).
 */
final class DelegasiMenu
{
    public static function sembunyikanDariSuperAdmin(): bool
    {
        return auth()->user()?->hasRole('Super Admin') ?? false;
    }

    /**
     * Peran aktif (switcher) adalah Dosen Pengampu dan user tidak sedang
     * berperan Admin — dipakai untuk menyembunyikan nav + menolak canAccess
     * pipeline Koordinator MK (Mata Kuliah / CPMK / Sub-CPMK / Peserta).
     */
    public static function peranAktifDosenBukanAdmin(?User $user = null): bool
    {
        $user ??= Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        return PeranUnitFormFields::defaultRole($user) === 'Dosen Pengampu'
            && ! $user->hasRole('Admin');
    }
}
