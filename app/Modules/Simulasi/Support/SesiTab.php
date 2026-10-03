<?php

namespace App\Modules\Simulasi\Support;

use App\Modules\Simulasi\Models\SimulasiJalan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Catatan satu tab simulasi.
 *
 * Satu tab = satu token acak di URL (`/s/<token>/...`) = satu cookie sesi
 * tersendiri. Itu yang membuat beberapa peran bisa dibuka bersamaan dalam
 * satu browser tanpa saling menimpa login: cookie dibatasi ke path tab.
 *
 * Token hanya sah selama catatannya ada di cache. Catatan menyimpan sandbox
 * dan akun tujuan, plus tiket masuk sekali pakai: tautan `/masuk` tidak bisa
 * dipakai ulang untuk login di tempat lain.
 */
final class SesiTab
{
    public const PANJANG_TOKEN = 24;

    /** Pola segmen awal URL tab: /s/<token> diikuti sisa path (boleh kosong). */
    public const POLA = '#^/s/([a-z0-9]{24})(/.*)?$#';

    public static function token(): string
    {
        return Str::lower(Str::random(self::PANJANG_TOKEN));
    }

    public static function ttlDetik(): int
    {
        return max(60, (int) config('simulasi.umur_menit', 120) * 60);
    }

    /**
     * @return array{jalan: string, user: string, peran: string, tiket: bool}
     */
    public static function buat(string $token, SimulasiJalan $jalan, string $userId, string $peran): array
    {
        $catatan = [
            'jalan' => (string) $jalan->getKey(),
            'user' => $userId,
            'peran' => $peran,
            'tiket' => true,
        ];

        Cache::put(self::kunci($token), $catatan, self::ttlDetik());

        return $catatan;
    }

    /**
     * @return array{jalan: string, user: string, peran: string, tiket: bool}|null
     */
    public static function cari(string $token): ?array
    {
        $catatan = Cache::get(self::kunci($token));

        return is_array($catatan) ? $catatan : null;
    }

    /**
     * Menghabiskan tiket masuk; null bila sudah terpakai atau tab tak dikenal.
     *
     * @return array{jalan: string, user: string, peran: string, tiket: bool}|null
     */
    public static function pakaiTiket(string $token): ?array
    {
        $catatan = self::cari($token);

        if ($catatan === null || ! $catatan['tiket']) {
            return null;
        }

        $catatan['tiket'] = false;
        Cache::put(self::kunci($token), $catatan, self::ttlDetik());

        return $catatan;
    }

    /**
     * Memperpanjang umur tab dan mencatat aktivitas sandbox, paling sering
     * sekali per lima menit supaya tidak menulis ke basis data tiap request.
     *
     * @param  array{jalan: string, user: string, peran: string, tiket: bool}  $catatan
     */
    public static function sentuh(string $token, array $catatan): void
    {
        if (! Cache::add('sim-sentuh:'.$token, 1, 300)) {
            return;
        }

        Cache::put(self::kunci($token), $catatan, self::ttlDetik());

        SimulasiJalan::query()
            ->whereKey($catatan['jalan'])
            ->update(['terakhir_aktif_pada' => now()]);
    }

    public static function lupakan(string $token): void
    {
        Cache::forget(self::kunci($token));
        Cache::forget('sim-sentuh:'.$token);
    }

    private static function kunci(string $token): string
    {
        return 'sim-tab:'.$token;
    }
}
