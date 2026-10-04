<?php

namespace App\Modules\Simulasi\Support;

use Closure;
use Illuminate\Support\Facades\Auth;

/**
 * Ranah data yang sedang berlaku: data inti (nyata) atau salah satu sandbox.
 *
 * Satu-satunya sumber kebenaran bagi RanahSimulasiScope. Ranah ditentukan
 * oleh pengguna yang sedang masuk — akun inti hanya melihat data inti, akun
 * sandbox hanya melihat sandbox miliknya. Proses tanpa pengguna (artisan,
 * queue worker) tidak disaring sama sekali; pekerjaan seperti itu selalu
 * digerakkan oleh ID yang eksplisit, bukan oleh "semua baris".
 *
 * Pembangun dan pembongkar sandbox memakai sebagai() untuk menimpa ranah:
 * keduanya sering dipicu Super Admin inti, yang tanpa penimpaan tidak akan
 * melihat satu pun baris sandbox yang baru ia buat.
 */
final class Ranah
{
    /** @var list<array{0: ?string}> */
    private static array $penimpa = [];

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function sebagai(?string $sandboxId, Closure $callback): mixed
    {
        self::$penimpa[] = [$sandboxId];

        try {
            return $callback();
        } finally {
            array_pop(self::$penimpa);
        }
    }

    /**
     * @return array{0: ?string}|null null = tanpa penyaringan; [null] = data
     *                                inti; [id] = sandbox tertentu
     */
    public static function aktif(): ?array
    {
        if (self::$penimpa !== []) {
            return self::$penimpa[array_key_last(self::$penimpa)];
        }

        $guard = Auth::guard();

        if (! $guard->hasUser()) {
            return null;
        }

        $sandboxId = $guard->user()?->getAttribute('sandbox_id');

        return [filled($sandboxId) ? (string) $sandboxId : null];
    }

    /** ID sandbox milik pengguna yang sedang masuk, bila ia akun sandbox. */
    /**
     * Apakah ada penimpaan eksplisit (Ranah::sebagai) yang sedang berjalan,
     * yakni pembangunan/pembongkaran/pembacaan oleh sistem, bukan permintaan
     * seorang pengguna sandbox.
     */
    public static function dipaksa(): bool
    {
        return self::$penimpa !== [];
    }

    public static function sandboxAktif(): ?string
    {
        return self::aktif()[0] ?? null;
    }
}
