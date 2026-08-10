<?php

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Memicu ulang AppServiceProvider::configureUrlForSubpathDeployment() setelah
 * config('app.url')/config('session.path') diubah di dalam test — boot()
 * provider yang sesungguhnya sudah berjalan sekali dengan config bawaan
 * sebelum body test dieksekusi.
 */
function reconfigureSubpathForTest(): void
{
    app()->getProvider(AppServiceProvider::class)->configureUrlForSubpathDeployment();
}

afterEach(function () {
    // Reset override runtime supaya tidak bocor ke test lain.
    URL::forceRootUrl(null);
    URL::forceScheme(null);
});

it('menyertakan prefix subpath pada url(), route(), dan redirect()->intended() saat APP_URL berisi path', function () {
    config(['app.url' => 'https://domain.test/silogy', 'session.path' => '/']);

    reconfigureSubpathForTest();

    expect(url('/dashboard'))->toBe('https://domain.test/silogy/dashboard')
        ->and(route('filament.admin.pages.dashboard'))->toStartWith('https://domain.test/silogy')
        ->and(redirect()->intended('/dashboard')->headers->get('Location'))->toBe('https://domain.test/silogy/dashboard');
});

it('session.path otomatis mengikuti subpath saat masih default (belum di-custom)', function () {
    config(['app.url' => 'https://domain.test/silogy', 'session.path' => '/']);

    reconfigureSubpathForTest();

    expect(config('session.path'))->toBe('/silogy');
});

it('menghormati session.path yang sudah di-custom walau APP_URL berisi path subpath', function () {
    // Meniru urutan boot sesungguhnya: config/session.php sudah membaca
    // SESSION_PATH kustom dari .env SEBELUM AppServiceProvider::boot() jalan.
    config(['app.url' => 'https://domain.test/silogy', 'session.path' => '/custom-path']);

    reconfigureSubpathForTest();

    expect(config('session.path'))->toBe('/custom-path');
});

it('tidak mengubah session.path saat APP_URL tanpa path (mode root domain atau subdomain)', function () {
    config(['app.url' => 'https://silogy.domain.test', 'session.path' => '/']);

    $rootSebelumnya = url('/dashboard');

    reconfigureSubpathForTest();

    expect(url('/dashboard'))->toBe($rootSebelumnya)
        ->and(config('session.path'))->toBe('/');
});
