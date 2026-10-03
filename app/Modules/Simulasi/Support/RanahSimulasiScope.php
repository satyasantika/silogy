<?php

namespace App\Modules\Simulasi\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Memagari data inti dan data sandbox agar tidak saling terlihat.
 *
 * Penanda kepemilikan (`sandbox_id`) hanya ada di tiga tabel akar: unit,
 * pengguna, mahasiswa. Model lain mengikuti akarnya lewat subquery:
 *
 *  - langsung : baris itu sendiri membawa sandbox_id.
 *  - unit     : kolom academic_unit_id menunjuk unit milik sandbox.
 *  - mk_unit  : kolom mk_unit_id menunjuk MkUnit milik unit sandbox.
 *  - mk       : kolom mk_id menunjuk MK milik unit sandbox (CPMK, komponen).
 *  - mk_cpmk  : kolom mk_cpmk_id menunjuk penghubung MK–CPMK milik MK sandbox.
 *  - pelaku   : kolom causer_id menunjuk pengguna milik sandbox (log audit).
 *
 * Ranah inti memakai NOT IN pada himpunan sandbox (kecil, terindeks) sehingga
 * tanpa data sandbox biayanya nyaris nol; ranah sandbox memakai IN pada
 * himpunan miliknya sendiri.
 *
 * @implements Scope<Model>
 */
final class RanahSimulasiScope implements Scope
{
    /**
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $ranah = Ranah::aktif();

        if ($ranah === null) {
            return;
        }

        $sandboxId = $ranah[0];
        $kolom = $model->qualifyColumn((string) constant($model::class.'::RANAH_KOLOM'));

        match ((string) constant($model::class.'::RANAH_MODE')) {
            'langsung' => $this->langsung($builder, $kolom, $sandboxId),
            'unit' => $this->unit($builder, $kolom, $sandboxId),
            'mk_unit' => $this->mkUnit($builder, $kolom, $sandboxId),
            'mk' => $this->lewat($builder, $kolom, $sandboxId, self::mkSandbox(...)),
            'mk_cpmk' => $this->lewat($builder, $kolom, $sandboxId, self::mkCpmkSandbox(...)),
            'pelaku' => $this->pelaku($builder, $kolom, $sandboxId),
            default => throw new \LogicException('RANAH_MODE tidak dikenal pada '.$model::class),
        };
    }

    /**
     * @param  Builder<covariant Model>  $builder
     */
    private function langsung(Builder $builder, string $kolom, ?string $sandboxId): void
    {
        $sandboxId === null
            ? $builder->whereNull($kolom)
            : $builder->where($kolom, $sandboxId);
    }

    /**
     * @param  Builder<covariant Model>  $builder
     */
    private function unit(Builder $builder, string $kolom, ?string $sandboxId): void
    {
        if ($sandboxId === null) {
            $builder->where(fn (Builder $q) => $q
                ->whereNull($kolom)
                ->orWhereNotIn($kolom, self::unitSandbox()));

            return;
        }

        $builder->whereIn($kolom, self::unitSandbox($sandboxId));
    }

    /**
     * @param  Builder<covariant Model>  $builder
     */
    private function mkUnit(Builder $builder, string $kolom, ?string $sandboxId): void
    {
        if ($sandboxId === null) {
            $builder->where(fn (Builder $q) => $q
                ->whereNull($kolom)
                ->orWhereNotIn($kolom, self::mkUnitSandbox()));

            return;
        }

        $builder->whereIn($kolom, self::mkUnitSandbox($sandboxId));
    }

    /**
     * Pola umum untuk model anak yang kepemilikannya diturunkan lewat rantai
     * tabel: inti = bukan milik sandbox mana pun, sandbox = milik sandbox ini.
     *
     * @param  Builder<covariant Model>  $builder
     * @param  callable(?string): QueryBuilder  $himpunan
     */
    private function lewat(Builder $builder, string $kolom, ?string $sandboxId, callable $himpunan): void
    {
        if ($sandboxId === null) {
            $builder->where(fn (Builder $q) => $q
                ->whereNull($kolom)
                ->orWhereNotIn($kolom, $himpunan(null)));

            return;
        }

        $builder->whereIn($kolom, $himpunan($sandboxId));
    }

    /**
     * @param  Builder<covariant Model>  $builder
     */
    private function pelaku(Builder $builder, string $kolom, ?string $sandboxId): void
    {
        if ($sandboxId === null) {
            $builder->where(fn (Builder $q) => $q
                ->whereNull($kolom)
                ->orWhereNotIn($kolom, self::penggunaSandbox()));

            return;
        }

        $builder->whereIn($kolom, self::penggunaSandbox($sandboxId));
    }

    /** Unit milik satu sandbox, atau milik sandbox mana pun bila $sandboxId null. */
    public static function unitSandbox(?string $sandboxId = null): QueryBuilder
    {
        $query = DB::table('academic_units')->select('id');

        return $sandboxId === null
            ? $query->whereNotNull('sandbox_id')
            : $query->where('sandbox_id', $sandboxId);
    }

    public static function mkUnitSandbox(?string $sandboxId = null): QueryBuilder
    {
        return DB::table('mk_units')
            ->select('id')
            ->whereIn('academic_unit_id', self::unitSandbox($sandboxId));
    }

    /** MK milik unit sandbox. */
    public static function mkSandbox(?string $sandboxId = null): QueryBuilder
    {
        return DB::table('mk')
            ->select('id')
            ->whereIn('academic_unit_id', self::unitSandbox($sandboxId));
    }

    /** Baris mk_cpmk (penghubung MK–CPMK) milik MK sandbox, lewat cpmk.mk_id. */
    public static function mkCpmkSandbox(?string $sandboxId = null): QueryBuilder
    {
        return DB::table('mk_cpmk')
            ->select('id')
            ->whereIn('cpmk_id', DB::table('cpmk')->select('id')->whereIn('mk_id', self::mkSandbox($sandboxId)));
    }

    public static function penggunaSandbox(?string $sandboxId = null): QueryBuilder
    {
        $query = DB::table('users')->select('id');

        return $sandboxId === null
            ? $query->whereNotNull('sandbox_id')
            : $query->where('sandbox_id', $sandboxId);
    }
}
