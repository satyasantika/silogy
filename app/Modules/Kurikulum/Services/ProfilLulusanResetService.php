<?php

namespace App\Modules\Kurikulum\Services;

use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Models\ProfilLulusan;

class ProfilLulusanResetService
{
    /**
     * Kosongkan seluruh Profil Lulusan kurikulum ini (cascade Indikator).
     * Tidak ada Observer terdaftar pada ProfilLulusan — bulk delete aman.
     */
    public function reset(Kurikulum $kurikulum): void
    {
        ProfilLulusan::query()->where('kurikulum_id', $kurikulum->id)->delete();
    }

    /**
     * Aman direset hanya bila BELUM ADA satu pun Profil Lulusan kurikulum
     * ini yang sudah dipetakan ke CPL — menghapus tanpa cek ini akan
     * mengorbankan pemetaan CPL yang sudah dibangun di atasnya.
     */
    public function bisaDireset(Kurikulum $kurikulum): bool
    {
        return ProfilLulusan::query()
            ->where('kurikulum_id', $kurikulum->id)
            ->whereHas('cplProfilLulusan')
            ->doesntExist();
    }
}
