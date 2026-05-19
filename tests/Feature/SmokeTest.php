<?php

use Filament\Facades\Filament;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('halaman beranda dapat diakses', function () {
    $this->get('/')->assertOk();
});

it('halaman masuk filament dapat diakses di jalur umumnya', function () {
    $this->get('/login')->assertOk();
});

it('halaman masuk memuat halaman livewire filament (wire:snapshot)', function () {
    $html = $this->get('/login')->assertOk()->getContent();

    expect($html)->toContain('wire:snapshot')
        ->and($html)->toContain('wire:submit="authenticate"')
        // Aset dipublikasikan ke public/vendor agar nginx tidak 404 untuk /livewire/livewire.js
        ->and($html)->toMatch('#/vendor/livewire/livewire(\.min)?\.js#');
});

it('aset livewire telah terpublikasi agar bisa dilayani nginx/apache sebagai file statis', function () {
    expect(file_exists(public_path('vendor/livewire/livewire.js')))->toBeTrue()
        ->and(file_exists(public_path('vendor/livewire/manifest.json')))->toBeTrue();
});

it('alihan permanen jalur filament lama (/admin/login) tetap bisa dijangkau', function () {
    $this->get('/admin/login')->assertRedirect('/login');
});

it('alihan permanen /admin mengarahkan ke dashboard', function () {
    $this->get('/admin')->assertRedirect('/dashboard');
});
