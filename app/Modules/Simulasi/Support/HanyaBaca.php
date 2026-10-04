<?php

namespace App\Modules\Simulasi\Support;

use App\Models\User;
use App\Modules\Simulasi\Models\SimulasiJalan;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Mengunci contoh terisi bersama menjadi hanya-baca.
 *
 * Contoh terisi satu salinan yang dilihat semua pengunjung, jadi tidak boleh ada
 * yang mengubahnya. Pengamanan berlapis dua, dan hanya berlaku bagi akun yang
 * sandboxnya bertanda `bersama`:
 *
 *  1. Gerbang (Gate::before): kemampuan menulis ditolak, sehingga tombol Simpan,
 *     Ubah, dan Hapus tidak tampil di Filament. Ini pagar kenyamanan.
 *  2. Pagar basis data (beforeExecuting): setiap INSERT/UPDATE/DELETE/DDL ke tabel
 *     di luar daftar kecuali ditolak sebelum sampai ke MySQL. Ini pagar sebenarnya:
 *     ia menangkap aksi tulis yang lolos dari policy, misalnya halaman dengan logika
 *     sendiri atau permintaan yang dibuat manual.
 *
 * Tidak berlaku bagi proses sistem (pembangun, pembongkar, penjadwal) yang
 * berjalan tanpa pengguna login atau di bawah Ranah::sebagai().
 */
final class HanyaBaca
{
    /**
     * Kemampuan penulisan yang ditolak di lapis gerbang. Daftar TOLAK, bukan daftar
     * izin: nama izin seperti kelola_* sengaja tidak disentuh karena sebagian policy
     * memakainya untuk menentukan siapa boleh MELIHAT. Pagar basis data tetap
     * menjamin tidak ada yang tertulis.
     *
     * @var list<string>
     */
    public const KEMAMPUAN_TULIS = [
        'create', 'update', 'delete', 'deleteAny', 'restore', 'restoreAny',
        'forceDelete', 'forceDeleteAny', 'replicate', 'reorder',
        'manage', 'manageMk', 'kelolaMahasiswa', 'inputNilai', 'createUnit',
        'setujui', 'tolak', 'batalkan', 'assignPermissions', 'assignDosenPengampu',
        'minta_analisis_ai',
    ];

    /**
     * Tabel yang tetap boleh ditulis oleh akun contoh bersama: infrastruktur
     * (sesi, cache, antrean) dan penanda aktivitas sandbox. Tidak satu pun berisi
     * data akademik.
     *
     * @var list<string>
     */
    public const TABEL_BOLEH = [
        'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
        'simulasi_jalan', 'simulasi_artefak', 'simulasi_pengaturan', 'simulasi_peran_terisi',
    ];

    public static function pasang(): void
    {
        Gate::before(static function (mixed $user, string $kemampuan): ?bool {
            if (in_array($kemampuan, self::KEMAMPUAN_TULIS, true) && self::aktif($user)) {
                return false;
            }

            return null;
        });

        DB::beforeExecuting(static function (string $sql, array $binding, Connection $koneksi): void {
            self::periksaTulis($sql);
        });

        FilamentView::registerRenderHook(
            PanelsRenderHook::PAGE_START,
            static fn (): string => self::banner(),
        );
    }

    /** Apakah pengguna ini sedang berada di contoh terisi bersama. */
    public static function aktif(mixed $user = null): bool
    {
        // Proses sistem (pembangun, pembongkar) memakai Ranah::sebagai() dan bebas.
        if (Ranah::dipaksa()) {
            return false;
        }

        $user ??= Auth::guard()->hasUser() ? Auth::guard()->user() : null;

        if (! $user instanceof User) {
            return false;
        }

        $sandboxId = $user->getAttribute('sandbox_id');

        if (blank($sandboxId)) {
            return false;
        }

        return self::sandboxBersama((string) $sandboxId);
    }

    public static function sandboxBersama(string $sandboxId): bool
    {
        // Singkat saja: baris ini dibaca pada setiap penulisan akun sandbox.
        return (bool) Cache::remember(
            'sim-bersama:'.$sandboxId,
            300,
            static fn (): bool => SimulasiJalan::query()->whereKey($sandboxId)->where('bersama', true)->exists(),
        );
    }

    /**
     * @throws HttpException bila akun contoh bersama mencoba menulis
     */
    public static function periksaTulis(string $sql): void
    {
        // Jalur cepat: hampir semua query adalah SELECT.
        if (! preg_match('/^\s*(insert|replace|update|delete|truncate|alter|drop|create|rename)\b/i', $sql, $kata)) {
            return;
        }

        if (! self::aktif()) {
            return;
        }

        $perintah = strtolower($kata[1]);

        if (in_array($perintah, ['alter', 'drop', 'create', 'rename', 'truncate'], true)) {
            self::tolak($perintah, null);
        }

        $tabel = self::tabelSasaran($sql);

        if ($tabel !== null && in_array($tabel, self::TABEL_BOLEH, true)) {
            return;
        }

        self::tolak($perintah, $tabel);
    }

    public static function tabelSasaran(string $sql): ?string
    {
        if (preg_match(
            '/^\s*(?:insert\s+(?:ignore\s+)?into|replace\s+into|update(?:\s+ignore)?|delete\s+from)\s+[`"\[]?([a-z0-9_]+)/i',
            $sql,
            $cocok,
        )) {
            return strtolower($cocok[1]);
        }

        return null;
    }

    private static function tolak(string $perintah, ?string $tabel): never
    {
        throw new HttpException(
            403,
            'Contoh terisi hanya dapat dibaca. Gunakan "Coba mengisi sendiri" dari halaman panduan untuk berlatih mengisi.',
        );
    }

    /** Keterangan di atas setiap halaman contoh terisi. */
    public static function banner(): string
    {
        try {
            if (! self::aktif()) {
                return '';
            }

            return view('filament.hooks.contoh-hanya-baca')->render();
        } catch (Throwable $galat) {
            report($galat);

            return '';
        }
    }
}
