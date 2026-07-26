<?php

use App\Models\User;
use App\Modules\Auth\Livewire\PeranUnitMenu;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
});

it('ganti kata sandi dari PeranUnitMenu berhasil menyimpan', function () {
    $timkur = User::query()->where('username', 'timkur')->firstOrFail();
    $timkur->forceFill(['password' => 'password-lama'])->save();
    $this->actingAs($timkur);

    Livewire::test(PeranUnitMenu::class)
        ->mountAction('gantiPassword')
        ->setActionData([
            'currentPassword' => 'password-lama',
            'password' => 'PasswordBaru1!',
            'passwordConfirmation' => 'PasswordBaru1!',
        ])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect(Hash::check('PasswordBaru1!', $timkur->fresh()->password))->toBeTrue();
});

it('aksi keluar dari PeranUnitMenu mengakhiri sesi', function () {
    $timkur = User::query()->where('username', 'timkur')->firstOrFail();
    $this->actingAs($timkur);

    Livewire::test(PeranUnitMenu::class)
        ->mountAction('keluar')
        ->callMountedAction()
        ->assertRedirect();

    $this->assertGuest();
});

it('halaman profil menampilkan gelar dan tombol ganti kata sandi tanpa isian password', function () {
    $timkur = User::query()->where('username', 'timkur')->firstOrFail();
    $this->actingAs($timkur);

    $this->get('/profile')
        ->assertOk()
        ->assertSee('Gelar depan')
        ->assertSee('Gelar belakang')
        ->assertSee('Ganti kata sandi');

    Livewire::test(\App\Filament\Pages\Auth\EditProfile::class)
        ->assertFormFieldExists('prefix')
        ->assertFormFieldExists('suffix')
        ->assertFormFieldExists('full_name')
        ->assertFormFieldExists('email')
        ->assertFormFieldDoesNotExist('password')
        ->assertActionExists('gantiPassword');
});

it('user-menu navbar memuat item ganti kata sandi', function () {
    $timkur = User::query()->where('username', 'timkur')->firstOrFail();
    $this->actingAs($timkur);

    $this->get('/dashboard')
        ->assertOk()
        ->assertSee('Ganti kata sandi');
});
