<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Services\KoordinatorMkRoleSync;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SemesterSeeder::class);

    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->dosen = User::query()->where('username', 'dosen')->firstOrFail();
    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Sync Korma',
        'tahun' => 2026,
        'is_active' => true,
    ]);
});

it('menetapkan role Koordinator Mata Kuliah saat dosen dijadikan koordinator mk', function () {
    expect($this->dosen->hasRole('Dosen Pengampu'))->toBeTrue()
        ->and($this->dosen->hasRole('Koordinator Mata Kuliah'))->toBeFalse();

    $mk = Mk::factory()->forKurikulum($this->kurikulum)->create([
        'academic_unit_id' => $this->prodi->id,
        'koordinator_mk_id' => $this->dosen->id,
    ]);

    expect($mk->fresh()->koordinator_mk_id)->toBe($this->dosen->id)
        ->and($this->dosen->fresh()->hasRole('Koordinator Mata Kuliah'))->toBeTrue()
        ->and($this->dosen->fresh()->hasRole('Dosen Pengampu'))->toBeTrue();
});

it('mencabut role Koordinator Mata Kuliah bila tidak ada penugasan mk maupun kelas lagi', function () {
    $mk = Mk::factory()->forKurikulum($this->kurikulum)->create([
        'academic_unit_id' => $this->prodi->id,
        'koordinator_mk_id' => $this->dosen->id,
    ]);

    expect($this->dosen->fresh()->hasRole('Koordinator Mata Kuliah'))->toBeTrue();

    $mk->update(['koordinator_mk_id' => null]);

    expect($this->dosen->fresh()->hasRole('Koordinator Mata Kuliah'))->toBeFalse()
        ->and($this->dosen->fresh()->hasRole('Dosen Pengampu'))->toBeTrue();
});

it('tidak mencabut role bila masih koordinator pada mk kurikulum lain', function () {
    $kurikulumLain = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Lain Sync Korma',
        'tahun' => 2025,
        'is_active' => false,
    ]);

    $mkA = Mk::factory()->forKurikulum($this->kurikulum)->create([
        'academic_unit_id' => $this->prodi->id,
        'koordinator_mk_id' => $this->dosen->id,
    ]);
    $mkB = Mk::factory()->forKurikulum($kurikulumLain)->create([
        'academic_unit_id' => $this->prodi->id,
        'koordinator_mk_id' => $this->dosen->id,
    ]);

    $mkA->update(['koordinator_mk_id' => null]);

    expect($this->dosen->fresh()->hasRole('Koordinator Mata Kuliah'))->toBeTrue()
        ->and($mkB->fresh()->koordinator_mk_id)->toBe($this->dosen->id)
        ->and(app(KoordinatorMkRoleSync::class)->masihKoordinator($this->dosen->id))->toBeTrue();
});

it('tidak mencabut role bila masih koordinator pada mk lain', function () {
    $mkA = Mk::factory()->forKurikulum($this->kurikulum)->create([
        'academic_unit_id' => $this->prodi->id,
        'koordinator_mk_id' => $this->dosen->id,
    ]);
    $mkB = Mk::factory()->forKurikulum($this->kurikulum)->create([
        'academic_unit_id' => $this->prodi->id,
        'koordinator_mk_id' => $this->dosen->id,
    ]);

    $mkA->update(['koordinator_mk_id' => null]);

    expect($this->dosen->fresh()->hasRole('Koordinator Mata Kuliah'))->toBeTrue()
        ->and($mkB->fresh()->koordinator_mk_id)->toBe($this->dosen->id);
});

it('tidak mencabut role bila masih koordinator pada kelas_mk', function () {
    $mk = Mk::factory()->forKurikulum($this->kurikulum)->create([
        'academic_unit_id' => $this->prodi->id,
        'koordinator_mk_id' => $this->dosen->id,
    ]);
    $mkUnit = MkUnit::factory()->forMk($mk)->forKurikulum($this->kurikulum)->create();
    $semesterId = \App\Modules\Kalender\Models\Semester::query()->where('status_aktif', true)->value('id');

    KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $semesterId,
        'kode_kelas' => 'A',
        'koordinator_mk_id' => $this->dosen->id,
    ]);

    $mk->update(['koordinator_mk_id' => null]);

    expect(app(KoordinatorMkRoleSync::class)->masihKoordinator($this->dosen->id))->toBeTrue()
        ->and($this->dosen->fresh()->hasRole('Koordinator Mata Kuliah'))->toBeTrue();
});
