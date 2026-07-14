<?php

namespace App\Modules\Auth\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Role aktif yang dipilih user multi-role lewat switcher di navigasi.
 *
 * Saat sebuah role aktif dipilih, relasi roles user yang sedang login
 * difilter ke role tersebut (lihat User::roles()) sehingga menu,
 * policy, dan permission mengikuti role yang dipilih. Tanpa pilihan
 * (null), seluruh role berlaku seperti biasa.
 */
class ActiveRole
{
    public const SESSION_KEY = 'silogy_active_role';

    /**
     * Role aktif tervalidasi milik user; null berarti semua role berlaku.
     *
     * Validasi sengaja tidak memakai relasi roles() untuk menghindari
     * rekursi (relasi tersebut difilter berdasarkan nilai ini).
     */
    public static function currentFor(User $user): ?string
    {
        $role = session()->get(self::SESSION_KEY);

        if (blank($role) || ! is_string($role)) {
            return null;
        }

        return self::userOwnsRole($user, $role) ? $role : null;
    }

    public static function set(?string $role): void
    {
        if (blank($role)) {
            session()->forget(self::SESSION_KEY);
            self::forgetAuthRoleRelationCache();

            return;
        }

        $user = auth()->user();

        if ($user instanceof User && self::userOwnsRole($user, $role)) {
            session()->put(self::SESSION_KEY, $role);
            self::forgetAuthRoleRelationCache();
        }
    }

    /**
     * Relasi roles() & cache permission Spatie harus dibuang setelah
     * role aktif berubah, supaya hasRole()/can() tidak memakai hasil
     * filter role sebelumnya pada instance user yang sama.
     */
    public static function forgetAuthRoleRelationCache(): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return;
        }

        $user->unsetRelation('roles');
        $user->unsetRelation('permissions');

        if (method_exists($user, 'forgetCachedPermissions')) {
            $user->forgetCachedPermissions();
        }
    }

    /**
     * Seluruh nama role milik user, tanpa terpengaruh filter role aktif.
     *
     * @return list<string>
     */
    public static function ownedRoleNames(User $user): array
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_uuid', $user->getKey())
            ->where('model_has_roles.model_type', $user->getMorphClass())
            ->orderBy('roles.name')
            ->pluck('roles.name')
            ->all();
    }

    /**
     * Cek role & permission milik user langsung dari pivot DB,
     * tanpa terpengaruh filter role aktif di relasi roles().
     */
    public static function userOwnsRoleWithPermission(User $user, string $role, string $permission): bool
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->join('role_has_permissions', 'role_has_permissions.role_id', '=', 'roles.id')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('model_has_roles.model_uuid', $user->getKey())
            ->where('model_has_roles.model_type', $user->getMorphClass())
            ->where('roles.name', $role)
            ->where('permissions.name', $permission)
            ->exists();
    }

    /**
     * Permission milik user (langsung atau lewat role mana pun),
     * tanpa terpengaruh filter role aktif switcher.
     */
    public static function userOwnsPermission(User $user, string $permission): bool
    {
        $morph = $user->getMorphClass();
        $uuid = $user->getKey();

        if (DB::table('model_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
            ->where('model_has_permissions.model_uuid', $uuid)
            ->where('model_has_permissions.model_type', $morph)
            ->where('permissions.name', $permission)
            ->exists()) {
            return true;
        }

        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->join('role_has_permissions', 'role_has_permissions.role_id', '=', 'roles.id')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('model_has_roles.model_uuid', $uuid)
            ->where('model_has_roles.model_type', $morph)
            ->where('permissions.name', $permission)
            ->exists();
    }

    public static function userOwnsRoleName(User $user, string $role): bool
    {
        return in_array($role, self::ownedRoleNames($user), true);
    }

    protected static function userOwnsRole(User $user, string $role): bool
    {
        return self::userOwnsRoleName($user, $role);
    }
}
