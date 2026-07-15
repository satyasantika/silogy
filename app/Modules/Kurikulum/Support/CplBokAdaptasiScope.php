<?php

namespace App\Modules\Kurikulum\Support;

use App\Modules\BoK\Models\Bok;
use App\Modules\BoK\Models\BokKodeOverride;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplKodeOverride;
use App\Modules\CPL\Models\CplMk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Konsekuensi adaptasi MK lintas unit: satu-satunya jalur dari MK yang
 * diadaptasi prodi menuju CPL/BoK milik unit asal (universitas/fakultas)
 * adalah mk_units.mk_id -> cpl_mk.mk_id -> cpl_mk.cpl_bok_id ->
 * cpl_bok.{cpl_id,bok_id}. Kelas ini adalah satu-satunya sumber query
 * untuk "apa saja yang terlihat/bisa diedit" lewat rantai tsb — dipakai
 * bersama oleh CplResource, BokResource, CplBokMatrix, CplMkMatrix, dan
 * ProfilCplMatrix, supaya aturannya konsisten di semua tempat.
 *
 * Kaidah keteredit-an yang berlaku seragam: suatu record/pasangan bisa
 * diedit oleh unit tertentu HANYA JIKA setidaknya satu sisinya benar-benar
 * milik unit tsb (bukan sekadar "terlihat" lewat rantai adaptasi).
 */
class CplBokAdaptasiScope
{
    /**
     * MK yang diadaptasi (aktif) oleh unit tertentu.
     *
     * @return Collection<int, string>
     */
    public static function adaptedMkIds(string $unitId): Collection
    {
        return MkUnit::query()
            ->where('academic_unit_id', $unitId)
            ->where('is_active', true)
            ->pluck('mk_id')
            ->unique()
            ->values();
    }

