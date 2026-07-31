<?php

namespace App\Modules\Penilaian\Support;

use App\Models\User;
use App\Modules\MK\Filament\Support\Concerns\HasDosenPengampuMkScope;
use App\Modules\MK\Models\Mk;
use Illuminate\Database\Eloquent\Builder;

/**
 * Mata kuliah yang sedang dikerjakan Dosen Pengampu — dipilih lewat widget
 * dashboard, tersimpan di session. Terpisah dari App\Modules\MK\Support\MkTerpilih
 * (koordinator) karena scope-nya berbeda: MK yang diampu (kelas_mk.dosen_pengampu_id),
 * bukan MK yang dikoordinatori.
 */
class MkDiampuTerpilih
{
    use HasDosenPengampuMkScope;

    public const SESSION_KEY = 'silogy_mk_diampu_terpilih';

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

        $mk = static::scopedQuery($user)->find($id);

        return $mk instanceof Mk ? $mk : null;
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

    /**
     * @return Builder<Mk>
     */
    protected static function scopedQuery(User $user): Builder
    {
        $query = Mk::query();

        if ($user->hasRole(['Super Admin', 'Auditor Mutu'])) {
            return $query;
        }

        $mkIds = static::scopedDiampuMkIds($user);

        if ($mkIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('id', $mkIds);
    }
}
