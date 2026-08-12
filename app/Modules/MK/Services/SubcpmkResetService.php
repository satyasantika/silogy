<?php

namespace App\Modules\MK\Services;

use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\Subcpmk;
use Illuminate\Database\Eloquent\Builder;

class SubcpmkResetService
{
    /**
     * Kosongkan Sub-CPMK MK ini pada semester tertentu saja — mengikuti
     * cakupan yang tampil di tabel ListSubcpmks (MK + semester terpilih),
     * BUKAN seluruh MK lintas semester seperti reset gabungan di EditMk.
     * Tidak ada Observer terdaftar pada Subcpmk — bulk delete aman.
     */
    public function reset(Mk $mk, string $semesterId): void
    {
        $this->scopedQuery($mk, $semesterId)->delete();
    }

    /**
     * Aman direset hanya bila BELUM ADA satu pun Sub-CPMK pada cakupan ini
     * yang sudah dipetakan ke Asesmen (KomponenPenilaian).
     */
    public function bisaDireset(Mk $mk, string $semesterId): bool
    {
        return $this->scopedQuery($mk, $semesterId)
            ->whereHas('subcpmkKomponens')
            ->doesntExist();
    }

    /**
     * @return Builder<Subcpmk>
     */
    protected function scopedQuery(Mk $mk, string $semesterId): Builder
    {
        return Subcpmk::query()
            ->where('semester_id', $semesterId)
            ->whereHas('mkCpmk.cpmk', fn ($query) => $query->where('mk_id', $mk->id));
    }
}
