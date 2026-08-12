<?php

namespace App\Modules\CPL\Services;

use App\Modules\CPL\Models\Cpl;
use App\Modules\Kurikulum\Models\Kurikulum;

class CplResetService
{
    /**
     * Kosongkan seluruh CPL kurikulum ini. Tidak ada Observer terdaftar
     * pada Cpl — bulk delete aman (cascade cpl_bok, cpl_mk, cpl_profil_lulusan
     * via FK, tapi berkat bisaDireset() seharusnya memang tidak ada).
     */
    public function reset(Kurikulum $kurikulum): void
    {
        Cpl::query()->where('kurikulum_id', $kurikulum->id)->delete();
    }

    /**
     * Aman direset hanya bila BELUM ADA satu pun CPL kurikulum ini yang
     * sudah dipetakan ke Profil Lulusan atau BoK.
     */
    public function bisaDireset(Kurikulum $kurikulum): bool
    {
        return Cpl::query()
            ->where('kurikulum_id', $kurikulum->id)
            ->where(fn ($query) => $query->whereHas('cplProfilLulusan')->orWhereHas('cplBoks'))
            ->doesntExist();
    }
}
