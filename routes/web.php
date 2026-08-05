<?php

use App\Http\Controllers\HealthController;
use App\Modules\Kurikulum\Http\Controllers\KurikulumLaporanPimpinanRedirectController;
use App\Modules\Kurikulum\Http\Controllers\KurikulumMenuRedirectController;
use App\Modules\MK\Http\Controllers\MkMenuRedirectController;
use Illuminate\Support\Facades\Route;

Route::permanentRedirect('/admin/login', '/login');
Route::permanentRedirect('/admin', '/dashboard');

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/health', [HealthController::class, 'index'])
    ->middleware('throttle:health')
    ->name('health.index');

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
