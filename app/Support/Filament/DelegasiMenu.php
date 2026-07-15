<?php

namespace App\Support\Filament;

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
}
