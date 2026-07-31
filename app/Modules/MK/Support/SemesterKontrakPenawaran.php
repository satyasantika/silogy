<?php

namespace App\Modules\MK\Support;

use App\Modules\Kalender\Models\Semester;

/**
 * Semester kalender terpilih untuk halaman Penawaran MK (/mk-units):
 * memengaruhi kolom jumlah mahasiswa kontrak dan aksi Tarik data.
 * Tidak bergantung Koordinator MK / MkTerpilih.
 */
class SemesterKontrakPenawaran
{
    public const SESSION_KEY = 'silogy_semester_kontrak_penawaran';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return Semester::query()
            ->orderBy('kode')
            ->pluck('nama', 'id')
            ->all();
    }

    public static function defaultId(): ?string
    {
        $options = static::options();

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

    public static function currentId(): ?string
    {
        $options = static::options();

        if ($options === []) {
            return null;
        }

        $stored = session()->get(self::SESSION_KEY);

        if (filled($stored) && array_key_exists((string) $stored, $options)) {
            return (string) $stored;
        }

        return static::defaultId();
    }

    public static function set(?string $semesterId): void
    {
        if (blank($semesterId)) {
            session()->forget(self::SESSION_KEY);

            return;
        }

        if (! array_key_exists($semesterId, static::options())) {
            return;
        }

        session()->put(self::SESSION_KEY, $semesterId);
    }

    public static function label(?string $semesterId = null): string
    {
        $semesterId ??= static::currentId();

        if (blank($semesterId)) {
            return '—';
        }

        return Semester::query()->whereKey($semesterId)->value('nama') ?? '—';
    }
}
