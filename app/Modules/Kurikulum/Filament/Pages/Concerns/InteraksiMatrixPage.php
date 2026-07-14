<?php

namespace App\Modules\Kurikulum\Filament\Pages\Concerns;

use App\Models\User;
use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Support\Filament\DelegasiMenu;

/**
 * Dasar halaman matriks interaksi (grup menu Interaksi): semua matriks
 * beroperasi atas kurikulum terpilih dan hanya untuk tim kurikulum
 * yang ber-scope pada unit kurikulum tersebut (atau Super Admin).
 */
trait InteraksiMatrixPage
{
    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->hasRole('Super Admin')) {
            return true;
        }

        // Matriks interaksi CPL/BoK/MK adalah ranah Tim Kurikulum/Admin —
        // jangan tampil hanya karena status_tim_kurikulum pada pivot
        // saat peran aktif adalah Dosen/Koordinator.
        if (! $user->hasAnyRole(['Tim Kurikulum', 'Admin', 'Admin Program Studi'])) {
            return false;
        }

        $kurikulum = KurikulumTerpilih::current();

        return $kurikulum !== null
            && $kurikulum->academicUnit !== null
            && AcademicUnitScope::userIsTimKurikulumOnUnit($user, $kurikulum->academicUnit);
    }

    public static function shouldRegisterNavigation(): bool
    {
        if (DelegasiMenu::sembunyikanDariSuperAdmin()) {
            return false;
        }

        return static::canAccess() && KurikulumTerpilih::current() !== null;
    }

    public function getKurikulum(): ?Kurikulum
    {
        return KurikulumTerpilih::current();
    }
}
