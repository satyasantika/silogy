<?php

namespace App\Modules\Kelas\Filament\Support\Concerns;

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\MK\Models\MkUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

trait HasKelasMkUnitScope
{
    /**
     * Unit yang boleh diakses untuk kelola kelas (pivot + keturunan).
     *
     * @return Collection<int, string>
     */
    public static function scopedAccessibleUnitIds(): Collection
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return collect();
        }

        if ($user->hasRole(['Super Admin', 'Auditor Mutu'])) {
            return AcademicUnit::query()->pluck('id');
        }

        return AcademicUnitScope::managedUnitIdsFor($user);
    }

    /**
     * @return array<string, string>
     */
    public static function mkUnitOptions(): array
    {
        $unitIds = static::scopedAccessibleUnitIds();

        if ($unitIds->isEmpty()) {
            return [];
        }

        return MkUnit::query()
            ->with('mk')
            ->whereIn('academic_unit_id', $unitIds)
            ->where('is_active', true)
            ->orderBy('kode')
            ->get()
            ->mapWithKeys(fn (MkUnit $mkUnit): array => [
                $mkUnit->id => static::formatMkUnitLabel($mkUnit),
            ])
            ->all();
    }

    public static function formatMkUnitLabel(MkUnit $mkUnit): string
    {
        $mkUnit->loadMissing('mk');

        return sprintf('%s – %s', $mkUnit->mk?->nama ?? '—', $mkUnit->kode);
    }

    /**
     * @return array<string, string>
     */
    public static function usersByRoleOptions(string $roleName, ?string $academicUnitId = null): array
    {
        $query = User::query()
            ->role($roleName)
            ->orderBy('full_name');

        if ($academicUnitId !== null) {
            $query->whereHas(
                'academicUnitUsers',
                fn (Builder $pivotQuery): Builder => $pivotQuery->where('academic_unit_id', $academicUnitId),
            );
        }

        return $query
            ->pluck('full_name', 'id')
            ->all();
    }

    public static function academicUnitIdForMkUnit(?string $mkUnitId): ?string
    {
        if ($mkUnitId === null) {
            return null;
        }

        return MkUnit::query()->whereKey($mkUnitId)->value('academic_unit_id');
    }
}
