<?php

namespace App\Modules\Institusi\Observers;

use App\Modules\Institusi\Models\AcademicUnitUser;
use App\Modules\Institusi\Support\AcademicUnitUserRoleSync;

/**
 * Saat penugasan pengguna pada unit akademik dibuat/diubah/dihapus,
 * sinkronkan otomatis role global "Pimpinan"/"Tim Kurikulum" lewat
 * AcademicUnitUserRoleSync (role dicabut hanya bila TIDAK ADA lagi
 * penugasan aktif dengan status itu di unit manapun, karena role ini
 * global per pengguna, bukan per unit).
 *
 * Batasan yang diketahui: bila seluruh AcademicUnit atau User dihapus,
 * baris academic_unit_users ikut terhapus lewat foreign key
 * cascadeOnDelete() di level database — itu TIDAK memicu event Eloquent
 * `deleted`, jadi sinkronisasi di sini tidak berjalan untuk kasus itu.
 * Konsisten dengan batasan cascade FK yang sudah ada di aplikasi ini
 * secara umum, bukan hal baru yang perlu diperbaiki di sini.
 */
class AcademicUnitUserObserver
{
    public function __construct(private readonly AcademicUnitUserRoleSync $roleSync) {}

    public function saved(AcademicUnitUser $academicUnitUser): void
    {
        $this->roleSync->syncForUser($academicUnitUser->user_id);

        if ($academicUnitUser->wasChanged('user_id')) {
            $previousUserId = $academicUnitUser->getOriginal('user_id');

            if ($previousUserId !== null) {
                $this->roleSync->syncForUser($previousUserId);
            }
        }
    }

    public function deleted(AcademicUnitUser $academicUnitUser): void
    {
        $this->roleSync->syncForUser($academicUnitUser->user_id);
    }
}
