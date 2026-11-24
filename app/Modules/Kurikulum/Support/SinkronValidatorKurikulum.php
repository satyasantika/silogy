<?php

namespace App\Modules\Kurikulum\Support;

use App\Modules\Institusi\Support\AcademicUnitUserRoleSync;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Penilaian\Support\ValidatorNilaiScope;

/**
 * Menyinkronkan peran "Validator Nilai" semua orang yang hak validasinya
 * bergantung pada satu kurikulum: pimpinan/tim kurikulum prodi pemilik,
 * validator tambahan, dan (lewat $userIdsSebelumnya) orang yang baru saja
 * kehilangan hak itu, misalnya karena dilepas dari pivot.
 */
class SinkronValidatorKurikulum
{
    public function __construct(private readonly AcademicUnitUserRoleSync $roleSync) {}

    /**
     * @param  array<int, string>  $userIdsSebelumnya
     */
    public function sinkronkan(Kurikulum $kurikulum, array $userIdsSebelumnya = []): void
    {
        $userIds = collect($userIdsSebelumnya)
            ->merge($kurikulum->validatorTambahan()->pluck('users.id'))
            ->merge(ValidatorNilaiScope::otomatisBerhakUserIds($kurikulum))
            ->unique();

        foreach ($userIds as $userId) {
            $this->roleSync->syncForUser($userId);
        }
    }
}
