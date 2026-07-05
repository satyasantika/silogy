<?php

namespace App\Modules\MK\Filament\Support\Concerns;

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\MK\Models\Mk;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

trait HasKoordinatorMkScope
{
    /**
     * MK yang user koordinasikan: lewat penetapan tim kurikulum pada
     * mk.koordinator_mk_id (berlaku sebelum kelas ada) maupun lewat
     * kelas_mk.koordinator_mk_id.
     *
     * @return Collection<int, string>
     */
    public static function scopedKoordinatorMkIds(User $user): Collection
    {
        if ($user->hasRole(['Super Admin', 'Auditor Mutu'])) {
            return Mk::query()->pluck('id');
        }

        // Admin: seluruh MK pada unit dalam jangkauan penugasannya.
        if ($user->hasRole('Admin')) {
            return Mk::query()
                ->whereIn('academic_unit_id', AcademicUnitScope::managedUnitIdsFor($user))
                ->pluck('id');
        }

        $viaMk = Mk::query()
            ->where('koordinator_mk_id', $user->id)
            ->pluck('id');

        $viaKelas = KelasMk::query()
            ->where('koordinator_mk_id', $user->id)
            ->whereHas('mkUnit')
            ->with('mkUnit')
            ->get()
            ->pluck('mkUnit.mk_id')
            ->filter();

        return $viaMk->merge($viaKelas)->unique()->values();
    }

    /**
     * @return Collection<int, string>
     */
    public static function scopedKoordinatorKelasMkIds(User $user): Collection
    {
        if ($user->hasRole(['Super Admin', 'Auditor Mutu'])) {
            return KelasMk::query()->pluck('id');
        }

        if ($user->hasRole('Admin')) {
            return KelasMk::query()
                ->whereHas('mkUnit', fn ($query) => $query->whereIn(
                    'academic_unit_id',
                    AcademicUnitScope::managedUnitIdsFor($user),
                ))
                ->pluck('id');
        }

        return KelasMk::query()
            ->where(function (Builder $scoped) use ($user): void {
                $scoped->where('koordinator_mk_id', $user->id)
                    ->orWhereHas(
                        'mkUnit.mk',
                        fn (Builder $mkQuery): Builder => $mkQuery->where('koordinator_mk_id', $user->id),
                    );
            })
            ->pluck('id');
    }

    /**
     * @return array<string, string>
     */
    public static function scopedKoordinatorMkOptions(): array
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return [];
        }

        $mkIds = static::scopedKoordinatorMkIds($user);

        if ($mkIds->isEmpty()) {
            return [];
        }

        return Mk::query()
            ->whereIn('id', $mkIds)
            ->orderBy('nama')
            ->pluck('nama', 'id')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function scopedKoordinatorKelasMkOptions(): array
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return [];
        }

        $query = KelasMk::query()
            ->with(['mkUnit.mk', 'semester'])
            ->orderBy('kode_kelas');

        if (! $user->hasRole(['Super Admin', 'Auditor Mutu'])) {
            $query->where('koordinator_mk_id', $user->id);
        }

        return $query
            ->get()
            ->mapWithKeys(fn (KelasMk $kelas): array => [
                $kelas->id => sprintf(
                    '%s – Kelas %s (%s)',
                    $kelas->mkUnit?->mk?->nama ?? '—',
                    $kelas->kode_kelas,
                    $kelas->semester?->kode ?? '—',
                ),
            ])
            ->all();
    }

    public static function userCanManageMkAsKoordinator(User $user, string $mkId): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        return static::scopedKoordinatorMkIds($user)->contains($mkId);
    }

    public static function userCanManageKelasAsKoordinator(User $user, KelasMk $kelasMk): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        return $kelasMk->koordinator_mk_id === $user->id;
    }

    public static function userCanManageKelasByAdminUnit(User $user, KelasMk $kelasMk): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        if (! $user->hasRole('Admin')) {
            return false;
        }

        $kelasMk->loadMissing('mkUnit.academicUnit');
        $unit = $kelasMk->mkUnit?->academicUnit;

        return $unit instanceof AcademicUnit
            && AcademicUnitScope::userHasPivotToUnitOrAncestor($user, $unit);
    }
}
