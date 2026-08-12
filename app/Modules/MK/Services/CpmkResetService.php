<?php

namespace App\Modules\MK\Services;

use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;

class CpmkResetService
{
    /**
     * Kosongkan seluruh CPMK MK ini (cascade mk_cpmk, subcpmk,
     * subcpmk_komponenpenilaian, nilai_mahasiswas via FK — tapi berkat
     * bisaDireset(), seharusnya memang tidak ada). cpl_mk TIDAK ikut
     * terhapus. Tidak ada Observer terdaftar pada Cpmk — bulk delete aman.
     */
    public function reset(Mk $mk): void
    {
        Cpmk::query()->where('mk_id', $mk->id)->delete();
    }

    /**
     * Aman direset hanya bila BELUM ADA satu pun CPMK MK ini yang sudah
     * dipetakan ke CPL-MK (mk_cpmk) — pemetaan itu tidak mungkin ada tanpa
     * mk_cpmk, jadi cek ini otomatis juga menjamin tidak ada Sub-CPMK.
     */
    public function bisaDireset(Mk $mk): bool
    {
        return Cpmk::query()
            ->where('mk_id', $mk->id)
            ->whereHas('mkCpmks')
            ->doesntExist();
    }
}
