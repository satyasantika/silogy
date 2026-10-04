<?php

namespace App\Modules\Simulasi\Http\Controllers;

use App\Models\User;
use App\Modules\Panduan\Support\PeranPanduan;
use App\Modules\Simulasi\Services\SimulasiService;
use App\Modules\Simulasi\Support\AkunSimulasi;
use App\Modules\Simulasi\Support\Ranah;
use App\Modules\Simulasi\Support\SesiTab;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Tombol "Lihat contoh terisi" di halaman panduan: masuk tanpa kata sandi ke
 * satu akun pada contoh terisi bersama (hanya-baca) dalam tab baru.
 *
 * Ruang latihan yang bisa diisi sendiri tidak lewat sini, melainkan lewat
 * token ruang (RuangSimulasiController).
 *
 * Empat pagar keamanan di sini hanya boleh ditambah, tidak dilonggarkan:
 *  1. mode latihan harus terbuka;  2. hanya tingkat prodi;
 *  3. akun harus tercatat milik sandbox;  4. surel akun berdomain simulasi.
 */
class CobaPeranController
{
    public function __construct(protected SimulasiService $simulasi) {}

    public function __invoke(Request $request, string $peran): RedirectResponse
    {
        // 404, bukan 403: fitur yang dimatikan sebaiknya tidak mengiklankan diri.
        abort_unless($this->simulasi->cobaPeranTerbuka(), 404);

        $level = (string) $request->input('level', AkunSimulasi::LEVEL_SIMULASI);
        abort_unless(array_key_exists($level, AkunSimulasi::LEVEL), 404);

        // Panduan tetap ada untuk semua tingkat, tetapi simulasi hanya untuk Program Studi.
        if ($level !== AkunSimulasi::LEVEL_SIMULASI) {
            return back()->with('panduan_galat', 'Simulasi hanya tersedia untuk tingkat Program Studi.');
        }

        $kunciAkun = PeranPanduan::akunUntuk($peran, $level);
        abort_if($kunciAkun === null, 404);

        $jalan = $this->simulasi->contohTerisi();

        if ($jalan === null) {
            return back()->with('panduan_galat', $this->simulasi->contohTerisiSedangDibangun()
                ? 'Contoh terisi sedang disiapkan. Coba lagi sebentar lagi.'
                : 'Contoh terisi belum tersedia. Hubungi Super Admin.');
        }

        $user = Ranah::sebagai((string) $jalan->getKey(), fn () => User::query()
            ->where('username', AkunSimulasi::username($kunciAkun, $jalan->kode()))
            ->first());

        if ($user === null) {
            return back()->with('panduan_galat',
                'Akun contoh untuk peran ini belum tersedia. Muat ulang halaman dan coba lagi.');
        }

        // Pagar 3 dan 4.
        abort_unless($this->simulasi->memiliki($jalan, $user), 403);
        abort_unless(str_ends_with((string) $user->email, '@'.AkunSimulasi::DOMAIN), 403);

        $token = SesiTab::token();
        SesiTab::buat($token, $jalan, (string) $user->getKey(), $peran);

        return redirect()->to(url('/s/'.$token.'/simulasi/masuk'));
    }
}
