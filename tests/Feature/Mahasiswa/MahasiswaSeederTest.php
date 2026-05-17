<?php

use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\MahasiswaSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('menghasilkan 30 mahasiswa per program studi', function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MahasiswaSeeder::class);

    $prodies = AcademicUnit::query()->where('type', 'study_program')->get();

    expect($prodies)->not->toBeEmpty();

    foreach ($prodies as $prodi) {
        expect(Mahasiswa::query()->where('academic_unit_id', $prodi->id)->count())->toBe(30);
    }
});

it('mahasiswa memiliki angkatan antara 2022 dan 2025', function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(MahasiswaSeeder::class);

    $angkatans = Mahasiswa::query()->pluck('angkatan')->unique();

    foreach ($angkatans as $angkatan) {
        expect((int) $angkatan)->toBeGreaterThanOrEqual(2022)
            ->and((int) $angkatan)->toBeLessThanOrEqual(2025);
    }
});
