<?php

namespace App\Modules\MK\Services;

use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\MK\Models\SubcpmkSemester;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SubcpmkResetService
{
    /**
     * Kosongkan Sub-CPMK MK ini pada semester tertentu saja — mengikuti
     * cakupan yang tampil di tabel ListSubcpmks (MK + semester terpilih),
     * BUKAN seluruh MK lintas semester seperti reset gabungan di EditMk.
     *
     * Yang dilakukan adalah MELEPAS LAMPIRAN semester, bukan menghapus baris.
     * Sejak satu baris Sub-CPMK bisa dipakai ulang di beberapa semester,
     * menghapusnya akan ikut menghapus Sub-CPMK semester lain beserta
     * nilainya. Baris baru benar-benar dihapus bila tidak lagi terlampir di
     * semester mana pun dan belum pernah dipetakan ke Asesmen.
     */
    public function reset(Mk $mk, string $semesterId): void
    {
        DB::transaction(function () use ($mk, $semesterId): void {
            $subcpmkIds = $this->scopedQuery($mk, $semesterId)->pluck('id');

            if ($subcpmkIds->isEmpty()) {
                return;
            }

            SubcpmkSemester::query()
                ->whereIn('subcpmk_id', $subcpmkIds)
                ->where('semester_id', $semesterId)
                ->delete();

            Subcpmk::query()
                ->whereIn('id', $subcpmkIds)
                ->whereDoesntHave('semesters')
                ->whereDoesntHave('subcpmkKomponens')
                ->delete();
        });
    }

    /**
     * Aman direset hanya bila BELUM ADA satu pun Sub-CPMK pada cakupan ini
     * yang sudah dipetakan ke Asesmen PADA SEMESTER INI. Pemetaan di
     * semester lain tidak menghalangi — melepas lampiran semester ini tidak
     * menyentuhnya.
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
     * @return Builder<Subcpmk>
     */
    protected function scopedQuery(Mk $mk, string $semesterId): Builder
    {
        return Subcpmk::query()
            ->untukSemester($semesterId)
            ->whereHas('mkCpmk.cpmk', fn ($query) => $query->where('mk_id', $mk->id));
    }
}
