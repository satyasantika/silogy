<?php

namespace App\Modules\BoK\Services;

use App\Modules\BoK\Models\Bok;
use App\Modules\Kurikulum\Models\Kurikulum;

class BokResetService
{
    /**
     * Kosongkan seluruh BoK kurikulum ini. Tidak ada Observer terdaftar
     * pada Bok — bulk delete aman.
     */
    public function reset(Kurikulum $kurikulum): void
    {
        Bok::query()->where('kurikulum_id', $kurikulum->id)->delete();
    }

    /**
     * Aman direset hanya bila BELUM ADA satu pun BoK kurikulum ini yang
     * sudah dipetakan ke CPL.
     */
    public function bisaDireset(Kurikulum $kurikulum): bool
    {
        return Bok::query()
            ->where('kurikulum_id', $kurikulum->id)
            ->whereHas('cplBoks')
            ->doesntExist();
    }
}
