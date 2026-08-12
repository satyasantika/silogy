<?php

namespace App\Modules\Penilaian\Services;

use App\Modules\MK\Models\Mk;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use Illuminate\Database\Eloquent\Builder;

class KomponenPenilaianResetService
{
    /**
     * Kosongkan Asesmen (KomponenPenilaian) MK ini pada semester tertentu
     * saja — mengikuti cakupan yang tampil di tabel ListKomponenPenilaians
     * (MK + semester terpilih). Tidak ada Observer terdaftar pada
     * KomponenPenilaian — bulk delete aman.
     */
    public function reset(Mk $mk, string $semesterId): void
    {
        $this->scopedQuery($mk, $semesterId)->delete();
    }

    /**
     * Aman direset hanya bila BELUM ADA satu pun Asesmen pada cakupan ini
     * yang sudah dipetakan ke Sub-CPMK.
     */
    public function bisaDireset(Mk $mk, string $semesterId): bool
    {
        return $this->scopedQuery($mk, $semesterId)
            ->whereHas('subcpmkKomponens')
            ->doesntExist();
    }

    /**
     * @return Builder<KomponenPenilaian>
     */
    protected function scopedQuery(Mk $mk, string $semesterId): Builder
    {
        return KomponenPenilaian::query()
            ->where('mk_id', $mk->id)
            ->where('semester_id', $semesterId);
    }
}