    /**
     * Baris cpl_bok yang terjangkau lewat MK yang diadaptasi unit tsb.
     *
     * @return Collection<int, string>
     */
    public static function adaptedCplBokIds(string $unitId): Collection
    {
        $mkIds = self::adaptedMkIds($unitId);

        if ($mkIds->isEmpty()) {
            return collect();
        }

        return CplMk::query()
            ->whereIn('mk_id', $mkIds)
            ->pluck('cpl_bok_id')
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    public static function adaptedCplIds(string $unitId): Collection
    {
        $cplBokIds = self::adaptedCplBokIds($unitId);

        if ($cplBokIds->isEmpty()) {
            return collect();
        }

        return CplBok::query()
            ->whereIn('id', $cplBokIds)
            ->pluck('cpl_id')
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    public static function adaptedBokIds(string $unitId): Collection
    {
        $cplBokIds = self::adaptedCplBokIds($unitId);

        if ($cplBokIds->isEmpty()) {
            return collect();
        }

        return CplBok::query()
            ->whereIn('id', $cplBokIds)
            ->pluck('bok_id')
            ->unique()
            ->values();
    }

    /**
     * @param  Builder<Cpl>  $query
     * @return Builder<Cpl>
     */
    public static function scopeVisibleCpl(Builder $query, string $unitId): Builder
    {
        $adapted = self::adaptedCplIds($unitId);

        return $query->where(
            fn (Builder $scoped): Builder => $scoped
                ->where('academic_unit_id', $unitId)
                ->when(
                    $adapted->isNotEmpty(),
                    fn (Builder $q): Builder => $q->orWhereIn('id', $adapted),
                ),
        );
    }

    /**
     * @param  Builder<Bok>  $query
     * @return Builder<Bok>
     */
    public static function scopeVisibleBok(Builder $query, string $unitId): Builder
    {
        $adapted = self::adaptedBokIds($unitId);

        return $query->where(
            fn (Builder $scoped): Builder => $scoped
                ->where('academic_unit_id', $unitId)
                ->when(
                    $adapted->isNotEmpty(),
                    fn (Builder $q): Builder => $q->orWhereIn('id', $adapted),
                ),
        );
    }

    /**
     * Pivot cpl_bok yang terlihat oleh unit: cpl-nya milik unit, atau
     * bok-nya milik unit, atau pivotnya sendiri terjangkau lewat adaptasi.
     *
     * @param  Builder<CplBok>  $query
     * @return Builder<CplBok>
     */
    public static function scopeVisibleCplBok(Builder $query, string $unitId): Builder
    {
        $ownCplIds = Cpl::query()->where('academic_unit_id', $unitId)->pluck('id');
        $ownBokIds = Bok::query()->where('academic_unit_id', $unitId)->pluck('id');
        $adapted = self::adaptedCplBokIds($unitId);

        return $query->where(function (Builder $scoped) use ($ownCplIds, $ownBokIds, $adapted): void {
            $scoped->whereIn('cpl_id', $ownCplIds)
                ->orWhereIn('bok_id', $ownBokIds)
                ->when(
                    $adapted->isNotEmpty(),
                    fn (Builder $q): Builder => $q->orWhereIn('id', $adapted),
                );
        });
    }

    /**
     * "Minimal satu sisi milik saya" — primitif keteredit-an dasar.
     */
    public static function pairEditable(string $unitA, string $unitB, string $viewingUnitId): bool
    {
        return $unitA === $viewingUnitId || $unitB === $viewingUnitId;
    }

    public static function canToggleCplBok(Cpl $cpl, Bok $bok, string $viewingUnitId): bool
    {
        return self::pairEditable($cpl->academic_unit_id, $bok->academic_unit_id, $viewingUnitId);
    }

    public static function canEditCplMkCell(Mk $mk, CplBok $cplBok, string $viewingUnitId): bool
    {
        if ($mk->academic_unit_id === $viewingUnitId) {
            return true;
        }

        $cplBok->loadMissing(['cpl', 'bok']);

        return self::canToggleCplBok($cplBok->cpl, $cplBok->bok, $viewingUnitId);
    }

    /**
     * Guard pertahanan-berlapis untuk toggle()/updateBobot(): id mentah
     * dari client wajib divalidasi terhadap semesta yang benar-benar
     * terlihat oleh unit ybs, jangan hanya mengandalkan atribut disabled
     * di blade.
     */
    public static function isVisiblePair(string $cplId, string $bokId, string $viewingUnitId): bool
    {
        return self::scopeVisibleCpl(Cpl::query(), $viewingUnitId)->whereKey($cplId)->exists()
            && self::scopeVisibleBok(Bok::query(), $viewingUnitId)->whereKey($bokId)->exists();
    }

    public static function isVisibleMkCplBokCell(string $mkId, string $cplBokId, string $viewingUnitId): bool
    {
        $mkVisible = Mk::query()->where('id', $mkId)->where('academic_unit_id', $viewingUnitId)->exists()
            || self::adaptedMkIds($viewingUnitId)->contains($mkId);

        if (! $mkVisible) {
            return false;
        }

        return self::scopeVisibleCplBok(CplBok::query(), $viewingUnitId)->whereKey($cplBokId)->exists();
    }

    /**
     * Peta kode tampilan (override-aware), dihitung sekaligus (batched)
     * untuk menghindari N+1 di tabel/matriks.
     *
     * @param  Collection<int, Cpl>  $cpls
     * @return Collection<string, string>
     */
    public static function displayKodeMapCpl(Collection $cpls, string $viewingUnitId): Collection
    {
        $foreignIds = $cpls
            ->filter(fn (Cpl $cpl): bool => $cpl->academic_unit_id !== $viewingUnitId)
            ->pluck('id');

        $overrides = $foreignIds->isEmpty()
            ? collect()
            : CplKodeOverride::query()
                ->where('academic_unit_id', $viewingUnitId)
                ->whereIn('cpl_id', $foreignIds)
                ->pluck('kode', 'cpl_id');

        return $cpls->mapWithKeys(fn (Cpl $cpl): array => [
            $cpl->id => $overrides[$cpl->id] ?? $cpl->kode,
        ]);
    }

    /**
     * @param  Collection<int, Bok>  $boks
     * @return Collection<string, string>
     */
    public static function displayKodeMapBok(Collection $boks, string $viewingUnitId): Collection
    {
        $foreignIds = $boks
            ->filter(fn (Bok $bok): bool => $bok->academic_unit_id !== $viewingUnitId)
            ->pluck('id');

        $overrides = $foreignIds->isEmpty()
            ? collect()
            : BokKodeOverride::query()
                ->where('academic_unit_id', $viewingUnitId)
                ->whereIn('bok_id', $foreignIds)
                ->pluck('kode', 'bok_id');

        return $boks->mapWithKeys(fn (Bok $bok): array => [
            $bok->id => $overrides[$bok->id] ?? $bok->kode,
        ]);
    }
}
