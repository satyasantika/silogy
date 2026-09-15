<?php

namespace App\Modules\Penilaian\Services;

use App\Modules\MK\Models\Mk;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Models\KomponenPenilaianSemester;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class KomponenPenilaianResetService
{
    /**
     * Kosongkan Asesmen (KomponenPenilaian) MK ini pada semester tertentu
     * saja — mengikuti cakupan yang tampil di tabel ListKomponenPenilaians
     * (MK + semester terpilih).
     *
     * Sama seperti SubcpmkResetService: yang dilepas adalah LAMPIRAN
     * semester, bukan barisnya. Menghapus baris yang dipakai ulang akan ikut
     * menghapus Asesmen semester lain beserta nilai mahasiswanya.
     */
    public function reset(Mk $mk, string $semesterId): void
    {
        DB::transaction(function () use ($mk, $semesterId): void {
            $komponenIds = $this->scopedQuery($mk, $semesterId)->pluck('id');

            if ($komponenIds->isEmpty()) {
                return;
            }

            KomponenPenilaianSemester::query()
                ->whereIn('komponen_penilaian_id', $komponenIds)
                ->where('semester_id', $semesterId)
                ->delete();

            KomponenPenilaian::query()
                ->whereIn('id', $komponenIds)
                ->whereDoesntHave('semesters')
                ->whereDoesntHave('subcpmkKomponens')
                ->delete();
        });
    }

    /**
     * Aman direset hanya bila BELUM ADA satu pun Asesmen pada cakupan ini
     * yang sudah dipetakan ke Sub-CPMK PADA SEMESTER INI.
     */
    public function bisaDireset(Mk $mk, string $semesterId): bool
    {
        return $this->scopedQuery($mk, $semesterId)
            ->whereHas(
                'subcpmkKomponens',
                fn (Builder $pemetaan): Builder => $pemetaan->where('semester_id', $semesterId),
            )
            ->doesntExist();
    }

    /**
     * @return Builder<KomponenPenilaian>
     */
    protected function scopedQuery(Mk $mk, string $semesterId): Builder
    {
        return KomponenPenilaian::query()
            ->where('mk_id', $mk->id)
            ->untukSemester($semesterId);
    }
}
