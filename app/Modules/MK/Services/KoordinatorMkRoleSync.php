<?php

namespace App\Modules\MK\Services;

use App\Models\Role;
use App\Models\User;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\MK\Models\Mk;
use Spatie\Permission\PermissionRegistrar;

/**
 * Saat dosen pengampu ditetapkan sebagai koordinator pada
 * mk.koordinator_mk_id, role global "Koordinator Mata Kuliah" dipastikan
 * ada. Role dicabut hanya bila user TIDAK lagi menjadi koordinator pada
 * MK mana pun (lintas kurikulum) maupun kelas_mk mana pun.
 *
 * Memakai syncWithoutDetaching()/detach() (bukan assignRole) agar aman
 * terhadap override User::roles() yang memfilter role aktif switcher.
 */
class KoordinatorMkRoleSync
{
    public const ROLE = 'Koordinator Mata Kuliah';

    public function pastikanRole(?string $userId): void
    {
        if (blank($userId)) {
            return;
        }

        $user = User::query()->find($userId);
        $role = Role::query()->where('name', self::ROLE)->first();

        if ($user === null || $role === null) {
            return;
        }

        $changes = $user->roles()->syncWithoutDetaching([$role->id]);

        if ($changes['attached'] !== []) {
            $this->lupaCacheIzin();
        }
    }

    public function cabutJikaTidakAdaPenugasan(?string $userId): void
    {
        if (blank($userId)) {
            return;
        }

        if ($this->masihKoordinator($userId)) {
            return;
        }

        $role = Role::query()->where('name', self::ROLE)->first();
        $user = User::query()->find($userId);

        if ($role === null || $user === null) {
            return;
        }

        $detached = $user->roles()->detach($role->id);

        if ($detached > 0) {
            $this->lupaCacheIzin();
        }
    }

    public function sinkronkanSetelahPerubahan(?string $baru, ?string $lama): void
    {
        if (filled($baru)) {
            $this->pastikanRole($baru);
        }

        if (filled($lama) && $lama !== $baru) {
            $this->cabutJikaTidakAdaPenugasan($lama);
        }
    }

    public function masihKoordinator(string $userId): bool
    {
        return Mk::query()->where('koordinator_mk_id', $userId)->exists()
            || KelasMk::query()->where('koordinator_mk_id', $userId)->exists();
    }

    private function lupaCacheIzin(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
