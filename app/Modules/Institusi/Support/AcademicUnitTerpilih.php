<?php

namespace App\Modules\Institusi\Support;

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use Illuminate\Support\Collection;

/**
 * Unit akademik aktif yang dipilih user dari halaman "Pilih Peran & Unit"
 * atau ikon switcher di footer sidebar, tersimpan di session. Dipakai
 * sebagai konteks unit default untuk role yang terikat unit (Tim
 * Kurikulum, Pimpinan) — KurikulumTerpilih::default() menimbang unit ini
 * sebelum jatuh ke heuristik unit terendah.
 */
class AcademicUnitTerpilih
{
    public const SESSION_KEY = 'silogy_unit_terpilih';

    public static function currentId(User $user): ?string
    {
        $id = session()->get(self::SESSION_KEY);

        if (blank($id) || ! is_string($id)) {
            return null;
        }

        return static::scopedUnitIds($user)->contains($id) ? $id : null;
    }

    public static function set(?string $unitId): void
    {
        if (blank($unitId)) {
            session()->forget(self::SESSION_KEY);

            return;
        }

        $user = auth()->user();

        if ($user instanceof User && static::scopedUnitIds($user)->contains($unitId)) {
            session()->put(self::SESSION_KEY, $unitId);
        }
    }

    /**
     * Unit relevan untuk role tertentu ('Tim Kurikulum' atau 'Pimpinan').
     * Role lain tidak punya konsep unit aktif.
     *
     * @return Collection<int, string>
     */
    public static function scopedUnitIdsForRole(User $user, ?string $role): Collection
    {
        return match ($role) {
            'Tim Kurikulum' => AcademicUnitScope::scopedTimKurikulumUnitIdsFor($user),
            'Pimpinan' => AcademicUnitScope::scopedPimpinanUnitIdsFor($user),
            default => collect(),
        };
    }

    /**
     * Gabungan unit relevan lintas SEMUA role user (dipakai currentId()/set()
     * untuk validasi tanpa perlu tahu role mana yang sedang aktif).
     *
     * @return Collection<int, string>
     */
    public static function scopedUnitIds(User $user): Collection
    {
        return AcademicUnitScope::scopedTimKurikulumUnitIdsFor($user)
            ->merge(AcademicUnitScope::scopedPimpinanUnitIdsFor($user))
            ->unique()
            ->values();
    }

    /**
     * @param  Collection<int, string>  $unitIds
     * @return array<string, string>
     */
    public static function optionsForUnitIds(Collection $unitIds): array
    {
        if ($unitIds->isEmpty()) {
            return [];
        }

        return AcademicUnit::query()
            ->whereIn('id', $unitIds)
            ->orderBy('nama')
            ->pluck('nama', 'id')
            ->all();
    }
}
