<?php

namespace App\Modules\Auth\Support;

use App\Models\User;
use App\Modules\Auth\Filament\Pages\PilihPeranUnit;
use App\Modules\Institusi\Support\AcademicUnitTerpilih;
use Filament\Forms\Components\Radio;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Collection;

/**
 * Field & logika form "pilih peran & unit" dipakai bersama oleh halaman
 * PilihPeranUnit, modal ganti di kartu Selamat Datang, dan modal ganti
 * di menu pengguna — supaya tidak terduplikasi di tiga tempat.
 */
class PeranUnitFormFields
{
    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function schema(User $user): array
    {
        $roles = ActiveRole::ownedRoleNames($user);

        return [
            Radio::make('role')
                ->label('Peran')
                ->options(array_combine($roles, $roles))
                ->required()
                ->live()
                ->default(static::defaultRole($user)),

            Radio::make('unit_id')
                ->label('Unit')
                ->options(fn (Get $get): array => AcademicUnitTerpilih::optionsForUnitIds(
                    static::unitIdsForRole($user, $get('role')),
                ))
                ->visible(fn (Get $get): bool => static::unitCountForRole($user, $get('role')) > 1)
                ->required(fn (Get $get): bool => static::unitCountForRole($user, $get('role')) > 1)
                ->default(static::defaultUnitId($user)),
        ];
    }

    public static function defaultRole(User $user): ?string
    {
        $roles = ActiveRole::ownedRoleNames($user);

        return ActiveRole::currentFor($user) ?? ($roles[0] ?? null);
    }

    public static function defaultUnitId(User $user): ?string
    {
        $role = static::defaultRole($user);

        if ($role === null) {
            return null;
        }

        $unitId = AcademicUnitTerpilih::currentId($user);

        return ($unitId !== null && static::unitIdsForRole($user, $role)->contains($unitId))
            ? $unitId
            : null;
    }

    /**
     * @return Collection<int, string>
     */
    public static function unitIdsForRole(User $user, ?string $role): Collection
    {
        return blank($role) ? collect() : AcademicUnitTerpilih::scopedUnitIdsForRole($user, $role);
    }

    public static function unitCountForRole(User $user, ?string $role): int
    {
        return static::unitIdsForRole($user, $role)->count();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function apply(array $data): void
    {
        ActiveRole::set($data['role']);
        AcademicUnitTerpilih::set($data['unit_id'] ?? null);
    }

    /**
     * Dipanggil dari closure `Impersonate::redirectTo()` (UserResource,
     * EditUser) SETELAH ImpersonateManager::take() berjalan — pada titik
     * itu auth()->user() sudah user yang di-impersonate. Membersihkan sisa
     * pilihan peran/unit sesi admin sebelumnya, lalu mengarahkan ke
     * gerbang Pilih Peran & Unit bila target multi-role, atau ke dashboard
     * bila tidak.
     */
    public static function redirectUrlAfterImpersonateStart(): string
    {
        session()->forget(ActiveRole::SESSION_KEY);
        session()->forget(AcademicUnitTerpilih::SESSION_KEY);

        $target = auth()->user();

        if ($target instanceof User && count(ActiveRole::ownedRoleNames($target)) > 1) {
            return PilihPeranUnit::getUrl();
        }

        return route('filament.admin.pages.dashboard');
    }
}
