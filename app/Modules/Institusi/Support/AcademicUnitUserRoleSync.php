<?php

namespace App\Modules\Institusi\Support;

use App\Models\Role;
use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnitUser;

/**
 * Sinkronkan role global "Pimpinan"/"Tim Kurikulum" berdasarkan status
 * penugasan unit akademik (academic_unit_users) milik seorang pengguna.
 *
 * Sengaja TIDAK memakai assignRole()/hasRole()/removeRole() bawaan Spatie:
 * User::roles() di-override untuk memfilter ke role aktif switcher, tapi
 * HANYA saat model target adalah auth()->user() sendiri. Karena
 * assignRole()/hasRole() membaca koleksi $this->roles yang kena filter itu,
 * memanggilnya pada diri sendiri (mis. Super Admin menugaskan dirinya
 * sendiri sebagai pimpinan unit yang sedang dibuka) bisa salah hitung role
 * yang sudah dimiliki lalu mencoba attach() duplikat -> QueryException.
 * syncWithoutDetaching()/detach() memakai pivot-query terpisah yang tidak
 * kena klausa filter itu sama sekali, jadi aman untuk target manapun.
 */
class AcademicUnitUserRoleSync
{
    private const ROLE_COLUMNS = [
        'Pimpinan' => 'status_pimpinan',
        'Tim Kurikulum' => 'status_tim_kurikulum',
    ];

    /**
     * @return array{granted: int, revoked: int}
     */
    public function syncForUser(string $userId): array
    {
        $user = User::query()->find($userId);

        if (! $user) {
            return ['granted' => 0, 'revoked' => 0];
        }

        $granted = 0;
        $revoked = 0;

        foreach (self::ROLE_COLUMNS as $roleName => $statusColumn) {
            $result = $this->syncRole($user, $roleName, $statusColumn);
            $granted += $result === 'granted' ? 1 : 0;
            $revoked += $result === 'revoked' ? 1 : 0;
        }

        return ['granted' => $granted, 'revoked' => $revoked];
    }

    /**
     * @return 'granted'|'revoked'|'unchanged'
     */
    private function syncRole(User $user, string $roleName, string $statusColumn): string
    {
        $role = Role::query()->where('name', $roleName)->first();

        if (! $role) {
            return 'unchanged';
        }

        $shouldHaveRole = AcademicUnitUser::query()
            ->where('user_id', $user->id)
            ->where($statusColumn, true)
            ->exists();

        if ($shouldHaveRole) {
            $changes = $user->roles()->syncWithoutDetaching([$role->id]);

            return $changes['attached'] !== [] ? 'granted' : 'unchanged';
        }

        $detachedCount = $user->roles()->detach($role->id);

        return $detachedCount > 0 ? 'revoked' : 'unchanged';
    }
}
