<?php

namespace App\Modules\MK\Services;

use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\CpmkSemester;
use App\Modules\MK\Models\Mk;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CpmkResetService
{
    /**
     * Kosongkan CPMK MK ini pada semester tertentu saja.
     *
     * Seperti Sub-CPMK dan Asesmen, yang dilepas adalah LAMPIRAN semester,
     * bukan barisnya: satu CPMK dipakai ulang lintas semester, jadi
     * menghapusnya akan ikut menghapus CPMK semester lain beserta seluruh
     * turunannya (mk_cpmk → subcpmk → nilai) lewat cascade FK. Baris CPMK
     * baru benar-benar dihapus bila tidak lagi terlampir di semester mana
     * pun dan belum dipetakan ke CPL-MK.
     */
    public function reset(Mk $mk, string $semesterId): void
    {
        DB::transaction(function () use ($mk, $semesterId): void {
            $cpmkIds = $this->scopedQuery($mk, $semesterId)->pluck('id');

            if ($cpmkIds->isEmpty()) {
                return;
            }

            CpmkSemester::query()
                ->whereIn('cpmk_id', $cpmkIds)
                ->where('semester_id', $semesterId)
                ->delete();

            Cpmk::query()
                ->whereIn('id', $cpmkIds)
                ->whereDoesntHave('semesters')
                ->whereDoesntHave('mkCpmks')
                ->delete();
        });
    }

    /**
     * Aman direset hanya bila BELUM ADA satu pun CPMK MK ini pada semester
     * tersebut yang sudah dipetakan ke CPL-MK (mk_cpmk) — pemetaan itu tidak
     * mungkin ada tanpa mk_cpmk, jadi cek ini otomatis juga menjamin tidak
     * ada Sub-CPMK.
     */
    public function bisaDireset(Mk $mk, string $semesterId): bool
    {
        return $this->scopedQuery($mk, $semesterId)
            ->whereHas('mkCpmks')
            ->doesntExist();
    }

    /**
     * @return Builder<Cpmk>
     */
    protected function scopedQuery(Mk $mk, string $semesterId): Builder
    {
        return Cpmk::query()
            ->where('mk_id', $mk->id)
            ->untukSemester($semesterId);
    }
}
