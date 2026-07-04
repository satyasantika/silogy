<?php

namespace App\Modules\Kurikulum\Support;

use App\Models\User;
use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\Kurikulum\Models\Kurikulum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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

        return static::scopedQuery($user)
            ->with('academicUnit')
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
        // Hanya unit tempat user berstatus tim kurikulum — timkur fakultas
        // mengerjakan kurikulum fakultasnya, bukan kurikulum prodi di bawahnya.
        return AcademicUnitScope::scopedTimKurikulumUnitIdsFor($user)->values();
    }
}
