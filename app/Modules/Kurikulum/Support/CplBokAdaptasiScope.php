<?php

namespace App\Modules\Kurikulum\Support;

use App\Modules\BoK\Models\Bok;
use App\Modules\BoK\Models\BokKodeOverride;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplKodeOverride;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Konsekuensi adaptasi MK lintas unit: jalur dari MK yang diadaptasi
 * (mk_units milik kurikulum penawar) menuju CPL/BoK unit asal adalah
 * mk_units.mk_id -> cpl_mk.mk_id -> cpl_bok -> cpl/bok.
 *
 * Visibility daftar/matriks di-scope per kurikulum_id. Hak edit pasangan
 * CPL x BoK (canToggleCplBok/pairEditable) berbasis kepemilikan
 * academic_unit dengan aturan "minimal satu sisi milik unit saya" — TAPI
 * hak edit bobot CPL x MK (canEditCplMkCell) berbeda, lihat docblock
 * method tsb.
 */
class CplBokAdaptasiScope
{
    /**
     * MK yang diadaptasi (aktif) oleh kurikulum tertentu.
     *
     * @return Collection<int, string>
     */
    public static function adaptedMkIds(string $kurikulumId): Collection
    {
        return MkUnit::query()
            ->where('kurikulum_id', $kurikulumId)
            ->where('is_active', true)
            ->pluck('mk_id')
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    public static function adaptedCplBokIds(string $kurikulumId): Collection
    {
        $mkIds = self::adaptedMkIds($kurikulumId);

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
    public static function adaptedCplIds(string $kurikulumId): Collection
    {
        $cplBokIds = self::adaptedCplBokIds($kurikulumId);

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
    public static function adaptedBokIds(string $kurikulumId): Collection
    {
        $cplBokIds = self::adaptedCplBokIds($kurikulumId);

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
     * Agregat adaptasi lintas semua kurikulum milik satu unit (untuk
     * policy / getEloquentQuery yang belum punya konteks kurikulum tunggal).
     *
     * @return Collection<int, string>
     */
    public static function adaptedCplIdsAcrossUnit(string $unitId): Collection
    {
        return Kurikulum::query()
            ->where('academic_unit_id', $unitId)
            ->pluck('id')
            ->flatMap(fn (string $kurikulumId): Collection => self::adaptedCplIds($kurikulumId))
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    public static function adaptedBokIdsAcrossUnit(string $unitId): Collection
    {
        return Kurikulum::query()
            ->where('academic_unit_id', $unitId)
            ->pluck('id')
            ->flatMap(fn (string $kurikulumId): Collection => self::adaptedBokIds($kurikulumId))
            ->unique()
            ->values();
    }

    /**
     * @param  Builder<Cpl>  $query
     * @return Builder<Cpl>
     */
    public static function scopeVisibleCpl(Builder $query, string $kurikulumId): Builder
    {
        $adapted = self::adaptedCplIds($kurikulumId);

        return $query->where(
            fn (Builder $scoped): Builder => $scoped
                ->where('kurikulum_id', $kurikulumId)
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
    public static function scopeVisibleBok(Builder $query, string $kurikulumId): Builder
    {
        $adapted = self::adaptedBokIds($kurikulumId);

        return $query->where(
            fn (Builder $scoped): Builder => $scoped
                ->where('kurikulum_id', $kurikulumId)
                ->when(
                    $adapted->isNotEmpty(),
                    fn (Builder $q): Builder => $q->orWhereIn('id', $adapted),
                ),
        );
    }

    /**
     * @param  Builder<CplBok>  $query
     * @return Builder<CplBok>
     */
    public static function scopeVisibleCplBok(Builder $query, string $kurikulumId): Builder
    {
        $ownCplIds = Cpl::query()->where('kurikulum_id', $kurikulumId)->pluck('id');
        $ownBokIds = Bok::query()->where('kurikulum_id', $kurikulumId)->pluck('id');
        $adapted = self::adaptedCplBokIds($kurikulumId);

        return $query->where(function (Builder $scoped) use ($ownCplIds, $ownBokIds, $adapted): void {
            $scoped->whereIn('cpl_id', $ownCplIds)
                ->orWhereIn('bok_id', $ownBokIds)
                ->when(
                    $adapted->isNotEmpty(),
                    fn (Builder $q): Builder => $q->orWhereIn('id', $adapted),
                );
        });
    }

    public static function pairEditable(string $unitA, string $unitB, string $viewingUnitId): bool
    {
        return $unitA === $viewingUnitId || $unitB === $viewingUnitId;
    }

    public static function canToggleCplBok(Cpl $cpl, Bok $bok, string $viewingUnitId): bool
    {
        return self::pairEditable($cpl->academic_unit_id, $bok->academic_unit_id, $viewingUnitId);
    }

    /**
     * Bobot CPL ↔ MK hanya bisa diedit oleh unit yang benar-benar memiliki
     * MK tsb — beda dari canToggleCplBok()/pairEditable() (dipakai
     * matriks CPL ↔ BoK), yang mengizinkan edit bila SALAH SATU sisi
     * (CPL/BoK) milik unit yang melihat. MK universitas/fakultas yang
     * tersingkap lewat adaptasi TIDAK "diseting" oleh Tim Kurikulum
     * Prodi — jadi walau kolom CPL/BoK tujuannya milik prodi, sel tetap
     * terkunci/baca-saja bagi prodi.
     */
    public static function canEditCplMkCell(Mk $mk, string $viewingUnitId): bool
    {
        return $mk->academic_unit_id === $viewingUnitId;
    }

    public static function isVisiblePair(string $cplId, string $bokId, string $kurikulumId): bool
    {
        return self::scopeVisibleCpl(Cpl::query(), $kurikulumId)->whereKey($cplId)->exists()
            && self::scopeVisibleBok(Bok::query(), $kurikulumId)->whereKey($bokId)->exists();
    }

    public static function isVisibleMkCplBokCell(string $mkId, string $cplBokId, string $kurikulumId): bool
    {
        $mkVisible = Mk::query()->where('id', $mkId)->where('kurikulum_id', $kurikulumId)->exists()
            || self::adaptedMkIds($kurikulumId)->contains($mkId);

        if (! $mkVisible) {
            return false;
        }

        return self::scopeVisibleCplBok(CplBok::query(), $kurikulumId)->whereKey($cplBokId)->exists();
    }

    /**
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

    /**
     * ORDER BY gabungan urutan-milik-sendiri + urutan-override-adaptasi,
     * dihitung di SQL (bukan di PHP) supaya tetap benar saat tabel
     * dipaginasi. Subquery berkorelasi dipakai (bukan JOIN) supaya kolom
     * `kode` yang sama-sama ada di cpl/cpl_kode_overrides tidak jadi
     * ambigu terhadap TextColumn::make('kode')->searchable() pada tabel.
     * NULL (belum pernah direorder manual) selalu di akhir.
     *
     * @param  Builder<Cpl>  $query
     * @return Builder<Cpl>
     */
    public static function applyDisplayOrderCpl(Builder $query, Kurikulum $kurikulum): Builder
    {
        return self::applyDisplayOrder($query, $kurikulum, 'cpl', 'cpl_kode_overrides', 'cpl_id');
    }

    /**
     * @param  Builder<Bok>  $query
     * @return Builder<Bok>
     */
    public static function applyDisplayOrderBok(Builder $query, Kurikulum $kurikulum): Builder
    {
        return self::applyDisplayOrder($query, $kurikulum, 'bok', 'bok_kode_overrides', 'bok_id');
    }

    /**
     * @param  Builder<Cpl>|Builder<Bok>  $query
     * @return Builder<Cpl>|Builder<Bok>
     */
    protected static function applyDisplayOrder(
        Builder $query,
        Kurikulum $kurikulum,
        string $baseTable,
        string $overrideTable,
        string $overrideForeignKey,
    ): Builder {
        $effective = 'COALESCE('
            ."(SELECT o.urutan FROM {$overrideTable} o WHERE o.{$overrideForeignKey} = {$baseTable}.id AND o.academic_unit_id = ? LIMIT 1), "
            ."CASE WHEN {$baseTable}.kurikulum_id = ? THEN {$baseTable}.urutan ELSE NULL END"
        .')';

        return $query->orderByRaw(
            "({$effective}) IS NULL, ({$effective}) ASC, {$baseTable}.kode ASC",
            [
                $kurikulum->academic_unit_id, $kurikulum->id,
                $kurikulum->academic_unit_id, $kurikulum->id,
            ],
        );
    }

    /**
     * Peta id -> urutan efektif (PHP-side mirror of applyDisplayOrder(),
     * untuk kebutuhan uji/inspeksi — sort tabel sesungguhnya tetap pakai
     * applyDisplayOrderCpl()/applyDisplayOrderBok() di SQL demi paginasi
     * yang benar). null berarti "belum diurutkan manual" (tampil paling
     * akhir).
     *
     * @param  Collection<int, Cpl>  $cpls
     * @return Collection<string, ?int>
     */
    public static function displayUrutanMapCpl(Collection $cpls, Kurikulum $viewingKurikulum): Collection
    {
        $foreignIds = $cpls
            ->filter(fn (Cpl $cpl): bool => $cpl->kurikulum_id !== $viewingKurikulum->id)
            ->pluck('id');

        $overrides = $foreignIds->isEmpty()
            ? collect()
            : CplKodeOverride::query()
                ->where('academic_unit_id', $viewingKurikulum->academic_unit_id)
                ->whereIn('cpl_id', $foreignIds)
                ->pluck('urutan', 'cpl_id');

        return $cpls->mapWithKeys(fn (Cpl $cpl): array => [
            $cpl->id => $cpl->kurikulum_id === $viewingKurikulum->id
                ? $cpl->urutan
                : ($overrides[$cpl->id] ?? null),
        ]);
    }

    /**
     * @param  Collection<int, Bok>  $boks
     * @return Collection<string, ?int>
     */
    public static function displayUrutanMapBok(Collection $boks, Kurikulum $viewingKurikulum): Collection
    {
        $foreignIds = $boks
            ->filter(fn (Bok $bok): bool => $bok->kurikulum_id !== $viewingKurikulum->id)
            ->pluck('id');

        $overrides = $foreignIds->isEmpty()
            ? collect()
            : BokKodeOverride::query()
                ->where('academic_unit_id', $viewingKurikulum->academic_unit_id)
                ->whereIn('bok_id', $foreignIds)
                ->pluck('urutan', 'bok_id');

        return $boks->mapWithKeys(fn (Bok $bok): array => [
            $bok->id => $bok->kurikulum_id === $viewingKurikulum->id
                ? $bok->urutan
                : ($overrides[$bok->id] ?? null),
        ]);
    }
}
