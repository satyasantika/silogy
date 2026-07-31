<?php

use App\Models\User;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Filament\Support\Enums\Width;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(User::where('username', 'superadmin')->firstOrFail());
});

it('panel admin memakai max content width full agar gutter tidak membesar di desktop', function () {
    expect(Filament::getCurrentPanel()?->getMaxContentWidth())->toBe(Width::Full);
});

it('theme mengunci padding-inline main ke 1rem di semua breakpoint', function () {
    $styles = view('filament.hooks.unsil-theme-styles')->render();

    expect($styles)
        ->toContain('.fi-body .fi-main')
        ->toContain('padding-inline: 1rem !important');
});

it('halaman dashboard merender main dengan kelas fi-width-full', function () {
    $this->get('/dashboard')
        ->assertOk()
        ->assertSee('fi-width-full', false);
});
