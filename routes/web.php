<?php

use App\Http\Controllers\HealthController;
use App\Modules\Auth\Http\Controllers\LeaveImpersonateController;
use App\Modules\Kurikulum\Http\Controllers\KurikulumLaporanPimpinanRedirectController;
use App\Modules\Kurikulum\Http\Controllers\KurikulumMenuRedirectController;
use App\Modules\MK\Http\Controllers\MkMenuRedirectController;
use App\Modules\Panduan\Http\Controllers\PanduanAsetController;
use App\Modules\Panduan\Http\Controllers\PanduanController;
use App\Modules\Panduan\Support\PeranPanduan;
use App\Modules\Simulasi\Http\Controllers\CobaPeranController;
use Illuminate\Support\Facades\Route;

Route::permanentRedirect('/admin/login', '/login');
Route::permanentRedirect('/admin', '/dashboard');

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/health', [HealthController::class, 'index'])
    ->middleware('throttle:health')
    ->name('health.index');

// ── Panduan publik ────────────────────────────────────────────────────────
// Panel Filament menempati path '/', dan rutenya terdaftar LEBIH DULU daripada
// berkas ini. Selama tidak ada resource/page bersiput 'panduan', rute di bawah
// aman — dijaga oleh tes di tests/Feature/Panduan/PanduanRouteTest.php.
Route::get('/panduan', [PanduanController::class, 'indeks'])->name('panduan.indeks');

Route::get('/panduan/aset/{berkas}', PanduanAsetController::class)
    ->where('berkas', '[A-Za-z0-9_\-]+\.(png|jpg|jpeg|svg|webp)')
    ->name('panduan.aset');

Route::get('/panduan/{peran}', [PanduanController::class, 'peran'])
    ->whereIn('peran', PeranPanduan::slug())
    ->name('panduan.peran');

// Masuk otomatis ke akun simulasi. POST + CSRF, bukan GET: tautan masuk-otomatis
// yang bisa dipanggil lewat <img src> adalah celah login-CSRF.
Route::post('/panduan/{peran}/coba', CobaPeranController::class)
    ->middleware('throttle:panduan-coba-peran')
    ->whereIn('peran', PeranPanduan::slug())
    ->name('panduan.coba');

// GET biasa (bukan aksi Livewire) — lihat catatan di LeaveImpersonateController
// soal kenapa ini sengaja tidak dijalankan lewat POST /livewire/update.
Route::middleware(['web', 'auth'])
    ->get('/impersonate/leave', LeaveImpersonateController::class)
    ->name('impersonate.leave');

Route::middleware(['web', 'auth'])
    ->get('/navigasi-kurikulum/{kurikulum}/{menu}', KurikulumMenuRedirectController::class)
    ->whereIn('menu', ['profil', 'cpl', 'bok', 'mk'])
    ->name('silogy.kurikulum-navigasi');

Route::middleware(['web', 'auth'])
    ->get('/navigasi-kurikulum-pimpinan/{kurikulum}/{menu}', KurikulumLaporanPimpinanRedirectController::class)
    ->whereIn('menu', ['hasil', 'grafik', 'mahasiswa'])
    ->name('silogy.kurikulum-navigasi-pimpinan');

Route::middleware(['web', 'auth'])
    ->get('/navigasi-mk/{mk}/{menu}', MkMenuRedirectController::class)
    ->whereIn('menu', ['cpmk', 'subcpmk', 'asesmen', 'mahasiswa', 'cpl-cpmk', 'subcpmk-asesmen'])
    ->name('silogy.mk-navigasi');
