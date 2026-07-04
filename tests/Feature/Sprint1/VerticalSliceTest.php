<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Policies\AcademicUnitPolicy;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->policy = new AcademicUnitPolicy;
});

it('lets super admin see all academic units', function () {
    $superAdmin = User::where('username', 'superadmin')->first();
    $units = AcademicUnit::all();

    expect($this->policy->viewAny($superAdmin))->toBeTrue()
        ->and($units)->toHaveCount(4);

    foreach ($units as $unit) {
        expect($this->policy->view($superAdmin, $unit))->toBeTrue();
    }
});

it('lets admin prodi see only its unit and ancestors', function () {
    $adminProdi = User::where('username', 'adminprodi')->first();
    $prodi = AcademicUnit::where('type', 'study_program')->first();
    $jurusan = AcademicUnit::where('type', 'department')->first();
    $fakultas = AcademicUnit::where('type', 'faculty')->first();
    $universitas = AcademicUnit::where('type', 'university')->first();

    // Prodi kini berinduk langsung ke fakultas — jurusan bukan lagi
    // ancestor prodi sehingga tidak ikut terlihat.
    expect($this->policy->viewAny($adminProdi))->toBeTrue()
        ->and($this->policy->view($adminProdi, $prodi))->toBeTrue()
        ->and($this->policy->view($adminProdi, $jurusan))->toBeFalse()
        ->and($this->policy->view($adminProdi, $fakultas))->toBeTrue()
        ->and($this->policy->view($adminProdi, $universitas))->toBeTrue();
});

it('blocks admin fakultas from editing universitas', function () {
    $adminFak = User::where('username', 'adminfak')->first();
    $universitas = AcademicUnit::where('type', 'university')->first();

    expect($this->policy->update($adminFak, $universitas))->toBeFalse();
});

it('allows filament default login via email untuk akun demo', function () {
    Livewire::test(Login::class)
        ->fillForm([
            'email' => 'superadmin@silogy.test',
            'password' => 'siliwangi',
        ])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    expect(auth()->user()?->username)->toBe('superadmin');
});
