<?php

namespace App\Modules\Kurikulum\Support;

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\Institusi\Support\AcademicUnitTerpilih;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Support\Filament\SilogyBannerPanel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
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
        $unitId = AcademicUnitTerpilih::currentId($user);

        if ($unitId !== null) {
            $kandidat = static::scopedQuery($user)
                ->with('academicUnit')
                ->where('academic_unit_id', $unitId)
                ->where('is_active', true)
                ->orderByDesc('created_at')
                ->first();

            if ($kandidat) {
                return $kandidat;
            }
        }

        $kandidat = static::scopedQuery($user)
            ->with('academicUnit')
            ->where('is_active', true)
            ->orderByDesc('created_at')
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
    public static function options(bool $hanyaAktif = false): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        return static::optionsForUnits(static::scopedUnitIds($user), $hanyaAktif);
    }

    /**
     * Opsi kurikulum untuk unit tertentu (mis. scope Kelas MK).
     *
     * @param  Collection<int, string>  $unitIds
     * @return array<string, string>
     */
    public static function optionsForUnits(Collection $unitIds, bool $hanyaAktif = false): array
    {
        if ($unitIds->isEmpty()) {
            return [];
        }

        $query = Kurikulum::query()
            ->with('academicUnit')
            ->whereIn('academic_unit_id', $unitIds)
            ->orderBy('nama');

        if ($hanyaAktif) {
            $query->where('is_active', true);
        }

        return $query
            ->get()
            ->mapWithKeys(fn (Kurikulum $kurikulum): array => [
                $kurikulum->id => static::label($kurikulum),
            ])
            ->all();
    }

    public static function label(Kurikulum $kurikulum): string
    {
        $unit = $kurikulum->academicUnit->nama_lengkap ?? '—';
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
            array_unshift($names, $current->nama_lengkap);

            if ($current->parent_id === null) {
                break;
            }

            $current->loadMissing('parent');
            $current = $current->parent;
        }

        return $names === [] ? '—' : implode(' / ', $names);
    }

    /**
     * Banner konteks kurikulum terpilih (Livewire) — tombol Ganti membuka
     * modal penggantian tanpa meninggalkan halaman. $catatan mengisi baris di
     * bawah garis pemisah banner dengan keterangan khas halaman pemanggil.
     */
    public static function bannerHtml(?string $catatan = null, ?string $bodyHtml = null): HtmlString
    {
        // Strip hijau flat (header). Bungkus panel hanya jika ada pelengkap/body —
        // di list Filament, strip saja menempel ke header card utama (bukan kartu baru).
        $banner = Blade::render(
            '@livewire(\'silogy.kurikulum-terpilih-banner\', [\'catatan\' => null, \'sebagaiHeaderPanel\' => true])',
        );

        if (blank($catatan) && blank($bodyHtml)) {
            return new HtmlString($banner);
        }

        return SilogyBannerPanel::wrap($banner, $catatan, $bodyHtml);
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
    public static function scopedUnitIds(User $user): Collection
    {
        // Tim kurikulum + Admin (unit penugasan) via scopedTimKurikulumUnitIdsFor.
        $unitIds = AcademicUnitScope::scopedTimKurikulumUnitIdsFor($user);

        // Pimpinan membaca laporan CPL pada unit kepemimpinannya beserta
        // seluruh keturunannya (KPI dasbor & daftar kurikulum memakai
        // scope yang sama — lihat DashboardPimpinanService).
        $unitIds = $unitIds->merge(AcademicUnitScope::scopedPimpinanUnitIdsWithDescendantsFor($user));

        // Koordinator MK mengelola kelas per prodi — perlu melihat kurikulum
        // unit penugasan saat role Koordinator sedang aktif.
        if ($user->hasRole('Koordinator Mata Kuliah')) {
            $unitIds = $unitIds->merge(AcademicUnitScope::managedUnitIdsFor($user));
        }

        return $unitIds->unique()->values();
    }
}
