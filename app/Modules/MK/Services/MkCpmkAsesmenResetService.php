<?php

namespace App\Modules\MK\Services;

use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use Illuminate\Support\Facades\DB;

class MkCpmkAsesmenResetService
{
    /**
     * Kosongkan CPMK, Sub-CPMK, dan Asesmen (KomponenPenilaian) satu MK
     * beserta relasinya. CPL, BoK, pemetaan CPL-MK, dan MK itu sendiri
     * TIDAK ikut terhapus.
     */
    public function reset(Mk $mk): void
    {
        DB::transaction(function () use ($mk): void {
            // Cpmk -> mk_cpmk -> subcpmk -> subcpmk_komponenpenilaian ->
            // nilai_mahasiswas (cascade DB). cpl_mk TIDAK ikut terhapus — FK
            // mengalir dari cpl_mk ke mk_cpmk, bukan sebaliknya.
            Cpmk::query()->where('mk_id', $mk->id)->delete();

            // KomponenPenilaian -> subcpmk_komponenpenilaian (sisa) ->
            // nilai_mahasiswas.
            KomponenPenilaian::query()->where('mk_id', $mk->id)->delete();
        });
    }
}
