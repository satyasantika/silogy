<?php

namespace App\Modules\MK\Support;

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\MK\Enums\StatusPerubahanCpmk;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\PerubahanCpmkRequest;
use App\Modules\Penilaian\Models\NilaiMahasiswa;

/**
 * Gerbang tunggal yang menjawab: bolehkah CPMK mata kuliah ini diubah untuk
 * semester ini? (Kini: selalu boleh selama ada semester terpilih.)
 *
 * Aturannya sengaja dipusatkan di sini karena dipakai policy, form Filament,
 * matriks CPL↔CPMK, dan tombol reset — kalau tersebar, salah satu pasti
 * ketinggalan dan gerbangnya bocor.
 */
final class GerbangPerubahanCpmk
{
    /**
     * Koordinator bebas menambah, mengurangi, dan mengubah CPMK/Sub-CPMK
     * kapan pun — tidak ada lagi usulan yang harus disetujui Tim Kurikulum.
     * Yang tersisa hanyalah peringatan bagi CPMK yang sudah dinilai
     * (lihat sudahDinilai()) dan jejak audit pada modelnya. Pintu ini
     * dipertahankan sebagai satu titik keputusan supaya aturan bisa
     * diketatkan lagi tanpa menyentuh policy, form, dan matriks.
     */
    public static function bolehUbah(Mk $mk, ?string $semesterId, ?User $user = null): bool
    {
        return $semesterId !== null;
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
     * Selalu false sejak perubahan CPMK tidak lagi butuh persetujuan; tetap
     * ada agar tombol pengajuan dan spanduk matriks mengikuti satu sumber.
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

    /**
     * Apakah ada nilai mahasiswa yang terisi pada asesmen Sub-CPMK milik CPMK
     * MK ini di semester tersebut. Sel nilai yang masih kosong tidak dihitung.
     */
    public static function sudahDinilai(Mk $mk, string $semesterId): bool
    {
        return NilaiMahasiswa::query()
            ->whereNotNull('nilai')
            ->whereHas('subcpmkKomponen', fn ($pivot) => $pivot
                ->where('semester_id', $semesterId)
                ->whereHas('subcpmk.mkCpmk.cpmk', fn ($cpmk) => $cpmk->where('mk_id', $mk->id)))
            ->exists();
    }
}
