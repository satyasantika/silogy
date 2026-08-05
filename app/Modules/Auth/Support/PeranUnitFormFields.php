<?php

namespace App\Modules\Auth\Support;

use App\Models\User;
use App\Modules\Auth\Filament\Pages\PilihPeranUnit;
use App\Modules\Institusi\Support\AcademicUnitTerpilih;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * Field & logika form "pilih peran & unit" dipakai bersama oleh halaman
 * PilihPeranUnit, modal ganti di kartu Selamat Datang, dan modal ganti
 * di menu pengguna — supaya tidak terduplikasi di tiga tempat.
 */
class PeranUnitFormFields
{
    /**
     * @return array<int, Component>
     */
    public static function schema(User $user): array
    {
        $roles = ActiveRole::ownedRoleNames($user);

        return [
            ToggleButtons::make('role')
                ->label('Peran')
                ->options(array_combine($roles, $roles) ?: [])
                ->icons(collect($roles)
                    ->mapWithKeys(fn (string $role): array => [$role => static::iconForRole($role)])
                    ->all())
                ->colors(collect($roles)
                    ->mapWithKeys(fn (string $role): array => [$role => static::colorForRole($role)])
                    ->all())
                ->columns(min(3, max(1, count($roles))))
                ->required()
                ->live()
                ->default(static::defaultRole($user)),

            Radio::make('unit_id')
                ->label('Unit akademik')
                ->options(fn (Get $get): array => AcademicUnitTerpilih::optionsForUnitIds(
                    static::unitIdsForRole($user, $get('role')),
                ))
                ->visible(fn (Get $get): bool => static::roleMembutuhkanPilihanUnit($user, $get('role')))
                ->required(fn (Get $get): bool => static::roleMembutuhkanPilihanUnit($user, $get('role')))
                ->default(static::defaultUnitId($user)),
        ];
    }

    public static function iconForRole(string $role): Heroicon
    {
        return match ($role) {
            'Super Admin' => Heroicon::OutlinedShieldCheck,
            'Admin' => Heroicon::OutlinedCog6Tooth,
            'Pimpinan' => Heroicon::OutlinedBuildingLibrary,
            'Tim Kurikulum' => Heroicon::OutlinedBookOpen,
            'Koordinator Mata Kuliah' => Heroicon::OutlinedClipboardDocumentList,
            'Dosen Pengampu' => Heroicon::OutlinedAcademicCap,
            'Auditor Mutu' => Heroicon::OutlinedMagnifyingGlassCircle,
            default => Heroicon::OutlinedUserCircle,
        };
    }

    public static function colorForRole(string $role): string
    {
        return match ($role) {
            'Super Admin' => 'danger',
            'Admin' => 'gray',
            'Pimpinan' => 'warning',
            'Tim Kurikulum' => 'info',
            'Koordinator Mata Kuliah' => 'success',
            'Dosen Pengampu' => 'primary',
            'Auditor Mutu' => 'danger',
            default => 'gray',
        };
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
     * Tim Kurikulum tidak memilih unit di gerbang/modal — konteks unit
     * mengikuti KurikulumTerpilih / unit sesi yang masih valid.
     */
    public static function roleMembutuhkanPilihanUnit(User $user, ?string $role): bool
    {
        if (blank($role) || $role === 'Tim Kurikulum') {
            return false;
        }

        return static::unitCountForRole($user, $role) > 1;
    }

    public static function unitIdUntukRole(User $user, ?string $role): ?string
    {
        $unitIds = static::unitIdsForRole($user, $role);

        if ($unitIds->isEmpty()) {
            return null;
        }

        $current = AcademicUnitTerpilih::currentId($user);

        if ($current !== null && $unitIds->contains($current)) {
            return $current;
        }

        return $unitIds->count() === 1 ? $unitIds->first() : null;
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
