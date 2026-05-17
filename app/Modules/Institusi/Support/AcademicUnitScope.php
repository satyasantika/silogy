<?php

namespace App\Modules\Institusi\Support;

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Models\AcademicUnitUser;
use Illuminate\Support\Collection;

class AcademicUnitScope
{
    /**
     * @return list<string>
     */
    public static function userAssignedUnitIds(User $user): array
    {
        return AcademicUnitUser::query()
            ->where('user_id', $user->id)
            ->pluck('academic_unit_id')
            ->all();
    }

    public static function userHasAnyPivot(User $user): bool
    {
        return AcademicUnitUser::query()
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * Rantai unit dari diri sendiri ke atas (termasuk unit itu sendiri).
     *
     * @return list<string>
     */
    public static function ancestorIdsIncludingSelf(AcademicUnit $unit): array
    {
        $ids = [];
        $current = $unit;

        while ($current) {
            $ids[] = $current->id;
            $current = $current->parent;
        }

        return $ids;
    }

    /**
     * Semua keturunan unit (termasuk unit itu sendiri).
     *
     * @return Collection<int, string>
     */
    public static function descendantIdsIncludingSelf(string $unitId): Collection
    {
        $ids = collect([$unitId]);
        $children = AcademicUnit::query()
            ->where('parent_id', $unitId)
            ->pluck('id');

        foreach ($children as $childId) {
            $ids = $ids->merge(static::descendantIdsIncludingSelf($childId));
        }

        return $ids->unique()->values();
    }

    /**
     * Pivot user berada pada unit target atau salah satu ancestornya (untuk kelola).
     */
    public static function userHasPivotToUnitOrAncestor(User $user, AcademicUnit $unit): bool
    {
        $assignedIds = static::userAssignedUnitIds($user);

        if ($assignedIds === []) {
            return false;
        }

        return (bool) array_intersect(
            $assignedIds,
            static::ancestorIdsIncludingSelf($unit),
        );
    }

    /**
     * User boleh melihat unit yang sama dengan penugasan atau ancestornya (lihat ke atas hierarki).
     */
    public static function userCanViewUnit(User $user, AcademicUnit $unit): bool
    {
        foreach (static::userAssignedUnitIds($user) as $assignedId) {
            $assigned = AcademicUnit::find($assignedId);

            if ($assigned && in_array($unit->id, static::ancestorIdsIncludingSelf($assigned), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Collection<int, string>
     */
    public static function managedUnitIdsFor(User $user): Collection
    {
        $ids = collect();

        foreach (static::userAssignedUnitIds($user) as $unitId) {
            $ids = $ids->merge(static::descendantIdsIncludingSelf($unitId));
        }

        return $ids->unique()->values();
    }

    public static function userSharesPivotScope(User $admin, User $target): bool
    {
        $scopeIds = static::managedUnitIdsFor($admin);

        if ($scopeIds->isEmpty()) {
            return false;
        }

        return AcademicUnitUser::query()
            ->where('user_id', $target->id)
            ->whereIn('academic_unit_id', $scopeIds)
            ->exists();
    }

    /**
     * ID program studi yang boleh diakses user (descendant dari pivot).
     *
     * @return Collection<int, string>
     */
    public static function scopedStudyProgramIdsFor(User $user): Collection
    {
        if ($user->hasRole(['Super Admin', 'Auditor Mutu'])) {
            return AcademicUnit::query()
                ->where('type', 'study_program')
                ->pluck('id');
        }

        $managedIds = static::managedUnitIdsFor($user);

        if ($managedIds->isEmpty()) {
            return collect();
        }

        return AcademicUnit::query()
            ->where('type', 'study_program')
            ->whereIn('id', $managedIds)
            ->pluck('id');
    }
}
