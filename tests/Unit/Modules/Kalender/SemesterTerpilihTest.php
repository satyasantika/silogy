<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kalender\Support\SemesterTerpilih;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Support\MkTerpilih;
use App\Modules\MK\Support\PenawaranMkScope;
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
    $this->korma = User::query()->where('username', 'korma')->firstOrFail();
    $this->semester = Semester::query()->where('status_aktif', true)->firstOrFail();

    $this->actingAs($this->korma);
});

it('semester options dari tabel semesters dan default mengikuti semester aktif', function () {
    $mk = Mk::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'koordinator_mk_id' => $this->korma->id,
    ]);
    $mkUnit = MkUnit::factory()->forMk($mk)->forAcademicUnit($this->prodi)->create(['kode' => 'SEM-KOR']);

    KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $this->semester->id,
        'kode_kelas' => 'A',
    ]);

    MkTerpilih::set($mk->id);

    $semesterIds = Semester::query()->pluck('id')->all();

    expect(PenawaranMkScope::semesterOptionsUntukMk($mk->id, $this->korma))
        ->toHaveKeys($semesterIds)
        ->and(SemesterTerpilih::options($mk->id))
        ->toHaveKeys($semesterIds)
        ->and(SemesterTerpilih::currentId($mk->id))
        ->toBe($this->semester->id);
});

it('semester terpilih tidak berlaku untuk peran selain koordinator mk murni', function () {
    $mk = Mk::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'koordinator_mk_id' => $this->korma->id,
    ]);
    MkTerpilih::set($mk->id);

    $timkur = User::query()->where('username', 'timkur')->firstOrFail();
    $this->actingAs($timkur);

    expect(SemesterTerpilih::berlakuUntukUser())->toBeFalse()
        ->and(SemesterTerpilih::options($mk->id))->toBe([])
        ->and(SemesterTerpilih::currentId($mk->id))->toBeNull();
});
