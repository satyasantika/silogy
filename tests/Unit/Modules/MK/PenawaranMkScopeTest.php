<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Support\PenawaranMkScope;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SemesterSeeder::class);

    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->korma = User::query()->where('username', 'korma')->firstOrFail();
    $this->dosen = User::query()->where('username', 'dosen')->firstOrFail();
    $this->semester = Semester::query()->where('status_aktif', true)->firstOrFail();
});

it('koordinator mk hanya melihat penawaran mk mata kuliah miliknya', function () {
    $mkKorma = Mk::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'koordinator_mk_id' => $this->korma->id,
    ]);
    $mkLain = Mk::factory()->create(['academic_unit_id' => $this->prodi->id]);

    MkUnit::factory()->forMk($mkKorma)->forAcademicUnit($this->prodi)->create(['kode' => 'KOR301']);
    MkUnit::factory()->forMk($mkLain)->forAcademicUnit($this->prodi)->create(['kode' => 'KOR302']);

    expect(PenawaranMkScope::penawaranMkQuery($this->korma)->pluck('kode')->all())->toBe(['KOR301']);
});

it('filter kelas mk koordinator mengikuti penawaran dan kelas yang dikoordinasikannya', function () {
    $mkKorma = Mk::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'koordinator_mk_id' => $this->korma->id,
    ]);
    $mkUnit = MkUnit::factory()->forMk($mkKorma)->forAcademicUnit($this->prodi)->create(['kode' => 'KOR401']);

    KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $this->semester->id,
        'kode_kelas' => 'A',
        'koordinator_mk_id' => $this->korma->id,
    ]);

    expect(PenawaranMkScope::mkFilterOptions($this->korma))->toHaveKey($mkKorma->id)
        ->and(PenawaranMkScope::semesterFilterOptions($this->korma))->toHaveKey($this->semester->id)
        ->and(PenawaranMkScope::unitIdsDariPenawaran($this->korma))->toContain($this->prodi->id);
});

it('filter kelas mk dosen mengikuti penawaran kelas yang diampunya', function () {
    $mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id]);
    $mkUnit = MkUnit::factory()->forMk($mk)->forAcademicUnit($this->prodi)->create(['kode' => 'DOS401']);

    KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $this->semester->id,
        'kode_kelas' => 'A',
        'dosen_pengampu_id' => $this->dosen->id,
    ]);

    expect(PenawaranMkScope::mkFilterOptions($this->dosen))->toHaveKey($mk->id)
        ->and(PenawaranMkScope::semesterFilterOptions($this->dosen))->toHaveKey($this->semester->id);
});

it('semester options untuk mk mengikuti tabel semesters', function () {
    $mk = Mk::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'koordinator_mk_id' => $this->korma->id,
    ]);
    $mkUnit = MkUnit::factory()->forMk($mk)->forAcademicUnit($this->prodi)->create(['kode' => 'SEM401']);
    $semesterLain = Semester::query()->where('status_aktif', false)->orderByDesc('kode')->firstOrFail();

    KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $this->semester->id,
        'kode_kelas' => 'A',
        'koordinator_mk_id' => $this->korma->id,
    ]);
    KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $semesterLain->id,
        'kode_kelas' => 'B',
        'koordinator_mk_id' => $this->korma->id,
    ]);

    $semesterIds = Semester::query()->pluck('id')->all();

    expect(PenawaranMkScope::semesterOptionsUntukMk($mk->id, $this->korma))
        ->toHaveKeys($semesterIds)
        ->and(PenawaranMkScope::semesterDefaultUntukMk($mk->id, $this->korma))
        ->toBe($this->semester->id);
});

it('validasi semester impor menerima semester dari master meski belum ada kelas mk', function () {
    $mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id]);

    expect(PenawaranMkScope::validasiSemesterUntukMkImpor(
        $mk->id,
        $this->semester->id,
    ))->toBeNull();
});

it('validasi semester impor menolak semester tidak valid', function () {
    $mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id]);

    expect(PenawaranMkScope::validasiSemesterUntukMkImpor(
        $mk->id,
        (string) Str::uuid(),
    ))->not->toBeNull();
});

it('kurikulum filter koordinator hanya dari unit penawaran mk miliknya', function () {
    $mkKorma = Mk::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'koordinator_mk_id' => $this->korma->id,
    ]);
    MkUnit::factory()->forMk($mkKorma)->forAcademicUnit($this->prodi)->create(['kode' => 'KOR501']);

    $kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Korma',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    $unitIds = PenawaranMkScope::unitIdsDariPenawaran($this->korma);

    expect(KurikulumTerpilih::optionsForUnits($unitIds))
        ->toHaveKey($kurikulum->id);
});
