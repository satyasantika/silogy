<?php

namespace App\Modules\Penilaian\Services;

use App\Modules\Kelas\Models\KelasMk;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;

class SubcpmkAsesmenPemetaanService
{
    /**
     * Petakan Sub-CPMK ke asesmen (komponen penilaian) lalu bagi bobot pivot merata (100 ÷ jumlah Sub-CPMK).
     */
    public static function petakanSubcpmk(KomponenPenilaian $komponen, Subcpmk $subcpmk): void
    {
        $komponen->loadMissing('kelasMk');

        SubcpmkKomponenPenilaian::query()->updateOrCreate(
            [
                'komponen_penilaian_id' => $komponen->id,
                'subcpmk_id' => $subcpmk->id,
            ],
            [
                'semester_id' => $komponen->kelasMk?->semester_id,
                'bobot' => 100,
            ],
        );

        self::redistribusiBobotMerata($komponen);
    }

    /**
     * Bagi bobot pivot Sub-CPMK ↔ asesmen secara merata agar total = 100%.
     */
    public static function redistribusiBobotMerata(KomponenPenilaian|string $komponen): void
    {
        $komponenId = $komponen instanceof KomponenPenilaian ? $komponen->id : $komponen;

        $pivots = SubcpmkKomponenPenilaian::query()
            ->where('komponen_penilaian_id', $komponenId)
            ->get();

        $jumlah = $pivots->count();

        if ($jumlah === 0) {
            return;
        }

        $bobotPerSubcpmk = round(100 / $jumlah, 2);

        foreach ($pivots as $pivot) {
            $pivot->update(['bobot' => $bobotPerSubcpmk]);
        }
    }

    /**
     * Cari Sub-CPMK berdasarkan kode pada MK dan semester kelas terkait.
     */
    public static function cariSubcpmkUntukKelas(string $kodeSubcpmk, KelasMk $kelasMk): ?Subcpmk
    {
        $kodeSubcpmk = trim($kodeSubcpmk);

        if ($kodeSubcpmk === '') {
            return null;
        }

        $kelasMk->loadMissing('mkUnit');

        $mkId = $kelasMk->mkUnit?->mk_id;

        if ($mkId === null) {
            return null;
        }

        return Subcpmk::query()
            ->where('kode', $kodeSubcpmk)
            ->where('semester_id', $kelasMk->semester_id)
            ->whereHas(
                'mkCpmk.cpmk',
                fn ($query) => $query->where('mk_id', $mkId),
            )
            ->first();
    }

    /**
     * @return array{valid: bool, keterangan: string}
     */
    public static function validasiKodeSubcpmk(string $kodeSubcpmk, KelasMk $kelasMk): array
    {
        $kodeSubcpmk = trim($kodeSubcpmk);

        if ($kodeSubcpmk === '') {
            return ['valid' => true, 'keterangan' => ''];
        }

        if (self::cariSubcpmkUntukKelas($kodeSubcpmk, $kelasMk) === null) {
            return [
                'valid' => false,
                'keterangan' => "Sub-CPMK '{$kodeSubcpmk}' tidak ditemukan pada MK dan semester kelas ini.",
            ];
        }

        return ['valid' => true, 'keterangan' => ''];
    }
}
