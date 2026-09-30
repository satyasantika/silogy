<?php

namespace App\Modules\Simulasi\Http\Controllers;

use App\Models\User;
use App\Modules\Auth\Support\ActiveRole;
use App\Modules\Institusi\Support\AcademicUnitTerpilih;
use App\Modules\Panduan\Support\PeranPanduan;
use App\Modules\Simulasi\Services\SimulasiService;
use App\Modules\Simulasi\Support\AkunSimulasi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Masuk otomatis ke akun simulasi dari halaman panduan.
 *
 * ── CATATAN KEAMANAN ──────────────────────────────────────────────────────
 * Ini jalur masuk TANPA KATA SANDI. Ia hanya aman selama empat pagar di bawah
 * berdiri utuh; melonggarkan salah satunya membuka pintu ke akun sungguhan.
 *
 *  1. Sakelarnya terbuka: instans mengizinkan (`simulasi.izinkan_coba_peran`,
 *     pemutus keras di .env) DAN Super Admin menyalakannya pada jalan simulasi
 *     yang aktif. Bawaannya mati; menyalakannya adalah tindakan sadar yang
 *     tercatat, dan bisa dicabut lagi dalam hitungan detik dari menu Simulasi.
 *  2. Ada jalan simulasi yang aktif.
 *  3. Akun sasaran TERCATAT di buku besar jalan itu sebagai baris yang
 *     benar-benar DIBUAT simulasi. Inilah pagar utamanya: sebuah akun nyata
 *     yang kebetulan bernama sama tidak punya artefak, jadi tidak akan pernah
 *     lolos. Pagar ini pula alasan akun simulasi memakai ruang nama sim-*
 *     terpisah — kalau ia memungut akun demo lama yang sudah ada di basis
 *     data, semuanya berstatus "dipinjam" dan gerbang ini menolak semuanya.
 *  4. Surel sasaran berakhiran domain simulasi — pertahanan berlapis.
 *
 * Rutenya POST ber-CSRF, bukan GET: tautan masuk-otomatis yang bisa dipanggil
 * lewat <img src="..."> adalah celah login-CSRF, dan URL bertanda tangan tidak
 * menolong karena tautannya sama untuk semua orang dan tercetak di halaman
 * publik.
 *
 * Yang membuat sesi hasil jalur ini tidak berbahaya adalah isolasi di lapisan
 * data: akun sim-* hanya ditugaskan ke pohon unit simulasi, sehingga seluruh
 * policy berbasis AcademicUnitScope memagarinya dari data nyata.
 */
class CobaPeranController
{
    public function __construct(protected SimulasiService $simulasi) {}

    public function __invoke(Request $request, string $peran): RedirectResponse
    {
        $jalan = $this->simulasi->aktif();

        if ($jalan === null) {
            return back()->with('panduan_galat',
                'Data simulasi belum tersedia. Minta Super Admin menekan Buat Simulasi lebih dulu.');
        }

        // 404, bukan 403: fitur yang dimatikan sebaiknya tidak mengiklankan diri.
        abort_unless($this->simulasi->cobaPeranTerbuka(), 404);

        $username = PeranPanduan::akunUntuk($peran);
        abort_if($username === null, 404);

        $user = User::query()->where('username', $username)->first();

        if ($user === null) {
            return back()->with('panduan_galat',
                'Akun latihan untuk peran ini belum ada. Minta Super Admin membangun ulang simulasi.');
        }

        // Pagar 3 dan 4.
        abort_unless($this->simulasi->memiliki($jalan, $user), 403);
        abort_unless(str_ends_with((string) $user->email, '@'.AkunSimulasi::DOMAIN), 403);

        // Pengunjung yang sedang masuk sebagai akun nyata tidak ditukar diam-diam.
        $sekarang = $request->user();

        if ($sekarang !== null && ! $this->simulasi->memiliki($jalan, $sekarang)) {
            return back()->with('panduan_galat',
                'Anda sedang masuk sebagai '.$sekarang->full_name.'. Keluar dulu sebelum mencoba mode simulasi.');
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Auth::login($user, remember: false);

        // Peran dan unit dipilihkan di sini supaya pengunjung mendarat langsung
        // di dasbor peran yang ia baca, bukan di layar "Pilih peran & unit" yang
        // membingungkan bagi orang yang baru saja menekan "Coba sebagai ...".
        // Keduanya memvalidasi kepemilikan sendiri, jadi pemetaan yang salah
        // gagal tertutup: kuncinya sekadar tidak terpasang.
        ActiveRole::set(PeranPanduan::peranUntuk($peran));
        AcademicUnitTerpilih::set(
            AcademicUnitTerpilih::scopedUnitIdsForRole($user, PeranPanduan::peranUntuk($peran))->first(),
        );

        $request->session()->put('silogy_sesi_simulasi', $jalan->getKey());
        $request->session()->put('silogy_sesi_simulasi_peran', $peran);

        return redirect()->to('/dashboard');
    }
}
