<?php

namespace App\Modules\Penilaian\Support;

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Models\AcademicUnitUser;
use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kurikulum\Models\Kurikulum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Cakupan validator nilai dihitung dari kurikulum: pimpinan dan tim kurikulum
 * prodi pemilik kurikulum berhak otomatis, ditambah validator tambahan yang
 * ditunjuk pada kurikulum itu. Switch `validasi_nilai_aktif` mematikan semuanya.
 * Unit induk (fakultas) dan admin tidak otomatis berhak.
 */
final class ValidatorNilaiScope
{
    /**
     * Pengguna dengan penugasan pimpinan atau tim kurikulum langsung pada
     * prodi pemilik kurikulum (bukan unit induk).
     *
     * @return Collection<int, string>
     */
    public static function otomatisBerhakUserIds(Kurikulum $kurikulum): Collection
    {
        return AcademicUnitUser::query()
            ->where('academic_unit_id', $kurikulum->academic_unit_id)
            ->where(fn (Builder $query) => $query->where('status_pimpinan', true)->orWhere('status_tim_kurikulum', true))
            ->pluck('user_id')
            ->unique()
            ->values();
    }

    public static function berhakPadaKurikulum(User $user, Kurikulum $kurikulum): bool
    {
        if (! $kurikulum->validasi_nilai_aktif) {
            return false;
        }

        return self::otomatisBerhakUserIds($kurikulum)->contains($user->id)
            || $kurikulum->validatorTambahan()->whereKey($user->id)->exists();
    }

    /**
     * Kurikulum dengan switch aktif yang hak validasinya dimiliki pengguna.
     *
     * @return Builder<Kurikulum>
     */
    public static function kurikulumQuery(User $user): Builder
    {
        return Kurikulum::query()
            ->where('validasi_nilai_aktif', true)
            ->where(function (Builder $query) use ($user): void {
                $query->whereIn('academic_unit_id', function ($sub) use ($user): void {
                    $sub->select('academic_unit_id')
                        ->from('academic_unit_users')
                        ->where('user_id', $user->id)
                        ->where(fn ($w) => $w->where('status_pimpinan', true)->orWhere('status_tim_kurikulum', true));
                })->orWhereHas('validatorTambahan', fn (Builder $validator) => $validator->whereKey($user->id));
            });
    }

    /**
     * Cakupan ditentukan oleh kurikulum penawaran (mk_units.kurikulum_id);
     * kelas tanpa kurikulum tidak masuk cakupan siapa pun.
     *
     * @return Builder<KelasMk>
     */
    public static function kelasMkQuery(User $user): Builder
    {
        return KelasMk::query()->whereHas(
            'mkUnit',
            fn (Builder $query) => $query->whereIn(
                'kurikulum_id',
                self::kurikulumQuery($user)->select('kurikulum.id'),
            ),
        );
    }

    public static function punyaHakValidasi(User $user): bool
    {
        return self::kurikulumQuery($user)->exists();
    }

    /**
     * Jabatan pada penugasan yang paling dekat dengan unit (unit itu sendiri
     * lebih dulu, lalu induk terdekat); null bila tak ada.
     */
    public static function jabatanUntuk(User $validator, AcademicUnit $unit): ?string
    {
        $rantai = AcademicUnitScope::ancestorIdsIncludingSelf($unit);

        $terdekat = AcademicUnitUser::query()
            ->where('user_id', $validator->id)
            ->whereIn('academic_unit_id', $rantai)
            ->orderBy('id')
            ->get()
            ->sortBy(fn (AcademicUnitUser $pivot): int => (int) array_search($pivot->academic_unit_id, $rantai, true))
            ->first();

        return $terdekat?->jabatan;
    }
}
