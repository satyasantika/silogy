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
     * Unit kerja kurikulum user: unit tempat ia berstatus tim kurikulum,
     * ditambah — untuk role Admin — seluruh unit dalam jangkauan
     * penugasannya (unit pivot + descendant). Kategori Kurikulum,
     * Mata Kuliah, Penilaian, dan Interaksi didelegasikan ke keduanya.
     *
     * @return Collection<int, string>
     */
    public static function scopedTimKurikulumUnitIdsFor(User $user): Collection
    {
        if ($user->hasRole('Super Admin')) {
            return AcademicUnit::query()->pluck('id');
        }

        $unitIds = AcademicUnitUser::query()
            ->where('user_id', $user->id)
            ->where('status_tim_kurikulum', true)
            ->pluck('academic_unit_id');

        // Merge unit Admin hanya bila role Admin sedang aktif.
        if ($user->hasAnyRole(['Admin', 'Admin Program Studi'])) {
            $unitIds = $unitIds->merge(static::managedUnitIdsFor($user));
        }

        return $unitIds->unique()->values();
    }

    /**
     * Unit tempat user berstatus tim kurikulum pada pivot (tanpa merge Admin).
     *
     * @return Collection<int, string>
     */
    public static function timKurikulumPivotUnitIdsFor(User $user): Collection
    {
        if ($user->hasRole('Super Admin')) {
            return AcademicUnit::query()->pluck('id');
        }

        return AcademicUnitUser::query()
            ->where('user_id', $user->id)
            ->where('status_tim_kurikulum', true)
            ->pluck('academic_unit_id')
            ->unique()
            ->values();
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

    public static function userIsTimKurikulumOnUnit(User $user, AcademicUnit $unit): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        // Admin bekerja pada unit penugasannya beserta seluruh turunannya.
        if ($user->hasRole('Admin') && static::userHasPivotToUnitOrAncestor($user, $unit)) {
            return true;
        }

        return AcademicUnitUser::query()
            ->where('user_id', $user->id)
            ->where('academic_unit_id', $unit->id)
            ->where('status_tim_kurikulum', true)
            ->exists();
    }

    /**
     * Unit prodi tempat user boleh mengelola penawaran MK (`mk_units`).
     * Hanya penugasan langsung pada program studi — tim kurikulum
     * fakultas/universitas tidak termasuk; Admin prodi tetap termasuk.
     *
     * @return Collection<int, string>
     */
    public static function scopedMkUnitProdiIdsFor(User $user): Collection
    {
        if ($user->hasRole('Super Admin')) {
            return AcademicUnit::query()
                ->where('type', 'study_program')
                ->pluck('id');
        }

        $query = AcademicUnitUser::query()
            ->where('user_id', $user->id)
            ->whereHas('academicUnit', fn ($builder) => $builder->where('type', 'study_program'));

        if (! $user->hasRole('Admin')) {
            $query->where('status_tim_kurikulum', true);
        }

        return $query->pluck('academic_unit_id')->unique()->values();
    }

    /**
     * User punya penugasan langsung pada unit bertipe tertentu
     * (mis. 'study_program' untuk aturan kelas MK khusus level prodi).
     */
    public static function userHasPivotOnUnitType(User $user, string $type): bool
    {
        return AcademicUnitUser::query()
            ->where('user_id', $user->id)
            ->whereHas('academicUnit', fn ($query) => $query->where('type', $type))
            ->exists();
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
