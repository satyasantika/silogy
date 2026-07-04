<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kalkulasi\Models\HasilCplUnit;
use App\Modules\Kalkulasi\Models\HasilSubcpmk;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use Database\Seeders\SimulasiSistemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('menempatkan prodi pendidikan matematika langsung di bawah fkip', function () {
    $this->seed(SimulasiSistemSeeder::class);

    $prodi = AcademicUnit::query()
        ->where('type', 'study_program')
        ->where('kode_pddikti', '84202')
        ->firstOrFail();

    expect($prodi->nama)->toContain('Pendidikan Matematika')
        ->and($prodi->parent->isFaculty())->toBeTrue()
        ->and($prodi->parent->nama)->toContain('Keguruan');
});

it('seeder simulasi menghasilkan rantai akademik lengkap untuk prodi', function () {
    $this->seed(SimulasiSistemSeeder::class);

    $prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $semester = Semester::query()->where('status_aktif', true)->firstOrFail();

    expect(Kurikulum::query()->where('academic_unit_id', $prodi->id)->where('is_active', true)->exists())->toBeTrue()
        ->and(KelasMk::query()->where('semester_id', $semester->id)->count())->toBeGreaterThanOrEqual(8)
        ->and(NilaiMahasiswa::query()->count())->toBeGreaterThan(0)
        ->and(HasilSubcpmk::query()->count())->toBeGreaterThan(0)
        ->and(HasilCplUnit::query()->where('academic_unit_id', $prodi->id)->count())->toBeGreaterThan(0);
});

it('seeder simulasi mengagregasi hasil cpl hingga fakultas dan universitas', function () {
    $this->seed(SimulasiSistemSeeder::class);

    $fkip = AcademicUnit::query()->where('type', 'faculty')->firstOrFail();
    $univ = AcademicUnit::query()->where('type', 'university')->firstOrFail();

    expect(HasilCplUnit::query()->where('academic_unit_id', $fkip->id)->count())->toBeGreaterThan(0)
        ->and(HasilCplUnit::query()->where('academic_unit_id', $univ->id)->count())->toBeGreaterThan(0);
});

it('seeder simulasi menyiapkan dosen pengampu pada kelas prodi, fakultas, dan universitas', function () {
    $this->seed(SimulasiSistemSeeder::class);

    $semester = Semester::query()->where('status_aktif', true)->firstOrFail();

    foreach ([
        'dosenuniv' => 'university',
        'dosenfak' => 'faculty',
        'dosen' => 'study_program',
    ] as $username => $type) {
        $user = User::query()->where('username', $username)->first();
        $unit = AcademicUnit::query()->where('type', $type)->firstOrFail();

        expect($user)->not->toBeNull()
            ->and($user->hasRole('Dosen Pengampu'))->toBeTrue()
            ->and($user->academicUnitUsers()->where('academic_unit_id', $unit->id)->exists())->toBeTrue()
            ->and(
                KelasMk::query()
                    ->where('dosen_pengampu_id', $user->id)
                    ->where('semester_id', $semester->id)
                    ->exists()
            )->toBeTrue();
    }
});

it('seeder simulasi idempoten saat dijalankan ulang', function () {
    $this->seed(SimulasiSistemSeeder::class);

    $nilaiCount = NilaiMahasiswa::query()->count();
    $hasilCount = HasilCplUnit::query()->count();

    $this->seed(SimulasiSistemSeeder::class);

    expect(NilaiMahasiswa::query()->count())->toBe($nilaiCount)
        ->and(HasilCplUnit::query()->count())->toBe($hasilCount);
});
