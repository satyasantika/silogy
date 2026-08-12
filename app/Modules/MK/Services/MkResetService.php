<?php

namespace App\Modules\MK\Services;

use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\MK\Models\Mk;

class MkResetService
{
    /**
     * Kosongkan seluruh MK kurikulum ini. Satu per satu (bukan bulk
     * delete) — MkObserver::deleted() yang mencabut role Koordinator Mata
     * Kuliah hanya terpicu delete per-model.
     */
    public function reset(Kurikulum $kurikulum): void
    {
        Mk::query()->where('kurikulum_id', $kurikulum->id)->get()->each->delete();
    }

    /**
     * Aman direset hanya bila BELUM ADA satu pun MK kurikulum ini yang
     * sudah punya Penawaran, CPMK, atau pemetaan CPL-MK.
     */
    public function bisaDireset(Kurikulum $kurikulum): bool
    {
        return Mk::query()
            ->where('kurikulum_id', $kurikulum->id)
            ->where(fn ($query) => $query->whereHas('mkUnits')->orWhereHas('cpmks')->orWhereHas('cplMks'))
            ->doesntExist();
    }
}
