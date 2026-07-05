<?php

namespace App\Modules\MK\Filament\Pages\Concerns;

use App\Models\User;
use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Filament\Resources\MataKuliahKoordinatorResource;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Support\MkTerpilih;
use App\Modules\MK\Support\PenawaranMkScope;
use App\Support\Filament\DelegasiMenu;

/**
 * Dasar halaman matriks interaksi koordinator MK (CPL↔CPMK, Sub-CPMK↔Asesmen)
 * atas kurikulum dan mata kuliah terpilih.
 */
trait InteraksiKoordinatorMatrixPage
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

        $kurikulum = KurikulumTerpilih::current();

        if (! $kurikulum instanceof Kurikulum || $kurikulum->academicUnit === null) {
            return false;
        }

        if (PenawaranMkScope::isKoordinatorMkOnly($user)) {
            return $user->can('kelola_cpmk')
                && MkTerpilih::current() !== null
                && PenawaranMkScope::penawaranMkQuery($user)
                    ->where('academic_unit_id', $kurikulum->academic_unit_id)
                    ->exists();
        }

        return AcademicUnitScope::userIsTimKurikulumOnUnit($user, $kurikulum->academicUnit)
            && ($user->can('kelola_cpmk') || $user->can('kelola_komponen_penilaian'));
    }

    public static function shouldRegisterNavigation(): bool
    {
        if (DelegasiMenu::sembunyikanDariSuperAdmin()) {
            return false;
        }

        $user = auth()->user();

        if ($user instanceof User && PenawaranMkScope::isKoordinatorMkOnly($user)) {
            return static::canAccess()
                && KurikulumTerpilih::current() !== null
                && MkTerpilih::current() !== null;
        }

        return static::canAccess() && KurikulumTerpilih::current() !== null;
    }

    public function getKurikulum(): ?Kurikulum
    {
        return KurikulumTerpilih::current();
    }

    public function getMkTerpilih(): ?Mk
    {
        return MkTerpilih::current();
    }

    /**
     * @return array<string, mixed>
     */
    protected function mkTerpilihViewData(): array
    {
        $mk = $this->getMkTerpilih();
        $kurikulum = $this->getKurikulum();
        $gantiUrl = MataKuliahKoordinatorResource::getUrl('index');

        return [
            'mkTerpilih' => $mk,
            'mkTerpilihLabel' => $mk instanceof Mk ? MkTerpilih::label($mk, $kurikulum) : null,
            'gantiMkUrl' => $gantiUrl,
        ];
    }
}
