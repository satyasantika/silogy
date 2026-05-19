<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::permanentRedirect('/admin/login', '/login');
Route::permanentRedirect('/admin', '/dashboard');

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/health', [HealthController::class, 'index'])
    ->middleware('throttle:health')
    ->name('health.index');
