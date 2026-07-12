<?php

namespace App\Modules\Penilaian\Observers;

use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;
use App\Modules\Penilaian\Services\SubcpmkAsesmenPemetaanService;

/**
 * Setiap interaksi Sub-CPMK ↔ penugasan (asesmen) berubah — dipetakan,
 * bobot pivotnya diubah, atau pemetaannya dihapus — bobot Sub-CPMK yang
 * bersangkutan dihitung ulang otomatis dari seluruh interaksinya.
 */
class SubcpmkKomponenPenilaianObserver
{
    public function saved(SubcpmkKomponenPenilaian $pivot): void
    {
        SubcpmkAsesmenPemetaanService::recalculateBobotSubcpmk($pivot->subcpmk_id);
    }

    public function deleted(SubcpmkKomponenPenilaian $pivot): void
    {
        SubcpmkAsesmenPemetaanService::recalculateBobotSubcpmk($pivot->subcpmk_id);
    }
}
