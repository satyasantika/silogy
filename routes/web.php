<?php

use App\Http\Controllers\HealthController;
use App\Modules\Kurikulum\Http\Controllers\KurikulumMenuRedirectController;
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
