<?php

namespace App\Modules\Kalender\Support;

use App\Models\User;
use App\Modules\Kalender\Models\Semester;
use App\Modules\MK\Support\MkTerpilih;
use App\Modules\MK\Support\PenawaranMkScope;
use Illuminate\Support\Facades\Auth;

/**
 * Semester operasional terpilih per mata kuliah — hanya untuk Koordinator Mata Kuliah.
 */
class SemesterTerpilih
{
    public const SESSION_KEY = 'silogy_semester_terpilih';

    /**
     * Filter/pilihan semester hanya aktif bila user berperan sebagai Koordinator MK murni.
     */
    public static function berlakuUntukUser(?User $user = null): bool
    {
        $user ??= Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        return PenawaranMkScope::isKoordinatorMkOnly($user);
    }

    /**
     * @return array<string, string>
     */
    public static function options(?string $mkId = null): array
    {
        if (! static::berlakuUntukUser()) {
            return [];
        }

        if (blank($mkId ?? MkTerpilih::currentId())) {
            return [];
        }

        return static::optionsSemua();
    }

    /**
     * @return array<string, string>
     */
    public static function optionsSemua(): array
    {
        return Semester::query()
            ->orderBy('kode')
            ->pluck('nama', 'id')
            ->all();
    }

    public static function defaultId(): ?string
    {
        $options = static::optionsSemua();

        if ($options === []) {
            return null;
        }

        $aktifId = Semester::query()->where('status_aktif', true)->value('id');

        if (filled($aktifId) && array_key_exists((string) $aktifId, $options)) {
            return (string) $aktifId;
        }

        return Semester::query()
            ->orderByDesc('kode')
            ->value('id');
    }

    public static function currentId(?string $mkId = null): ?string
    {
        if (! static::berlakuUntukUser()) {
            return null;
        }

        $mkId ??= MkTerpilih::currentId();

        if (blank($mkId)) {
            return null;
        }

        $options = static::optionsSemua();

        if ($options === []) {
            return null;
        }

        $stored = session()->get(self::SESSION_KEY);

        if (
            is_array($stored)
            && ($stored['mk_id'] ?? null) === $mkId
            && filled($stored['semester_id'] ?? null)
            && array_key_exists((string) $stored['semester_id'], $options)
        ) {
            return (string) $stored['semester_id'];
        }

        return static::defaultId();
    }

    public static function set(?string $mkId, ?string $semesterId): void
    {
        if (! static::berlakuUntukUser()) {
            return;
        }

        if (blank($mkId) || blank($semesterId)) {
            session()->forget(self::SESSION_KEY);

            return;
        }

        if (! array_key_exists($semesterId, static::optionsSemua())) {
            return;
        }

        session()->put(self::SESSION_KEY, [
            'mk_id' => $mkId,
            'semester_id' => $semesterId,
        ]);
    }

    public static function label(?string $semesterId = null): string
    {
        $semesterId ??= static::currentId();

        if (blank($semesterId)) {
            return '—';
        }

        return Semester::query()->whereKey($semesterId)->value('nama') ?? '—';
    }

    /**
     * @return array{status: string, keterangan: string}|null
     */
    public static function validasiUntukImpor(string $semesterId): ?array
    {
        if (! Semester::query()->whereKey($semesterId)->exists()) {
            return [
                'status' => 'invalid',
                'keterangan' => 'Semester tidak ditemukan.',
            ];
        }

        return null;
    }
}
