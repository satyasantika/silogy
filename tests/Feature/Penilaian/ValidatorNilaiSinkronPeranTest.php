<?php

use App\Models\Role;
use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnitUser;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\SinkronValidatorKurikulum;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/../../Support/KelasValidasiFixture.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([AcademicUnitSeeder::class, RolePermissionSeeder::class]);
    [$this->prodi, $this->prodiLain] = siapkanDuaProdi();
    $this->peranId = Role::findByName('Validator Nilai', 'web')->id;
    $this->punyaPeran = fn (User $user): bool => DB::table('model_has_roles')
        ->where('model_uuid', $user->id)->where('role_id', $this->peranId)->exists();
    $this->buatKurikulum = fn ($prodi, string $nama, array $atribut = []): Kurikulum => Kurikulum::query()->create([
        'academic_unit_id' => $prodi->id,
        'nama' => $nama,
        'tahun' => 2026,
        'is_active' => true,
        ...$atribut,
    ]);
});

it('pimpinan prodi menerima peran saat ada kurikulum aktif dan kehilangannya saat switch mati', function () {
    $user = User::factory()->create();
    $kurikulum = ($this->buatKurikulum)($this->prodi, 'Kurikulum Sinkron');

    AcademicUnitUser::create(['academic_unit_id' => $this->prodi->id, 'user_id' => $user->id, 'status_pimpinan' => true]);
    expect(($this->punyaPeran)($user))->toBeTrue();

    $kurikulum->update(['validasi_nilai_aktif' => false]);
    expect(($this->punyaPeran)($user))->toBeFalse();

    $kurikulum->update(['validasi_nilai_aktif' => true]);
    expect(($this->punyaPeran)($user))->toBeTrue();
});

it('validator tambahan menerima peran setelah sinkronkan dan kehilangannya setelah dilepas dari pivot', function () {
    $user = User::factory()->create();
    $kurikulum = ($this->buatKurikulum)($this->prodi, 'Kurikulum Tambahan');

    $kurikulum->validatorTambahan()->attach($user->id);
    app(SinkronValidatorKurikulum::class)->sinkronkan($kurikulum);
    expect(($this->punyaPeran)($user))->toBeTrue();

    $kurikulum->validatorTambahan()->detach($user->id);
    app(SinkronValidatorKurikulum::class)->sinkronkan($kurikulum, [$user->id]);
    expect(($this->punyaPeran)($user))->toBeFalse();
});

it('orang yang berhak di dua kurikulum tetap berperan sampai keduanya mati', function () {
    $user = User::factory()->create();
    $kur1 = ($this->buatKurikulum)($this->prodi, 'Kurikulum Satu');
    $kur2 = ($this->buatKurikulum)($this->prodi, 'Kurikulum Dua');
    AcademicUnitUser::create(['academic_unit_id' => $this->prodi->id, 'user_id' => $user->id, 'status_tim_kurikulum' => true]);

    $kur1->update(['validasi_nilai_aktif' => false]);
    expect(($this->punyaPeran)($user))->toBeTrue();

    $kur2->update(['validasi_nilai_aktif' => false]);
    expect(($this->punyaPeran)($user))->toBeFalse();
});

it('peran yang diberikan manual tanpa hak tetap dicabut oleh sinkronisasi', function () {
    $user = User::factory()->create();
    $user->roles()->syncWithoutDetaching([$this->peranId]);
    expect(($this->punyaPeran)($user))->toBeTrue();

    $hasil = app(\App\Modules\Institusi\Support\AcademicUnitUserRoleSync::class)->syncForUser($user->id);

    expect(($this->punyaPeran)($user))->toBeFalse()
        ->and($hasil['revoked'])->toBe(1);
});
