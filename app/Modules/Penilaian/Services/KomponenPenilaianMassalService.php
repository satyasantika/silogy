<?php

namespace App\Modules\Penilaian\Services;

use App\Modules\Kalender\Support\SemesterTerpilih;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Support\MkTerpilih;
use App\Modules\MK\Support\PenawaranMkScope;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use Illuminate\Support\Collection;

/**
 * Asesmen (komponen penilaian) berlaku untuk semua kelas pada satu mata
 * kuliah + semester, bukan per kelas. Service ini menentukan konteks
 * "mode massal" tersebut dan menerapkan create/update ke seluruh kelasnya.
 */
class KomponenPenilaianMassalService
{
    /**
     * Konteks mode massal saat membuat asesmen baru — mengikuti Mata
     * Kuliah dan Semester yang sedang terpilih (khusus Koordinator MK).
     *
     * @return array{mk: Mk, semester_id: string, kelas: Collection<int, KelasMk>}|null
     */
    public static function resolveUntukPembuatan(): ?array
    {
        if (! SemesterTerpilih::berlakuUntukUser()) {
            return null;
        }

        $mk = MkTerpilih::current();

        if (! $mk instanceof Mk) {
            return null;
        }

        $semesterId = SemesterTerpilih::currentId($mk->id);

        if (blank($semesterId)) {
            return null;
        }

        return static::resolveKelas($mk, $semesterId);
    }

    /**
     * Konteks mode massal untuk record yang sedang diedit — diturunkan dari
     * kelas MK milik record itu sendiri, bukan dari sesi Mata Kuliah/Semester
     * terpilih, agar tetap benar walau konteks sesi sudah berpindah.
     *
     * @return array{mk: Mk, semester_id: string, kelas: Collection<int, KelasMk>}|null
     */
    public static function resolveUntukRecord(KomponenPenilaian $komponen): ?array
    {
        $komponen->loadMissing('kelasMk.mkUnit.mk');

        $kelasMk = $komponen->kelasMk;
        $mk = $kelasMk?->mkUnit?->mk;
        $semesterId = $kelasMk?->semester_id;

        if (! $mk instanceof Mk || blank($semesterId)) {
            return null;
        }

        return static::resolveKelas($mk, $semesterId);
    }

    /**
     * @return array{mk: Mk, semester_id: string, kelas: Collection<int, KelasMk>}|null
     */
    protected static function resolveKelas(Mk $mk, string $semesterId): ?array
    {
        $kelas = PenawaranMkScope::kelasMkUntukMkSemester($mk->id, $semesterId);

        if ($kelas->isEmpty()) {
            return null;
        }

        return ['mk' => $mk, 'semester_id' => $semesterId, 'kelas' => $kelas];
    }

    /**
     * Buat/perbarui (berdasar kode) komponen penilaian pada semua kelas
     * dalam konteks massal; mengembalikan record pada kelas "anchor" (pertama).
     *
     * @param  array{kode: string, evaluasi_id: string|null, nama: string, bobot: float}  $payload
     * @param  array{mk: Mk, semester_id: string, kelas: Collection<int, KelasMk>}  $massal
     */
    public static function buatUntukSemuaKelas(array $payload, array $massal): KomponenPenilaian
    {
        return $massal['kelas']
            ->map(fn (KelasMk $kelasMk): KomponenPenilaian => KomponenPenilaian::query()->updateOrCreate(
                ['kelas_mk_id' => $kelasMk->id, 'kode' => $payload['kode']],
                $payload,
            ))
            ->first();
    }

    /**
     * Terapkan perubahan record yang baru disimpan ke komponen kelas lain
     * dengan kode lama yang sama, dalam konteks massal yang sama.
     *
     * @param  array{kode: string, evaluasi_id: string|null, nama: string, bobot: float}  $payload
     * @param  array{mk: Mk, semester_id: string, kelas: Collection<int, KelasMk>}  $massal
     */
    public static function perbaruiSemuaKelas(
        KomponenPenilaian $record,
        string $kodeLama,
        array $payload,
        array $massal,
    ): void {
        KomponenPenilaian::query()
            ->whereIn('kelas_mk_id', $massal['kelas']->pluck('id'))
            ->where('kode', $kodeLama)
            ->where('id', '!=', $record->getKey())
            ->get()
            ->each(fn (KomponenPenilaian $sibling) => $sibling->update($payload));
    }
}
