<?php

namespace App\Modules\MK\Support;

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\MK\Enums\StatusPerubahanCpmk;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\PerubahanCpmkRequest;

/**
 * Gerbang tunggal yang menjawab: bolehkah CPMK mata kuliah ini diubah untuk
 * semester ini?
 *
 * Aturannya sengaja dipusatkan di sini karena dipakai policy, form Filament,
 * matriks CPL↔CPMK, dan tombol reset — kalau tersebar, salah satu pasti
 * ketinggalan dan gerbangnya bocor.
 */
final class GerbangPerubahanCpmk
{
    public static function bolehUbah(Mk $mk, ?string $semesterId, ?User $user = null): bool
    {
        if ($semesterId === null) {
            return false;
        }

        // Tim Kurikulum unit MK (atau induknya) dan Admin/Super Admin memang
        // pemilik kewenangan itu — tidak perlu mengajukan ke dirinya sendiri.
        if ($user instanceof User && self::userBerwenangMenyetujui($mk, $user)) {
            return true;
        }

        // Semester yang CPMK-nya masih kosong: pengisian pertama bebas.
        // Belum ada rumusan berjalan yang diubah, jadi tidak ada yang perlu
        // dilindungi — koordinator tinggal memilih pakai lama atau susun baru.
        if (! self::sudahAdaCpmk($mk, $semesterId)) {
            return true;
        }

        return PerubahanCpmkRequest::query()
            ->where('mk_id', $mk->id)
            ->where('semester_id', $semesterId)
            ->where('status', StatusPerubahanCpmk::Disetujui)
            ->exists();
    }

    /**
     * Usulan yang sedang menunggu keputusan untuk pasangan MK + semester ini.
     */
    public static function usulanTerbuka(Mk $mk, ?string $semesterId): ?PerubahanCpmkRequest
    {
        if ($semesterId === null) {
            return null;
        }

        return PerubahanCpmkRequest::query()
            ->where('mk_id', $mk->id)
            ->where('semester_id', $semesterId)
            ->where('status', StatusPerubahanCpmk::Diajukan)
            ->latest()
            ->first();
    }

    /**
     * Perlu mengajukan usulan: sudah ada CPMK berjalan di semester ini, belum
     * ada persetujuan, dan user bukan pihak yang berwenang menyetujui.
     */
    public static function butuhPersetujuan(Mk $mk, ?string $semesterId, ?User $user = null): bool
    {
        return $semesterId !== null && ! self::bolehUbah($mk, $semesterId, $user);
    }

    public static function userBerwenangMenyetujui(Mk $mk, User $user): bool
    {
        $mk->loadMissing('academicUnit');
        $unit = $mk->academicUnit;

        return $unit instanceof AcademicUnit
            && AcademicUnitScope::userIsTimKurikulumOnUnitOrAncestor($user, $unit);
    }

    private static function sudahAdaCpmk(Mk $mk, string $semesterId): bool
    {
        return Cpmk::query()
            ->where('mk_id', $mk->id)
            ->untukSemester($semesterId)
            ->exists();
    }
}
