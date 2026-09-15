<?php

namespace App\Modules\Penilaian\Services;

use App\Modules\MK\Models\Subcpmk;
use App\Modules\MK\Models\SubcpmkSemester;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;
use Illuminate\Support\Collection;

/**
 * Semua operasi di sini BERCAKUPAN SEMESTER. Sejak satu baris Sub-CPMK dan
 * satu baris Asesmen boleh dipakai ulang di beberapa semester, menjumlahkan
 * pemetaan tanpa menyaring semester akan mencampur bobot antar semester —
 * dan, lebih buruk, menimpa angka semester yang nilainya sudah terkunci.
 */
class SubcpmkAsesmenPemetaanService
{
    /**
     * Rincian kontribusi Sub-CPMK ke nilai akhir mata kuliah pada satu
     * semester, dikelompokkan per jenis evaluasi (mis. Tugas, Proyek
     * Individu). Bobot pivot Sub-CPMK SUDAH berupa kontribusi nyata (skala
     * sama dengan bobot Asesmen, bukan lagi persentase bagian dari 100) —
     * jadi kontribusi per jenis = jumlah bobot pivot itu sendiri, tanpa
     * dikalikan ulang.
     *
     * @return Collection<string, float> nama evaluasi => bobot (%)
     */
    public static function rincianBobotEvaluasi(string $subcpmkId, string $semesterId): Collection
    {
        return SubcpmkKomponenPenilaian::query()
            ->where('subcpmk_id', $subcpmkId)
            ->where('semester_id', $semesterId)
            ->with('komponenPenilaian.evaluasi')
            ->get()
            ->filter(fn (SubcpmkKomponenPenilaian $pivot): bool => $pivot->komponenPenilaian?->evaluasi !== null)
            ->groupBy(fn (SubcpmkKomponenPenilaian $pivot): string => $pivot->komponenPenilaian->evaluasi->nama)
            ->map(fn (Collection $group): float => (float) $group->sum(
                fn (SubcpmkKomponenPenilaian $pivot): float => (float) $pivot->bobot,
            ))
            ->sortDesc();
    }

    /**
     * Hitung ulang bobot Sub-CPMK PADA SATU SEMESTER dari seluruh
     * interaksinya dengan Asesmen di semester itu — dipanggil otomatis
     * setiap pemetaan berubah (lihat SubcpmkKomponenPenilaianObserver).
     * Hasilnya disimpan di subcpmk_semester.bobot, bukan pada baris
     * Sub-CPMK, supaya semester lain tidak ikut berubah.
     */
    public static function recalculateBobotSubcpmk(string $subcpmkId, string $semesterId): void
    {
        $total = round((float) self::rincianBobotEvaluasi($subcpmkId, $semesterId)->sum(), 2);

        SubcpmkSemester::query()->updateOrCreate(
            [
                'subcpmk_id' => $subcpmkId,
                'semester_id' => $semesterId,
            ],
            ['bobot' => $total],
        );
    }

    /**
     * Petakan Sub-CPMK ke Asesmen pada satu semester, lalu bagi bobot pivot
     * merata (bobot Asesmen di semester itu ÷ jumlah Sub-CPMK).
     */
    public static function petakanSubcpmk(KomponenPenilaian $komponen, Subcpmk $subcpmk, string $semesterId): void
    {
        SubcpmkKomponenPenilaian::query()->updateOrCreate(
            [
                'komponen_penilaian_id' => $komponen->id,
                'subcpmk_id' => $subcpmk->id,
                'semester_id' => $semesterId,
            ],
            ['bobot' => 0],
        );

        self::redistribusiBobotMerata($komponen, $semesterId);
    }

    /**
     * Bagi bobot pivot Sub-CPMK ↔ Asesmen secara merata agar total = bobot
     * Asesmen itu sendiri pada semester tersebut (bukan lagi selalu 100).
     */
    public static function redistribusiBobotMerata(KomponenPenilaian|string $komponen, string $semesterId): void
    {
        $komponen = $komponen instanceof KomponenPenilaian ? $komponen : KomponenPenilaian::query()->find($komponen);

        if ($komponen === null) {
            return;
        }

        $pivots = SubcpmkKomponenPenilaian::query()
            ->where('komponen_penilaian_id', $komponen->id)
            ->where('semester_id', $semesterId)
            ->get();

        $jumlah = $pivots->count();

        if ($jumlah === 0) {
            return;
        }

        $bobotPerSubcpmk = round($komponen->bobotUntukSemester($semesterId) / $jumlah, 2);

        foreach ($pivots as $pivot) {
            $pivot->update(['bobot' => $bobotPerSubcpmk]);
        }
    }

    /**
     * Sisa kapasitas bobot yang masih tersedia pada suatu Asesmen di satu
     * semester — bobot Asesmen dikurangi jumlah bobot pivot Sub-CPMK LAIN
     * yang sudah berinteraksi dengannya pada semester itu (di luar pivot
     * $excludeSubcpmkKomponenId, bila sedang mengedit pivot yang sudah ada).
     * Satu sumber kebenaran dipakai baik oleh form interaksi
     * (RelationManager) maupun halaman Matrix/Clipboard, supaya batasnya
     * konsisten di semua tempat.
     */
    public static function sisaBobotTersedia(
        KomponenPenilaian $komponen,
        string $semesterId,
        ?string $excludeSubcpmkKomponenId = null,
    ): float {
        $terpakai = (float) SubcpmkKomponenPenilaian::query()
            ->where('komponen_penilaian_id', $komponen->id)
            ->where('semester_id', $semesterId)
            ->when(
                $excludeSubcpmkKomponenId !== null,
                fn ($query) => $query->whereKeyNot($excludeSubcpmkKomponenId),
            )
            ->sum('bobot');

        return round(max($komponen->bobotUntukSemester($semesterId) - $terpakai, 0), 2);
    }

    /**
     * Cari Sub-CPMK berdasarkan kode pada MK dan semester tertentu.
     */
    public static function cariSubcpmkUntukMk(string $kodeSubcpmk, string $mkId, string $semesterId): ?Subcpmk
    {
        $kodeSubcpmk = trim($kodeSubcpmk);

        if ($kodeSubcpmk === '') {
            return null;
        }

        return Subcpmk::query()
            ->where('kode', $kodeSubcpmk)
            ->untukSemester($semesterId)
            ->whereHas(
                'mkCpmk.cpmk',
                fn ($query) => $query->where('mk_id', $mkId),
            )
            ->first();
    }

    /**
     * @return array{valid: bool, keterangan: string}
     */
    public static function validasiKodeSubcpmk(string $kodeSubcpmk, string $mkId, string $semesterId): array
    {
        $kodeSubcpmk = trim($kodeSubcpmk);

        if ($kodeSubcpmk === '') {
            return ['valid' => true, 'keterangan' => ''];
        }

        if (self::cariSubcpmkUntukMk($kodeSubcpmk, $mkId, $semesterId) === null) {
            return [
                'valid' => false,
                'keterangan' => "Sub-CPMK '{$kodeSubcpmk}' tidak ditemukan pada mata kuliah dan semester ini.",
            ];
        }

        return ['valid' => true, 'keterangan' => ''];
    }
}
