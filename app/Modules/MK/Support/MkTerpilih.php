<?php

namespace App\Modules\MK\Support;

use App\Models\User;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Filament\Resources\MataKuliahKoordinatorResource;
use App\Modules\MK\Filament\Support\Concerns\HasKoordinatorMkScope;
use App\Modules\MK\Models\Mk;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

/**
 * Mata kuliah yang sedang dikerjakan koordinator MK — dipilih lewat card
 * pada halaman Mata Kuliah Koordinator, tersimpan di session.
 */
class MkTerpilih
{
    use HasKoordinatorMkScope;

    public const SESSION_KEY = 'silogy_mk_terpilih';

    public static function current(): ?Mk
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return null;
        }

        $id = session()->get(self::SESSION_KEY);

        if (blank($id)) {
            return null;
        }

        $mk = static::scopedQuery($user)->with('mkUnits')->find($id);

        if (! $mk instanceof Mk) {
            return null;
        }

        $kurikulum = KurikulumTerpilih::current();

        if ($kurikulum instanceof Kurikulum && ! static::mkDitawarkanPadaKurikulum($mk, $kurikulum)) {
            return null;
        }

        return $mk;
    }

    public static function currentId(): ?string
    {
        return static::current()?->id;
    }

    public static function set(?string $id): void
    {
        if (blank($id)) {
            session()->forget(self::SESSION_KEY);

            return;
        }

        $user = auth()->user();

        if (! $user instanceof User) {
            return;
        }

        if (static::scopedQuery($user)->whereKey($id)->exists()) {
            session()->put(self::SESSION_KEY, $id);
        }
    }

    public static function label(Mk $mk, ?Kurikulum $kurikulum = null): string
    {
        $kurikulum ??= KurikulumTerpilih::current();

        if ($kurikulum instanceof Kurikulum) {
            $mk->loadMissing('mkUnits');
            $kode = $mk->mkUnits
                ->firstWhere('academic_unit_id', $kurikulum->academic_unit_id)
                ?->kode;

            if (filled($kode)) {
                return "{$mk->nama} ({$kode})";
            }
        }

        return $mk->nama;
    }

    /**
     * Banner konteks MK terpilih untuk halaman CPMK, Sub-CPMK, Asesmen, dan matriks interaksi.
     */
    public static function bannerHtml(): HtmlString
    {
        return new HtmlString(view('filament.modules.mk.partials.mk-terpilih-banner-inner', [
            'gantiUrl' => MataKuliahKoordinatorResource::getUrl('index'),
            'mk' => static::current(),
            'kurikulum' => KurikulumTerpilih::current(),
        ])->render());
    }

    public static function mkDitawarkanPadaKurikulum(Mk $mk, Kurikulum $kurikulum): bool
    {
        $mk->loadMissing('mkUnits');

        return $mk->mkUnits
            ->where('academic_unit_id', $kurikulum->academic_unit_id)
            ->where('is_active', true)
            ->isNotEmpty();
    }

    /**
     * @return Builder<Mk>
     */
    protected static function scopedQuery(User $user): Builder
    {
        $query = Mk::query();

        if ($user->hasRole(['Super Admin', 'Auditor Mutu'])) {
            return $query;
        }

        $mkIds = static::scopedKoordinatorMkIds($user);

        if ($mkIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('id', $mkIds);
    }
}
