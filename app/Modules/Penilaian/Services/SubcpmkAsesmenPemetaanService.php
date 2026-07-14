<?php

namespace App\Modules\Penilaian\Services;

use App\Modules\MK\Models\Subcpmk;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;
use Illuminate\Support\Collection;

class SubcpmkAsesmenPemetaanService
{
    /**
     * Rincian kontribusi Sub-CPMK ke nilai akhir mata kuliah, dikelompokkan
     * per jenis evaluasi (mis. Tugas, Proyek Individu). Bobot pivot Sub-CPMK
     * SUDAH berupa kontribusi nyata (skala sama dengan bobot komponen
     * penilaian, bukan lagi persentase bagian dari 100) — jadi kontribusi
     * per jenis = jumlah bobot pivot itu sendiri, tanpa dikalikan ulang.
     * Dipakai untuk tampilan rekap maupun untuk menghitung ulang subcpmk.bobot.
     *
     * @return Collection<string, float> nama evaluasi => bobot (%)
     */
    public static function rincianBobotEvaluasi(string $subcpmkId): Collection
    {
        return SubcpmkKomponenPenilaian::query()
            ->where('subcpmk_id', $subcpmkId)
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
     * Hitung ulang subcpmk.bobot dari seluruh interaksinya dengan komponen
     * penilaian — dipanggil otomatis setiap interaksi Sub-CPMK ↔ penugasan
     * berubah (lihat SubcpmkKomponenPenilaianObserver), agar bobot Sub-CPMK
     * selalu mencerminkan kontribusi asesmen yang sebenarnya.
     */
    public static function recalculateBobotSubcpmk(string $subcpmkId): void
    {
        $total = round((float) self::rincianBobotEvaluasi($subcpmkId)->sum(), 2);

        Subcpmk::query()->whereKey($subcpmkId)->update(['bobot' => $total]);
    }

    /**
     * Petakan Sub-CPMK ke asesmen (komponen penilaian) lalu bagi bobot pivot
     * merata (bobot Asesmen ÷ jumlah Sub-CPMK).
     */
    public static function petakanSubcpmk(KomponenPenilaian $komponen, Subcpmk $subcpmk): void
    {
        SubcpmkKomponenPenilaian::query()->updateOrCreate(
            [
                'komponen_penilaian_id' => $komponen->id,
                'subcpmk_id' => $subcpmk->id,
            ],
            [
                'semester_id' => $komponen->semester_id,
                'bobot' => 0,
            ],
        );

        self::redistribusiBobotMerata($komponen);
    }

    /**
     * Bagi bobot pivot Sub-CPMK ↔ asesmen secara merata agar total = bobot
     * Asesmen itu sendiri (bukan lagi selalu 100).
     */
    public static function redistribusiBobotMerata(KomponenPenilaian|string $komponen): void
    {
        $komponen = $komponen instanceof KomponenPenilaian ? $komponen : KomponenPenilaian::query()->find($komponen);

        if ($komponen === null) {
            return;
        }

        $pivots = SubcpmkKomponenPenilaian::query()
            ->where('komponen_penilaian_id', $komponen->id)
            ->get();

        $jumlah = $pivots->count();

        if ($jumlah === 0) {
            return;
        }

        $bobotPerSubcpmk = round((float) $komponen->bobot / $jumlah, 2);

        foreach ($pivots as $pivot) {
            $pivot->update(['bobot' => $bobotPerSubcpmk]);
        }
    }

    /**
     * Sisa kapasitas bobot yang masih tersedia pada suatu Asesmen — bobot
     * Asesmen dikurangi jumlah bobot pivot Sub-CPMK LAIN yang sudah
     * berinteraksi dengannya (di luar pivot $excludeSubcpmkKomponenId, bila
     * sedang mengedit pivot yang sudah ada). Satu sumber kebenaran dipakai
     * baik oleh form interaksi (RelationManager) maupun halaman
     * Matrix/Clipboard, supaya batasnya konsisten di semua tempat.
     */
    public static function sisaBobotTersedia(KomponenPenilaian $komponen, ?string $excludeSubcpmkKomponenId = null): float
    {
        $terpakai = (float) SubcpmkKomponenPenilaian::query()
            ->where('komponen_penilaian_id', $komponen->id)
            ->when(
                $excludeSubcpmkKomponenId !== null,
                fn ($query) => $query->whereKeyNot($excludeSubcpmkKomponenId),
            )
            ->sum('bobot');

        return round(max((float) $komponen->bobot - $terpakai, 0), 2);
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
            ->where('semester_id', $semesterId)
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
