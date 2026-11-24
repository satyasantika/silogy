<?php

use App\Models\Role;
use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Models\AcademicUnitUser;
use App\Modules\Kurikulum\Models\Kurikulum;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([AcademicUnitSeeder::class, RolePermissionSeeder::class]);
    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Backfill',
        'tahun' => 2026,
        'is_active' => true,
    ]);
    $this->peranId = Role::findByName('Validator Nilai', 'web')->id;
    $this->punyaPeran = fn (User $user): bool => DB::table('model_has_roles')
        ->where('model_uuid', $user->id)->where('role_id', $this->peranId)->exists();
    $this->cabutPeran = fn (User $user) => DB::table('model_has_roles')
        ->where('model_uuid', $user->id)->where('role_id', $this->peranId)->delete();
});

it('perintah sync-roles memberi Validator Nilai ke pimpinan dan validator tambahan tanpa peran', function () {
    $pimpinan = User::factory()->create();
    $tambahan = User::factory()->create();
    AcademicUnitUser::create(['academic_unit_id' => $this->prodi->id, 'user_id' => $pimpinan->id, 'status_pimpinan' => true]);
    $this->kurikulum->validatorTambahan()->attach($tambahan->id);

    // Keadaan sebelum backfill: hak ada, peran belum.
    ($this->cabutPeran)($pimpinan);
    ($this->cabutPeran)($tambahan);
    expect(($this->punyaPeran)($pimpinan))->toBeFalse()
        ->and(($this->punyaPeran)($tambahan))->toBeFalse();

    $this->artisan('academic-unit:sync-roles')->assertSuccessful();

    expect(($this->punyaPeran)($pimpinan))->toBeTrue()
        ->and(($this->punyaPeran)($tambahan))->toBeTrue();
});

it('perintah sync-roles mencabut peran Validator Nilai pemegang tanpa hak', function () {
    $liar = User::factory()->create();
    DB::table('model_has_roles')->insert(['role_id' => $this->peranId, 'model_type' => $liar->getMorphClass(), 'model_uuid' => $liar->id]);

    $this->artisan('academic-unit:sync-roles')->assertSuccessful();

    expect(($this->punyaPeran)($liar))->toBeFalse();
});

it('backfill migrasi memberi peran dan aman dijalankan ulang', function () {
    $pimpinan = User::factory()->create();
    AcademicUnitUser::create(['academic_unit_id' => $this->prodi->id, 'user_id' => $pimpinan->id, 'status_pimpinan' => true]);
    ($this->cabutPeran)($pimpinan);

    $migrasi = require database_path('migrations/2026_11_24_000001_tambah_validasi_nilai_ke_kurikulum.php');
    $migrasi->up();
    $migrasi->up();

    expect(($this->punyaPeran)($pimpinan))->toBeTrue();
});

it('backfill migrasi no-op bila role Validator Nilai belum ada', function () {
    $pimpinan = User::factory()->create();
    AcademicUnitUser::create(['academic_unit_id' => $this->prodi->id, 'user_id' => $pimpinan->id, 'status_pimpinan' => true]);
    DB::table('model_has_roles')->where('role_id', $this->peranId)->delete();
    DB::table('role_has_permissions')->where('role_id', $this->peranId)->delete();
    DB::table('roles')->where('id', $this->peranId)->delete();

    $migrasi = require database_path('migrations/2026_11_24_000001_tambah_validasi_nilai_ke_kurikulum.php');
    $migrasi->up();

    expect(DB::table('roles')->where('name', 'Validator Nilai')->exists())->toBeFalse();
});
