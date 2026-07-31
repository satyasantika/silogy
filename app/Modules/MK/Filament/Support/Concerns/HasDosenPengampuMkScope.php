<?php

namespace App\Modules\MK\Filament\Support\Concerns;

use App\Models\User;
use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\MK\Models\Mk;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

trait HasDosenPengampuMkScope
{
    /**
     * MK yang benar-benar diampu user lewat kelas_mk.dosen_pengampu_id.
     *
     * Tidak membatasi tipe unit penawaran (prodi/fakultas/jurusan/universitas)
     * — selama user ditugaskan sebagai dosen pengampu pada kelas penawaran
     * tersebut, MK-nya masuk cakupan dashboard dan penilaian.
     *
     * @return Collection<int, string>
     */
    public static function scopedDiampuMkIds(User $user): Collection
    {
        if ($user->hasRole(['Super Admin', 'Auditor Mutu'])) {
            return Mk::query()->pluck('id');
        }

        // Admin: seluruh MK pada unit dalam jangkauan penugasannya, plus MK
        // yang ia ampu lewat kelas di luar jangkauan tersebut.
        if ($user->hasRole('Admin')) {
            $dariUnit = Mk::query()
                ->whereIn('academic_unit_id', AcademicUnitScope::managedUnitIdsFor($user))
                ->pluck('id');

            $dariKelas = static::mkIdsDariKelasDiampu($user);

            return $dariUnit->merge($dariKelas)->unique()->values();
        }

        return static::mkIdsDariKelasDiampu($user);
    }

    /**
     * @return Collection<int, string>
     */
    public static function scopedDiampuKelasMkIds(User $user): Collection
    {
        if ($user->hasRole(['Super Admin', 'Auditor Mutu'])) {
            return KelasMk::query()->pluck('id');
        }

        if ($user->hasRole('Admin')) {
            $dariUnit = KelasMk::query()
                ->whereHas('mkUnit', fn (Builder $query): Builder => $query->whereIn(
                    'academic_unit_id',
                    AcademicUnitScope::managedUnitIdsFor($user),
                ))
                ->pluck('id');

            $dariPenugasan = KelasMk::query()
                ->where('dosen_pengampu_id', $user->id)
                ->pluck('id');

            return $dariUnit->merge($dariPenugasan)->unique()->values();
        }

        return KelasMk::query()
            ->where('dosen_pengampu_id', $user->id)
            ->pluck('id');
    }

    /**
     * Distinct mk_id dari kelas yang ditugaskan ke user sebagai dosen pengampu,
     * lintas unit penawaran (tidak difilter study_program saja).
     *
     * @return Collection<int, string>
     */
    protected static function mkIdsDariKelasDiampu(User $user): Collection
    {
        return Mk::query()
            ->whereHas(
                'mkUnits.kelasMks',
                fn (Builder $kelasQuery): Builder => $kelasQuery->where('dosen_pengampu_id', $user->id),
            )
            ->pluck('id');
    }
}
