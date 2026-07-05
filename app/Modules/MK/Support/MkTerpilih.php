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
        $gantiUrl = MataKuliahKoordinatorResource::getUrl('index');
        $mk = static::current();
        $kurikulum = KurikulumTerpilih::current();

        if (! $mk instanceof Mk) {
            return new HtmlString(
                '<div style="padding:12px 14px;border-radius:8px;background:#fef3c7;border:1px solid #fcd34d;color:#92400e;font-size:13px;line-height:1.55;">'
                .'<div>Belum ada mata kuliah terpilih.</div>'
                .'<div style="margin-top:4px;">'
                .'<a href="'.e($gantiUrl).'" style="font-weight:600;color:#b45309;text-decoration:underline;">Pilih dari halaman Mata Kuliah</a>'
                .'</div>'
                .'</div>'
            );
        }

        $kurikulum?->loadMissing('academicUnit');
        $label = static::label($mk, $kurikulum);
        $kurikulumLabel = $kurikulum instanceof Kurikulum ? $kurikulum->nama : '—';
        $prodiLabel = $kurikulum?->academicUnit?->nama ?? '—';

        return new HtmlString(
            '<div style="padding:12px 14px;border-radius:8px;background:#eff6ff;border:1px solid #bfdbfe;color:#1e3a8a;font-size:13px;line-height:1.55;">'
            .'<div>'
            .'<span style="opacity:.88;">Mata kuliah terpilih:</span> '
            .'<strong>'.e($label).'</strong> '
            .'<a href="'.e($gantiUrl).'" style="margin-left:6px;font-weight:600;color:#1d4ed8;text-decoration:underline;">Ganti</a>'
            .'</div>'
            .'<div style="margin-top:6px;opacity:.92;">Kurikulum: <strong>'.e($kurikulumLabel).'</strong></div>'
            .'<div style="margin-top:2px;opacity:.92;">Program studi: <strong>'.e($prodiLabel).'</strong></div>'
            .'</div>'
        );
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
