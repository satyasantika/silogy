<?php

namespace App\Modules\MK\Services;

use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplMk;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkCpmk;

class CpmkCplPemetaanService
{
    /**
     * Validasi kode CPL dapat dipetakan ke CPMK pada MK terkait (prasyarat CPL–MK).
     *
     * @return array{valid: bool, keterangan: string}
     */
    public static function validasiKodeCplUntukMk(string $kodeCpl, Mk|string $mk): array
    {
        $kodeCpl = trim($kodeCpl);

        if ($kodeCpl === '') {
            return ['valid' => true, 'keterangan' => ''];
        }

        $mkModel = $mk instanceof Mk ? $mk : Mk::query()->findOrFail($mk);

        // kurikulum_id, BUKAN academic_unit_id — satu unit bisa punya beberapa
        // kurikulum/generasi yang memakai ulang kode CPL yang sama (kode unik
        // per (kurikulum_id, kode), lihat migrasi 2026_07_27_...cpl_bok_mk).
        // Filter academic_unit_id saja bisa menemukan Cpl dari generasi
        // kurikulum yang salah — ada, tapi tidak pernah dipetakan ke MK ini.
        $cpl = Cpl::query()
            ->where('kurikulum_id', $mkModel->kurikulum_id)
            ->where('kode', $kodeCpl)
            ->first();

        if (! $cpl) {
            return [
                'valid' => false,
                'keterangan' => "CPL '{$kodeCpl}' tidak ditemukan pada unit MK ini.",
            ];
        }

        $adaCplMk = CplMk::query()
            ->where('mk_id', $mkModel->id)
            ->whereHas('cplBok', fn ($query) => $query->where('cpl_id', $cpl->id))
            ->exists();

        if (! $adaCplMk) {
            return [
                'valid' => false,
                'keterangan' => "CPL '{$kodeCpl}' belum dipetakan ke MK lewat interaksi CPL ↔ MK.",
            ];
        }

        return ['valid' => true, 'keterangan' => ''];
    }

    /**
     * Buat atau perbarui pemetaan CPL ↔ CPMK (pivot mk_cpmk, bobot 0).
     */
    public static function petakanCpmkKeCpl(Cpmk $cpmk, string $kodeCpl): void
    {
        $kodeCpl = trim($kodeCpl);

        if ($kodeCpl === '') {
            return;
        }

        $cpmk->loadMissing('mk');

        // kurikulum_id — lihat catatan pada validasiKodeCplUntukMk().
        $cpl = Cpl::query()
            ->where('kurikulum_id', $cpmk->mk->kurikulum_id)
            ->where('kode', $kodeCpl)
            ->firstOrFail();

        $cplMks = CplMk::query()
            ->where('mk_id', $cpmk->mk_id)
            ->whereHas('cplBok', fn ($query) => $query->where('cpl_id', $cpl->id))
            ->get();

        foreach ($cplMks as $cplMk) {
            MkCpmk::query()->updateOrCreate(
                [
                    'cpl_mk_id' => $cplMk->id,
                    'cpmk_id' => $cpmk->id,
                ],
                ['bobot' => 0],
            );
        }
    }
}
