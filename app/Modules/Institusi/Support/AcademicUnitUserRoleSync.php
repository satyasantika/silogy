<?php

namespace App\Modules\Institusi\Support;

use App\Models\Role;
use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnitUser;
use App\Modules\Penilaian\Support\ValidatorNilaiScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sinkronkan role global "Pimpinan"/"Tim Kurikulum" berdasarkan status
 * penugasan unit akademik (academic_unit_users) milik seorang pengguna,
 * dan "Validator Nilai" berdasarkan hak validasi per kurikulum
 * (ValidatorNilaiScope::punyaHakValidasi).
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
     * Id pengguna yang perannya perlu disinkronkan: penugasan unit,
     * validator tambahan kurikulum, dan pemegang role Validator Nilai.
     * Dibaca langsung dari pivot (tanpa scope model).
     *
     * @return Collection<int, string>
     */
    public function userIdsTerkait(): Collection
    {
        $ids = DB::table('academic_unit_users')->pluck('user_id')
            ->merge(DB::table('kurikulum_validator')->pluck('user_id'));

        $roleId = DB::table('roles')->where('name', 'Validator Nilai')->value('id');

        if ($roleId !== null) {
            $ids = $ids->merge(
                DB::table('model_has_roles')
                    ->where('role_id', $roleId)
                    ->where('model_type', (new User)->getMorphClass())
                    ->pluck('model_uuid')
            );
        }

        return $ids->map(fn ($id) => (string) $id)->unique()->values();
    }

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

        $result = $this->syncValidatorNilai($user);
        $granted += $result === 'granted' ? 1 : 0;
        $revoked += $result === 'revoked' ? 1 : 0;

        return ['granted' => $granted, 'revoked' => $revoked];
    }

    /**
     * Sinkronkan HANYA peran Validator Nilai seorang pengguna. Dipakai jalur
     * yang mengatur peran lain sendiri (mis. impor pengguna) tetapi tidak bisa
     * menyebut Validator Nilai, supaya Pimpinan/Tim Kurikulum yang diatur
     * jalur itu tidak ikut dicabut oleh syncForUser().
     *
     * @return 'granted'|'revoked'|'unchanged'
     */
    public function syncValidatorNilaiUntukPengguna(string $userId): string
    {
        $user = User::query()->find($userId);

        return $user ? $this->syncValidatorNilai($user) : 'unchanged';
    }

    /**
     * Peran Validator Nilai mengikuti hak dari kurikulum, bukan penanda
     * penugasan; peran yang diberikan manual tanpa hak ikut dicabut.
     *
     * @return 'granted'|'revoked'|'unchanged'
     */
    private function syncValidatorNilai(User $user): string
    {
        $role = Role::query()->where('name', 'Validator Nilai')->first();

        if (! $role) {
            return 'unchanged';
        }

        if (ValidatorNilaiScope::punyaHakValidasi($user)) {
            $changes = $user->roles()->syncWithoutDetaching([$role->id]);

            return $changes['attached'] !== [] ? 'granted' : 'unchanged';
        }

        return $user->roles()->detach($role->id) > 0 ? 'revoked' : 'unchanged';
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
