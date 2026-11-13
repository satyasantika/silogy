<?php

use App\Models\Role;
use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Models\AcademicUnitUser;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed([AcademicUnitSeeder::class, RolePermissionSeeder::class]));

it('seeder membuat peran Validator Nilai dengan izinnya', function () {
    $peran = Role::findByName('Validator Nilai', 'web');
    expect($peran->permissions->pluck('name')->all())
        ->toEqualCanonicalizing(['validasi_nilai', 'lihat_laporan', 'lihat_dashboard']);
});

it('penanda penugasan memberi dan mencabut peran', function () {
    $user = User::factory()->create();
    $peranId = Role::findByName('Validator Nilai', 'web')->id;
    $prodi = AcademicUnit::where('type', 'study_program')->firstOrFail();
    $penugasan = AcademicUnitUser::create(['academic_unit_id' => $prodi->id, 'user_id' => $user->id, 'status_validator_nilai' => true]);
    $punyaPeran = fn () => DB::table('model_has_roles')
        ->where('model_uuid', $user->id)->where('role_id', $peranId)->count();
    expect($punyaPeran())->toBe(1);

    $penugasan->update(['status_validator_nilai' => false]);
    expect($punyaPeran())->toBe(0);
});

it('migrasi peran idempoten di atas basis data yang sudah diseed', function () {
    (require database_path('migrations/2026_11_13_000002_tambah_peran_validator_nilai.php'))->up();
    expect(Role::where('name', 'Validator Nilai')->count())->toBe(1);
});

it('akun demo validator menempel pada prodi nyata dengan penanda aktif', function () {
    $user = User::where('username', 'validator')->firstOrFail();
    $penugasan = AcademicUnitUser::where('user_id', $user->id)->firstOrFail();
    expect($penugasan->status_validator_nilai)->toBeTrue()
        ->and($penugasan->jabatan)->toBe('Validator Nilai');
});
