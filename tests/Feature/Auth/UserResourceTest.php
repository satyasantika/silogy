<?php

use App\Models\Role;
use App\Models\User;
use App\Modules\Auth\Filament\Resources\UserResource\Pages\CreateUser;
use App\Modules\Auth\Filament\Resources\UserResource\Pages\EditUser;
use App\Modules\Auth\Filament\Resources\UserResource\Pages\ListUsers;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Models\AcademicUnitUser;
use App\Modules\Kurikulum\Models\Kurikulum;
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

it('superadmin dapat membuka halaman edit pengguna', function () {
    $dosen = User::create([
        'full_name' => 'Dosen Edit',
        'username' => 'dosenedit',
        'email' => 'dosenedit@silogy.test',
        'password' => 'RahasiaKuat123',
    ]);

    Livewire::test(EditUser::class, ['record' => $dosen->id])
        ->assertSuccessful()
        ->assertFormFieldExists('password');
});

it('dapat mengimpor pengguna massal lewat copypaste', function () {
    $rows = implode("\n", [
        'Budi Santoso|budisantoso|RahasiaKuat123|budi@silogy.test|Dosen Pengampu',
        'Siti Aminah|sitiaminah|RahasiaKuat456|siti@silogy.test|Tim Kurikulum, Dosen Pengampu',
    ]);

    Livewire::test(ListUsers::class)
        ->callAction('bulkImport', ['rows' => $rows])
        ->assertHasNoActionErrors();

    $budi = User::where('username', 'budisantoso')->first();
    $siti = User::where('username', 'sitiaminah')->first();

    expect($budi)->not->toBeNull()
        ->and($budi->hasRole('Dosen Pengampu'))->toBeTrue()
        ->and($budi->email_verified_at)->not->toBeNull()
        ->and(Hash::check('RahasiaKuat123', $budi->password))->toBeTrue()
        ->and($siti)->not->toBeNull()
        ->and($siti->hasRole(['Tim Kurikulum', 'Dosen Pengampu']))->toBeTrue();
});

it('membatalkan impor massal jika ada baris tidak valid', function () {
    $rows = implode("\n", [
        'Budi Santoso|budisantoso|RahasiaKuat123|budi@silogy.test|Dosen Pengampu',
        'Baris Rusak|tanpa-email-dan-role',
    ]);

    Livewire::test(ListUsers::class)
        ->callAction('bulkImport', ['rows' => $rows]);

    expect(User::where('username', 'budisantoso')->exists())->toBeFalse();
});

it('menolak impor massal dengan role yang tidak dikenal', function () {
    Livewire::test(ListUsers::class)
        ->callAction('bulkImport', [
            'rows' => 'Budi Santoso|budisantoso|RahasiaKuat123|budi@silogy.test|Role Tidak Ada',
        ]);

    expect(User::where('username', 'budisantoso')->exists())->toBeFalse();
});

it('tidak mengizinkan hapus user yang datanya dipakai tabel lain', function () {
    $superadmin = User::where('username', 'superadmin')->first();
    $prodi = AcademicUnit::where('type', 'study_program')->first();

    $dosen = User::create([
        'full_name' => 'Dosen Terpakai',
        'username' => 'dosenterpakai',
        'email' => 'dosenterpakai@silogy.test',
        'password' => 'RahasiaKuat123',
    ]);

    Kurikulum::create([
        'academic_unit_id' => $prodi->id,
        'nama' => 'Kurikulum Uji',
        'tahun' => 2026,
        'dibuat_oleh' => $dosen->id,
    ]);

    expect($dosen->hasDependentRecords())->toBeTrue()
        ->and($superadmin->can('delete', $dosen))->toBeFalse();
});

it('mengizinkan hapus user yang datanya belum dipakai tabel lain', function () {
    $superadmin = User::where('username', 'superadmin')->first();

    $dosen = User::create([
        'full_name' => 'Dosen Bebas',
        'username' => 'dosenbebas',
        'email' => 'dosenbebas@silogy.test',
        'password' => 'RahasiaKuat123',
    ]);

    expect($dosen->hasDependentRecords())->toBeFalse()
        ->and($superadmin->can('delete', $dosen))->toBeTrue();
});
