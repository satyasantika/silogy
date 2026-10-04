<?php

namespace App\Modules\Simulasi\Http\Controllers;

use App\Models\User;
use App\Modules\Panduan\Support\PeranPanduan;
use App\Modules\Simulasi\Models\SimulasiJalan;
use App\Modules\Simulasi\Services\SimulasiService;
use App\Modules\Simulasi\Support\AkunSimulasi;
use App\Modules\Simulasi\Support\Ranah;
use App\Modules\Simulasi\Support\SesiTab;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Menyiapkan tab simulasi baru dari halaman panduan.
 *
 * ── CATATAN KEAMANAN ──────────────────────────────────────────────────────
 * Ini jalur masuk TANPA KATA SANDI. Ia hanya aman selama pagar di bawah
 * berdiri utuh; melonggarkan salah satunya membuka pintu ke akun sungguhan.
 *
 *  1. Sakelarnya terbuka: instans mengizinkan (`simulasi.izinkan_coba_peran`)
 *     DAN Super Admin menyalakannya. Bawaannya mati.
 *  2. Pengunjung mendapat sandbox miliknya sendiri (SimulasiService::klaim).
 *  3. Akun sasaran TERCATAT di buku besar sandbox itu sebagai baris yang
 *     benar-benar DIBUAT simulasi. Akun nyata bernama sama tidak punya artefak
 *     sehingga tidak pernah lolos. Akun sandbox juga bernama unik per sandbox
 *     dan bersandi acak yang tidak pernah ditampilkan.
 *  4. Surel sasaran berakhiran domain simulasi — pertahanan berlapis.
 *  5. Tiket masuk sekali pakai, terikat ke satu tab (lihat SesiTab).
 *
 * Rutenya POST ber-CSRF, bukan GET: tautan masuk-otomatis yang bisa dipanggil
 * lewat <img src="..."> adalah celah login-CSRF.
 *
 * Controller ini TIDAK melakukan login. Login terjadi di tab baru
 * (MasukSandboxController) supaya sesinya terpisah dari sesi halaman panduan
 * dan dari tab peran lain; itulah penyebab 403 "bentrok antarperan" dulu.
 */
class CobaPeranController
{
    public const COOKIE_PENGUNJUNG = 'silogy_pengunjung';

    public function __construct(protected SimulasiService $simulasi) {}

    public function __invoke(Request $request, string $peran): RedirectResponse
    {
        // 404, bukan 403: fitur yang dimatikan sebaiknya tidak mengiklankan diri.
        abort_unless($this->simulasi->cobaPeranTerbuka(), 404);

        $level = (string) $request->input('level', 'prodi');
        abort_unless(array_key_exists($level, AkunSimulasi::LEVEL), 404);

        $kunciAkun = PeranPanduan::akunUntuk($peran, $level);
        abort_if($kunciAkun === null, 404);

        $mode = PeranPanduan::modeUntuk($peran, (string) $request->input('mode', SimulasiJalan::MODE_TERISI));

        $pengenal = (string) $request->cookie(self::COOKIE_PENGUNJUNG);

        if (strlen($pengenal) < 32) {
            $pengenal = Str::random(40);
        }

        $jalan = $this->simulasi->klaim(SimulasiService::hashPengunjung($pengenal), $mode);

        if ($jalan === null) {
            return back()->with('panduan_galat',
                'Ruang latihan sedang penuh. Coba lagi beberapa menit lagi.');
        }

        $user = Ranah::sebagai((string) $jalan->getKey(), fn () => User::query()
            ->where('username', AkunSimulasi::username($kunciAkun, $jalan->kode()))
            ->first());

        if ($user === null) {
            return back()->with('panduan_galat',
                'Akun latihan untuk peran ini belum tersedia. Muat ulang halaman dan coba lagi.');
        }

        // Pagar 3 dan 4.
        abort_unless($this->simulasi->memiliki($jalan, $user), 403);
        abort_unless(str_ends_with((string) $user->email, '@'.AkunSimulasi::DOMAIN), 403);

        $token = SesiTab::token();
        SesiTab::buat($token, $jalan, (string) $user->getKey(), $peran);

        Cookie::queue(Cookie::make(
            name: self::COOKIE_PENGUNJUNG,
            value: $pengenal,
            minutes: 60 * 24 * 30,
            path: $this->jalurCookie(),
            secure: $request->isSecure(),
            httpOnly: true,
            sameSite: 'lax',
        ));

        return redirect()->to(url('/s/'.$token.'/simulasi/masuk'));
    }

    private function jalurCookie(): string
    {
        $subPath = rtrim((string) parse_url((string) config('app.url'), PHP_URL_PATH), '/');

        return $subPath === '' ? '/' : $subPath;
    }
}
