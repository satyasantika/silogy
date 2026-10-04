<?php

namespace App\Modules\Simulasi\Http\Controllers;

use App\Models\User;
use App\Modules\Panduan\Support\PeranPanduan;
use App\Modules\Simulasi\Models\SimulasiJalan;
use App\Modules\Simulasi\Services\RuangSimulasi;
use App\Modules\Simulasi\Services\SimulasiService;
use App\Modules\Simulasi\Support\AkunSimulasi;
use App\Modules\Simulasi\Support\PengaturanSimulasi;
use App\Modules\Simulasi\Support\Ranah;
use App\Modules\Simulasi\Support\SesiTab;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Pintu masuk peserta ruang latihan: masukkan token ruang, lalu pilih peran.
 *
 * Pagar yang dipertahankan dari jalur contoh terisi: mode latihan harus
 * terbuka (404 bila tidak), akun harus tercatat milik sandbox, dan surelnya
 * harus berdomain simulasi.
 */
class RuangSimulasiController
{
    public function __construct(
        protected SimulasiService $simulasi,
        protected RuangSimulasi $ruang,
    ) {}

    public function form(Request $request): View
    {
        $this->pastikanTerbuka();

        return view('simulasi.ruang', ['ruang' => null, 'peran' => [], 'pinIsian' => (string) $request->query('pin', '')]);
    }

    public function periksa(Request $request): RedirectResponse
    {
        $this->pastikanTerbuka();

        $ruang = $this->cariAtauTolak($request, (string) $request->input('pin', ''));

        if ($ruang === null) {
            return redirect()->route('simulasi.ruang')
                ->withInput(['pin' => (string) $request->input('pin', '')])
                ->with('panduan_galat', 'Token ruang tidak ditemukan. Periksa kembali huruf dan angkanya, atau minta token kepada fasilitator.');
        }

        return redirect()->route('simulasi.ruang.tampil', ['pin' => $ruang->pin]);
    }

    public function tampil(Request $request, string $pin): View|RedirectResponse
    {
        $this->pastikanTerbuka();

        $ruang = $this->cariAtauTolak($request, $pin);

        if ($ruang === null) {
            return redirect()->route('simulasi.ruang')
                ->with('panduan_galat', 'Token ruang tidak ditemukan atau ruangnya sudah ditutup.');
        }

        return view('simulasi.ruang', [
            'ruang' => $ruang,
            'peran' => $this->ruang->daftarPeran($ruang),
            'pinIsian' => (string) $ruang->pin,
        ]);
    }

    /** Status terisi tiap peran, untuk penyegaran otomatis halaman ruang. */
    public function status(Request $request, string $pin): JsonResponse
    {
        $this->pastikanTerbuka();

        $ruang = $this->cariAtauTolak($request, $pin);
        abort_if($ruang === null, 404);

        return response()->json([
            'terisi' => collect($this->ruang->daftarPeran($ruang))->map(fn (array $p): bool => $p['terisi'])->all(),
        ]);
    }

    public function masuk(Request $request, string $pin, string $peran): RedirectResponse
    {
        $this->pastikanTerbuka();

        $ruang = $this->cariAtauTolak($request, $pin);

        if ($ruang === null) {
            return redirect()->route('simulasi.ruang')
                ->with('panduan_galat', 'Token ruang tidak ditemukan atau ruangnya sudah ditutup.');
        }

        $kunciAkun = PeranPanduan::akunUntuk($peran, AkunSimulasi::LEVEL_SIMULASI);
        abort_if($kunciAkun === null, 404);

        $user = Ranah::sebagai((string) $ruang->getKey(), fn () => User::query()
            ->where('username', AkunSimulasi::username($kunciAkun, $ruang->kode()))
            ->first());

        if ($user === null) {
            return redirect()->route('simulasi.ruang.tampil', ['pin' => $ruang->pin])
                ->with('panduan_galat', 'Akun untuk peran ini belum tersedia di ruang ini. Hubungi fasilitator.');
        }

        // Pagar 3 dan 4.
        abort_unless($this->simulasi->memiliki($ruang, $user), 403);
        abort_unless(str_ends_with((string) $user->email, '@'.AkunSimulasi::DOMAIN), 403);

        // Token tab dibuat lebih dulu karena dialah yang menjadi pemegang peran.
        $token = SesiTab::token();

        if (! $this->ruang->ambilPeran($ruang, $peran, $token)) {
            return redirect()->route('simulasi.ruang.tampil', ['pin' => $ruang->pin])
                ->with('panduan_galat', 'Peran ini baru saja diambil peserta lain. Pilih peran yang masih tersedia.');
        }

        SesiTab::buat($token, $ruang, (string) $user->getKey(), $peran, ruang: true);

        return redirect()->to(url('/s/'.$token.'/simulasi/masuk'));
    }

    private function pastikanTerbuka(): void
    {
        // 404, bukan 403: fitur yang ditutup sebaiknya tidak mengiklankan diri.
        abort_unless($this->simulasi->cobaPeranTerbuka(), 404);
    }

    /**
     * Mencari ruang dari token. Token salah dihitung per IP: peserta satu
     * jaringan berbagi IP, jadi yang dibatasi hanya tebakan yang gagal, bukan
     * semua klik.
     */
    private function cariAtauTolak(Request $request, string $masukan): ?SimulasiJalan
    {
        $kunci = 'ruang-token-salah:'.$request->ip();
        $batas = PengaturanSimulasi::ambilCepat('batas_token_salah');

        abort_if(RateLimiter::tooManyAttempts($kunci, $batas), 429, 'Terlalu banyak token salah. Tunggu semenit lalu coba lagi.');

        $ruang = $this->ruang->cari($masukan);

        if ($ruang === null) {
            RateLimiter::hit($kunci, 60);
        }

        return $ruang;
    }
}
