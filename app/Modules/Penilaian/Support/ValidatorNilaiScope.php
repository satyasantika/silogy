<?php

namespace App\Modules\Penilaian\Support;

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnitUser;
use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\Kelas\Models\KelasMk;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Cakupan validator nilai: unit yang penugasannya bertanda validator beserta
 * turunannya. Penugasan tanpa penanda tidak memberi cakupan.
 */
final class ValidatorNilaiScope
{
    /**
     * @return Collection<int, string>
     */
    public static function unitIdsFor(User $user): Collection
    {
        return AcademicUnitUser::query()
            ->where('user_id', $user->id)
            ->where('status_validator_nilai', true)
            ->pluck('academic_unit_id')
            ->flatMap(fn (string $unitId): Collection => AcademicUnitScope::descendantIdsIncludingSelf($unitId))
            ->unique()
            ->values();
    }

    /**
     * Cakupan ditentukan oleh unit penawaran (mk_units), bukan unit pemilik MK.
     *
     * @return Builder<KelasMk>
     */
    public static function kelasMkQuery(User $user): Builder
    {
        return KelasMk::query()->whereHas(
            'mkUnit',
            fn (Builder $query) => $query->whereIn('academic_unit_id', self::unitIdsFor($user)->all()),
        );
    }
}
