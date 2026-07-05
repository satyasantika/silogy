<?php

namespace App\Modules\Kurikulum\Support;

use App\Models\User;
use App\Modules\Auth\Support\ActiveRole;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\Kurikulum\Filament\Resources\KurikulumResource;
use App\Modules\Kurikulum\Models\Kurikulum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

/**
 * Kurikulum yang sedang dikerjakan tim kurikulum — dipilih lewat filter
 * kurikulum pada halaman Profil/CPL/BoK/MK/Penawaran MK/Kelas MK atau
 * widget dashboard, tersimpan di session.
 *
 * Default: kurikulum aktif pada unit ter-scope user; bila user menjadi
 * tim kurikulum di beberapa level sekaligus, diambil kurikulum aktif
 * pada unit TERENDAH (prodi → jurusan → fakultas → universitas).
 */
class KurikulumTerpilih
{
    public const SESSION_KEY = 'silogy_kurikulum_terpilih';

    /** Urutan kedalaman unit: terendah lebih dulu. */
    protected const URUTAN_UNIT_TERENDAH = ['study_program', 'department', 'faculty', 'university'];

    public static function current(): ?Kurikulum
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return null;
        }

        $id = session()->get(self::SESSION_KEY);

        if (filled($id)) {
            $kurikulum = static::scopedQuery($user)->with('academicUnit')->find($id);

            if ($kurikulum) {
                return $kurikulum;
            }
        }

        return static::default($user);
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

        if ($user instanceof User && static::scopedQuery($user)->whereKey($id)->exists()) {
            session()->put(self::SESSION_KEY, $id);
        }
    }

    public static function default(User $user): ?Kurikulum
    {
        $kandidat = static::scopedQuery($user)
            ->with('academicUnit')
            ->where('is_active', true)
            ->get()
            ->sortBy(function (Kurikulum $kurikulum): int {
                $posisi = array_search($kurikulum->academicUnit?->type, self::URUTAN_UNIT_TERENDAH, true);

                return $posisi === false ? 99 : $posisi;
            })
            ->first();

        return $kandidat ?? static::scopedQuery($user)
            ->with('academicUnit')
            ->orderByDesc('tahun')
            ->first();
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        return static::optionsForUnits(static::scopedUnitIds($user));
    }

    /**
     * Opsi kurikulum untuk unit tertentu (mis. scope Kelas MK).
     *
     * @param  Collection<int, string>  $unitIds
     * @return array<string, string>
     */
    public static function optionsForUnits(Collection $unitIds): array
    {
        if ($unitIds->isEmpty()) {
            return [];
        }

        return Kurikulum::query()
            ->with('academicUnit')
            ->whereIn('academic_unit_id', $unitIds)
            ->orderBy('nama')
            ->get()
            ->mapWithKeys(fn (Kurikulum $kurikulum): array => [
                $kurikulum->id => static::label($kurikulum),
            ])
            ->all();
    }

    public static function label(Kurikulum $kurikulum): string
    {
        $unit = $kurikulum->academicUnit->nama ?? '—';
        $aktif = $kurikulum->is_active ? ' · aktif' : '';

        return "{$kurikulum->nama} ({$unit}{$aktif})";
    }

    /**
     * Rantai unit dari level terendah ke induk: Prodi / Jurusan / Fakultas / Universitas.
     */
    public static function unitHierarchyLabel(?AcademicUnit $unit): string
    {
        if (! $unit instanceof AcademicUnit) {
            return '—';
        }

        $names = [];
        $current = $unit;

        while ($current instanceof AcademicUnit) {
            array_unshift($names, $current->nama);

            if ($current->parent_id === null) {
                break;
            }

            $current->loadMissing('parent');
            $current = $current->parent;
        }

        return $names === [] ? '—' : implode(' / ', $names);
    }

    /**
     * Banner konteks kurikulum terpilih untuk halaman Profil, CPL, BoK, MK, Penawaran MK, dan matriks interaksi.
     */
    public static function bannerHtml(): HtmlString
    {
        $gantiUrl = KurikulumResource::getUrl('index');
        $kurikulum = static::current();

        if (! $kurikulum instanceof Kurikulum) {
            return new HtmlString(
                '<div style="padding:12px 14px;border-radius:8px;background:#fef3c7;border:1px solid #fcd34d;color:#92400e;font-size:13px;line-height:1.55;">'
                .'<div>Belum ada kurikulum terpilih.</div>'
                .'<div style="margin-top:4px;">'
                .'<a href="'.e($gantiUrl).'" style="font-weight:600;color:#b45309;text-decoration:underline;">Pilih dari halaman Kurikulum</a>'
                .'</div>'
                .'</div>'
            );
        }

        $kurikulum->loadMissing('academicUnit');
        $hierarchy = static::unitHierarchyLabel($kurikulum->academicUnit);

        return new HtmlString(
            '<div style="padding:12px 14px;border-radius:8px;background:#eff6ff;border:1px solid #bfdbfe;color:#1e3a8a;font-size:13px;line-height:1.55;">'
            .'<div>'
            .'<span style="opacity:.88;">Kurikulum terpilih:</span> '
            .'<strong>'.e($kurikulum->nama).'</strong> '
            .'<a href="'.e($gantiUrl).'" style="margin-left:6px;font-weight:600;color:#1d4ed8;text-decoration:underline;">Ganti</a>'
            .'</div>'
            .'<div style="margin-top:6px;opacity:.92;">'.e($hierarchy).'</div>'
            .'</div>'
        );
    }

    /**
     * @return Builder<Kurikulum>
     */
    protected static function scopedQuery(User $user): Builder
    {
        $query = Kurikulum::query();

        if ($user->hasRole('Super Admin')) {
            return $query;
        }

        $unitIds = static::scopedUnitIds($user);

        if ($unitIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('academic_unit_id', $unitIds);
    }

    /**
     * @return Collection<int, string>
     */
    protected static function scopedUnitIds(User $user): Collection
    {
        // Tim kurikulum + Admin (unit penugasan) via scopedTimKurikulumUnitIdsFor.
        $unitIds = AcademicUnitScope::scopedTimKurikulumUnitIdsFor($user);

        // Koordinator MK mengelola kelas per prodi — perlu melihat kurikulum unit penugasan.
        if (ActiveRole::userOwnsRoleName($user, 'Koordinator Mata Kuliah')) {
            $unitIds = $unitIds->merge(AcademicUnitScope::managedUnitIdsFor($user));
        }

        return $unitIds->unique()->values();
    }
}
