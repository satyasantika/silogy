<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Models\AcademicUnitUser;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\SinkronValidatorKurikulum;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed([AcademicUnitSeeder::class, RolePermissionSeeder::class]));

it('seeder membuat peran Validator Nilai dengan izinnya', function () {
    $peran = Role::findByName('Validator Nilai', 'web');
    expect($peran->permissions->pluck('name')->all())
        ->toEqualCanonicalizing(['validasi_nilai', 'lihat_laporan', 'lihat_dashboard']);
});

it('pivot kurikulum memberi dan mencabut peran', function () {
    $user = User::factory()->create();
    $peranId = Role::findByName('Validator Nilai', 'web')->id;
    $prodi = AcademicUnit::where('type', 'study_program')->firstOrFail();
    $kurikulum = Kurikulum::query()->create(['academic_unit_id' => $prodi->id, 'nama' => 'Kurikulum Peran', 'tahun' => 2026, 'is_active' => true]);
    $punyaPeran = fn () => DB::table('model_has_roles')
        ->where('model_uuid', $user->id)->where('role_id', $peranId)->count();

    $kurikulum->validatorTambahan()->attach($user->id);
    app(SinkronValidatorKurikulum::class)->sinkronkan($kurikulum);
    expect($punyaPeran())->toBe(1);

    $kurikulum->validatorTambahan()->detach($user->id);
    app(SinkronValidatorKurikulum::class)->sinkronkan($kurikulum, [$user->id]);
    expect($punyaPeran())->toBe(0);
});

it('migrasi peran idempoten di atas basis data yang sudah diseed', function () {
    (require database_path('migrations/2026_11_13_000002_tambah_peran_validator_nilai.php'))->up();
    expect(Role::where('name', 'Validator Nilai')->count())->toBe(1);
});

it('akun demo validator berhak lewat validator tambahan kurikulum, bukan penanda penugasan', function () {
    $user = User::where('username', 'validator')->firstOrFail();
    $prodi = AcademicUnit::where('type', 'study_program')->firstOrFail();
    $penugasan = AcademicUnitUser::where('user_id', $user->id)->firstOrFail();

    // Seeder peran tidak membuat kurikulum: tanpa kurikulum belum ada hak.
    expect($penugasan->status_validator_nilai)->toBeFalse()
        ->and($penugasan->jabatan)->toBe('Validator Nilai')
        ->and($user->fresh()->hasRole('Validator Nilai'))->toBeFalse();

    $kurikulum = Kurikulum::query()->create(['academic_unit_id' => $prodi->id, 'nama' => 'Kurikulum Demo', 'tahun' => 2026, 'is_active' => true]);
    $this->seed(RolePermissionSeeder::class);

    expect($kurikulum->validatorTambahan()->whereKey($user->id)->exists())->toBeTrue()
        ->and($user->fresh()->hasRole('Validator Nilai'))->toBeTrue();
});

it('migrasi peran saja membuat peran Validator Nilai dengan tepat tiga izinnya', function () {
    Role::findByName('Validator Nilai', 'web')->delete();
    Permission::query()->where('name', 'validasi_nilai')->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    expect(Role::where('name', 'Validator Nilai')->exists())->toBeFalse()
        ->and(Permission::where('name', 'validasi_nilai')->exists())->toBeFalse();

    (require database_path('migrations/2026_11_13_000002_tambah_peran_validator_nilai.php'))->up();

    $peran = Role::findByName('Validator Nilai', 'web');
    expect($peran->permissions()->pluck('name')->all())
        ->toEqualCanonicalizing(['validasi_nilai', 'lihat_laporan', 'lihat_dashboard']);
});
