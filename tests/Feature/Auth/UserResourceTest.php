<?php

use App\Models\Role;
use App\Models\User;
use App\Modules\Auth\Filament\Resources\UserResource\Pages\CreateUser;
use App\Modules\Auth\Filament\Resources\UserResource\Pages\ListUsers;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Models\AcademicUnitUser;
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
    $this->actingAs(User::where('username', 'superadmin')->first());
});

it('dapat membuat dosenfoo dengan role dosen pengampu dan penugasan prodi', function () {
    $prodi = AcademicUnit::where('type', 'study_program')->first();
    $roleDosen = Role::where('name', 'Dosen Pengampu')->first();

    Livewire::test(CreateUser::class)
        ->fillForm([
            'full_name' => 'Dosen Foo',
            'username' => 'dosenfoo',
            'email' => 'dosenfoo@silogy.test',
            'password' => 'Silogy2026!',
            'roles' => [$roleDosen->id],
            'academicUnitUsers' => [
                [
                    'academic_unit_id' => $prodi->id,
                    'status_pimpinan' => false,
                    'status_tim_kurikulum' => false,
                    'jabatan' => 'Dosen',
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('username', 'dosenfoo')->first();

    expect($user)->not->toBeNull()
        ->and($user->email)->toBe('dosenfoo@silogy.test')
        ->and($user->hasRole('Dosen Pengampu'))->toBeTrue()
        ->and(Hash::check('Silogy2026!', $user->password))->toBeTrue();

    $pivot = AcademicUnitUser::query()
        ->where('user_id', $user->id)
        ->where('academic_unit_id', $prodi->id)
        ->first();

    expect($pivot)->not->toBeNull()
        ->and($pivot->jabatan)->toBe('Dosen')
        ->and($pivot->status_pimpinan)->toBeFalse()
        ->and($pivot->status_tim_kurikulum)->toBeFalse();
});

it('superadmin dapat mengakses daftar pengguna', function () {
    Livewire::test(ListUsers::class)
        ->assertSuccessful();
});
