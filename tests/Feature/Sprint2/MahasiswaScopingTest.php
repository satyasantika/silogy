<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Mahasiswa\Filament\Resources\MahasiswaResource;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\Mahasiswa\Policies\MahasiswaPolicy;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\MahasiswaSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MahasiswaSeeder::class);

    $this->policy = new MahasiswaPolicy;
    $this->prodiA = AcademicUnit::query()->where('type', 'study_program')->where('code', 'S1-IF')->first();
    $jurusan = AcademicUnit::query()->where('type', 'department')->first();

    $this->prodiB = AcademicUnit::factory()->studyProgram($jurusan)->create([
        'nama' => 'S1 Sistem Informasi',
        'code' => 'S1-SI',
        'kode_pddikti' => '57201',
        'jenjang' => 'S1',
        'gelar_lulusan' => 'S.Kom.',
        'status' => 'aktif',
    ]);

    Mahasiswa::factory()->count(30)->create([
        'academic_unit_id' => $this->prodiB->id,
    ]);
});

it('lets admin prodi a see only prodi a students', function () {
    $adminProdi = User::where('username', 'adminprodi')->first();

    $this->actingAs($adminProdi);

    expect(MahasiswaResource::getEloquentQuery()->count())->toBe(30)
        ->and(Mahasiswa::where('academic_unit_id', $this->prodiA->id)->count())->toBe(30)
        ->and(Mahasiswa::where('academic_unit_id', $this->prodiB->id)->count())->toBe(30);
});

it('blocks admin prodi a from editing prodi b student', function () {
    $adminProdi = User::where('username', 'adminprodi')->first();
    $mahasiswaProdiB = Mahasiswa::query()->where('academic_unit_id', $this->prodiB->id)->first();

    expect($this->policy->update($adminProdi, $mahasiswaProdiB))->toBeFalse()
        ->and($this->policy->view($adminProdi, $mahasiswaProdiB))->toBeFalse();
});

it('lets dekan see all students in fakultasnya', function () {
    $dekan = User::where('username', 'dekan')->first();

    $this->actingAs($dekan);

    expect(MahasiswaResource::getEloquentQuery()->count())->toBe(60);
});
