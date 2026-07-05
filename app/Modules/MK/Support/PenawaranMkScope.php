<?php

namespace App\Modules\MK\Support;

use App\Models\User;
use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\Kalender\Support\SemesterTerpilih;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Scope penawaran MK (`mk_units`) menurut akun login — acuan filter Kelas MK
 * dan daftar read-only Penawaran MK untuk Koordinator Mata Kuliah.
 */
class PenawaranMkScope
{
    public static function isKoordinatorMkOnly(User $user): bool
    {
        return $user->hasRole('Koordinator Mata Kuliah')
            && ! $user->hasRole(['Super Admin', 'Admin', 'Admin Program Studi']);
    }

    public static function isDosenPengampuOnly(User $user): bool
    {
        return $user->hasRole('Dosen Pengampu')
            && $user->can('kelola_kelas')
            && ! $user->hasRole('Admin');
    }

    public static function filterBerdasarkanPenawaranAkun(User $user): bool
    {
        return self::isKoordinatorMkOnly($user) || self::isDosenPengampuOnly($user);
    }

    /**
     * @return Builder<MkUnit>
     */
    public static function penawaranMkQuery(?User $user = null): Builder
    {
        $user ??= Auth::user();
        $query = MkUnit::query()->where('is_active', true);

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRole(['Super Admin', 'Auditor Mutu'])) {
            return $query;
        }

        if (self::isKoordinatorMkOnly($user)) {
            return $query->whereHas(
                'mk',
                fn (Builder $mkQuery): Builder => $mkQuery->where('koordinator_mk_id', $user->id),
            );
        }

        if (self::isDosenPengampuOnly($user)) {
            return $query->whereHas(
                'kelasMks',
                fn (Builder $kelasQuery): Builder => $kelasQuery->where('dosen_pengampu_id', $user->id),
            );
        }

        $unitIds = AcademicUnitScope::managedUnitIdsFor($user);

        if ($unitIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('academic_unit_id', $unitIds);
    }

    /**
     * @return Collection<int, string>
     */
    public static function unitIdsDariPenawaran(?User $user = null): Collection
    {
        return self::penawaranMkQuery($user)
            ->distinct()
            ->pluck('academic_unit_id')
            ->filter()
            ->values();
    }

    /**
     * @return array<string, string>
     */
    public static function mkFilterOptions(?User $user = null): array
    {
        $mkIds = self::penawaranMkQuery($user)
            ->distinct()
            ->pluck('mk_id')
            ->filter()
            ->values();

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
     * Semester operasional yang punya kelas MK terkait penawaran akun ini.
     *
     * @return array<string, string>
     */
    public static function semesterFilterOptions(?User $user = null): array
    {
        $user ??= Auth::user();
        $penawaranIds = self::penawaranMkQuery($user)->pluck('id');

        if ($penawaranIds->isEmpty()) {
            return [];
        }

        $kelasQuery = KelasMk::query()->whereIn('mk_unit_id', $penawaranIds);

        if ($user instanceof User && self::isKoordinatorMkOnly($user)) {
            $kelasQuery->where('koordinator_mk_id', $user->id);
        } elseif ($user instanceof User && self::isDosenPengampuOnly($user)) {
            $kelasQuery->where('dosen_pengampu_id', $user->id);
        }

        $semesterIds = $kelasQuery->distinct()->pluck('semester_id')->filter();

        if ($semesterIds->isEmpty()) {
            return [];
        }

        return Semester::query()
            ->whereIn('id', $semesterIds)
            ->orderBy('kode')
            ->pluck('nama', 'id')
            ->all();
    }

    /**
     * Query kelas MK untuk satu mata kuliah — scope sama dengan halaman Kelas MK.
     *
     * @return Builder<KelasMk>
     */
    public static function kelasMkQueryUntukMk(string $mkId, ?User $user = null): Builder
    {
        $user ??= Auth::user();

        $query = KelasMk::query()->whereHas(
            'mkUnit',
            fn (Builder $mkUnitQuery): Builder => $mkUnitQuery->where('mk_id', $mkId),
        );

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRole(['Super Admin', 'Auditor Mutu'])) {
            return $query;
        }

        if ($user->hasRole('Dosen Pengampu') && $user->can('kelola_kelas') && ! $user->hasRole('Admin')) {
            return $query->where('dosen_pengampu_id', $user->id);
        }

        if (self::isKoordinatorMkOnly($user)) {
            return $query->where(function (Builder $scoped) use ($user, $mkId): void {
                $scoped->where('koordinator_mk_id', $user->id)
                    ->orWhereHas(
                        'mkUnit.mk',
                        fn (Builder $mkQuery): Builder => $mkQuery
                            ->where('id', $mkId)
                            ->where('koordinator_mk_id', $user->id),
                    );
            });
        }

        $penawaranIds = self::penawaranMkQuery($user)
            ->where('mk_id', $mkId)
            ->pluck('id');

        if ($penawaranIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('mk_unit_id', $penawaranIds);
    }

    /**
     * Semester dari tabel semesters (urut kode).
     *
     * @return array<string, string>
     */
    public static function semesterOptionsUntukMk(string $mkId, ?User $user = null): array
    {
        unset($mkId);

        if (! SemesterTerpilih::berlakuUntukUser($user)) {
            return [];
        }

        return SemesterTerpilih::optionsSemua();
    }

    /**
     * Default semester: aktif bila ada, selain itu kode terbaru.
     */
    public static function semesterDefaultUntukMk(string $mkId, ?User $user = null): ?string
    {
        unset($mkId);

        if (! SemesterTerpilih::berlakuUntukUser($user)) {
            return null;
        }

        return SemesterTerpilih::defaultId();
    }

    /**
     * Validasi semester impor massal terhadap tabel semesters.
     *
     * @return array{status: string, keterangan: string}|null null bila valid
     */
    public static function validasiSemesterUntukMkImpor(string $mkId, string $semesterId, ?User $user = null): ?array
    {
        unset($mkId, $user);

        return SemesterTerpilih::validasiUntukImpor($semesterId);
    }

    /**
     * Kelas MK pada MK dan semester tertentu (scope akun, sumber tabel kelas_mk).
     *
     * @return Collection<int, KelasMk>
     */
    public static function kelasMkUntukMkSemester(string $mkId, string $semesterId, ?User $user = null): Collection
    {
        return self::kelasMkQueryUntukMk($mkId, $user)
            ->where('semester_id', $semesterId)
            ->get();
    }
}
