<?php

namespace App\Modules\Penilaian\Support;

/**
 * MK terpilih pada alur Penilaian dosen — tersimpan di session agar konsisten
 * antara halaman /penilaian dan /penilaian/input-nilai.
 */
class PenilaianMkTerpilih
{
    public const SESSION_KEY = 'silogy_penilaian_mk_terpilih';

    public static function currentId(): ?string
    {
        $id = session()->get(self::SESSION_KEY);

        return filled($id) ? (string) $id : null;
    }

    public static function set(?string $mkId): void
    {
        if (blank($mkId)) {
            session()->forget(self::SESSION_KEY);

            return;
        }

        session()->put(self::SESSION_KEY, $mkId);
    }
}
