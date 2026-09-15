<?php

namespace App\Modules\MK\Support;

use App\Modules\MK\Models\Cpmk;
use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aturan kaskade "pakai lama vs harus baru", bergranularitas PER-CPMK.
 *
 * Intinya: turunan hanya boleh dipakai ulang bila induknya juga dipakai
 * ulang. CPMK yang baru disusun untuk semester berjalan berarti rumusan
 * capaiannya berubah — Sub-CPMK dan Asesmen lama tidak lagi mengukur hal
 * yang sama, jadi keduanya wajib baru. Sebaliknya CPMK yang dipertahankan
 * boleh mewarisi seluruh turunannya.
 */
final class KaskadeReuseSemester
{
    /**
     * CPMK dianggap BARU di sebuah semester bila ia terlampir di semester
     * itu dan tidak terlampir di satu pun semester yang lebih awal
     * (urutan semesters.kode, format YYYYS sehingga tersortir leksikografis).
     */
    public static function cpmkBaruDiSemester(string $cpmkId, string $semesterId): bool
    {
        $kode = DB::table('semesters')->where('id', $semesterId)->value('kode');

        if ($kode === null) {
            return true;
        }

        $terlampirDiSemesterIni = DB::table('cpmk_semester')
            ->where('cpmk_id', $cpmkId)
            ->where('semester_id', $semesterId)
            ->exists();

        if (! $terlampirDiSemesterIni) {
            return false;
        }

        return ! DB::table('cpmk_semester')
            ->join('semesters', 'semesters.id', '=', 'cpmk_semester.semester_id')
            ->where('cpmk_semester.cpmk_id', $cpmkId)
            ->where('semesters.kode', '<', $kode)
            ->exists();
    }

    /**
     * CPMK satu MK yang turunannya boleh dipakai ulang di semester ini:
     * terlampir di semester ini DAN bukan CPMK yang baru lahir di sini.
     *
     * @return Collection<int, string>
     */
    public static function cpmkIdsBolehReuseSubtree(string $mkId, string $semesterId): Collection
    {
        return Cpmk::query()
            ->where('mk_id', $mkId)
            ->untukSemester($semesterId)
            ->pluck('id')
            ->reject(fn ($cpmkId): bool => self::cpmkBaruDiSemester((string) $cpmkId, $semesterId))
            ->map(fn ($cpmkId): string => (string) $cpmkId)
            ->values();
    }

    /**
     * Kode Sub-CPMK yang dipetakan ke sebuah Asesmen pada semester sumber
     * tetapi BELUM terlampir di semester tujuan. Selama daftar ini tidak
     * kosong, Asesmen itu tidak boleh dipakai ulang — memakai ulangnya akan
     * menghasilkan asesmen yang mengukur Sub-CPMK yang tidak ada.
     *
     * @return list<string>
     */
    public static function subcpmkBelumSiapUntukAsesmen(
        string $komponenPenilaianId,
        string $sumberSemesterId,
        string $targetSemesterId,
    ): array {
        $pemetaan = SubcpmkKomponenPenilaian::query()
            ->where('komponen_penilaian_id', $komponenPenilaianId)
            ->where('semester_id', $sumberSemesterId)
            ->with('subcpmk')
            ->get();

        $belumSiap = [];

        foreach ($pemetaan as $pivot) {
            $sudahTerlampir = DB::table('subcpmk_semester')
                ->where('subcpmk_id', $pivot->subcpmk_id)
                ->where('semester_id', $targetSemesterId)
                ->exists();

            if (! $sudahTerlampir) {
                $belumSiap[] = $pivot->subcpmk === null ? '—' : (string) $pivot->subcpmk->kode;
            }
        }

        return array_values(array_unique($belumSiap));
    }
}
