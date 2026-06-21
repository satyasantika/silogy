<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalkulasi\Models\HasilCplUnit;
use App\Modules\Kalkulasi\Models\HasilSubcpmk;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('simulasi seeder menghasilkan rantai akademik lengkap per prodi', function () {
    $this->seed(DatabaseSeeder::class);

    $prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $semester = Semester::query()->where('status_aktif', true)->firstOrFail();

    expect(Kurikulum::query()->where('academic_unit_id', $prodi->id)->where('is_active', true)->exists())->toBeTrue()
        ->and(KelasMk::query()->where('semester_id', $semester->id)->count())->toBeGreaterThanOrEqual(9)
        ->and(NilaiMahasiswa::query()->count())->toBeGreaterThan(0)
        ->and(HasilSubcpmk::query()->count())->toBeGreaterThan(0)
        ->and(HasilCplUnit::query()->where('academic_unit_id', $prodi->id)->count())->toBeGreaterThan(0);
});

it('simulasi seeder menyiapkan dosen pengampu di setiap level unit', function () {
    $this->seed(DatabaseSeeder::class);

    $semester = Semester::query()->where('status_aktif', true)->firstOrFail();

    foreach ([
        'dosenuniv' => 'university',
        'dosenfak' => 'faculty',
        'dosenjur' => 'department',
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

it('simulasi seeder idempoten saat dijalankan ulang', function () {
    $this->seed(DatabaseSeeder::class);

    $nilaiCount = NilaiMahasiswa::query()->count();
    $hasilCount = HasilCplUnit::query()->count();

    $this->seed(DatabaseSeeder::class);

    expect(NilaiMahasiswa::query()->count())->toBe($nilaiCount)
        ->and(HasilCplUnit::query()->count())->toBe($hasilCount);
});
