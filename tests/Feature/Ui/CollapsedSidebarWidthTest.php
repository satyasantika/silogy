<?php

use App\Models\User;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(User::where('username', 'superadmin')->firstOrFail());
});

it('panel admin memakai collapsed sidebar width 71px', function () {
    expect(Filament::getCurrentPanel()?->getCollapsedSidebarWidth())->toBe('71px');
});

it('theme mengunci lebar sidebar minimize dan gutter ikon 15px', function () {
    $styles = view('filament.hooks.unsil-theme-styles')->render();

    expect($styles)
        ->toContain('.fi-main-sidebar:not(.fi-sidebar-open)')
        ->toContain('width: var(--collapsed-sidebar-width, 71px)')
        ->toContain('padding-inline: 15px')
        ->toContain('margin-inline: 15px');
});

it('halaman dashboard merender CSS variable collapsed sidebar 71px', function () {
    $this->get('/dashboard')
        ->assertOk()
        ->assertSee('--collapsed-sidebar-width: 71px', false);
});
