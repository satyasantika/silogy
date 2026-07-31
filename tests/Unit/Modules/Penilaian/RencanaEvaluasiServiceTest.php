<?php

use App\Models\User;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkCpmk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\MK\Support\MkTerpilih;
use App\Modules\Penilaian\Models\Evaluasi;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;
use App\Modules\Penilaian\Services\RencanaEvaluasiService;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SemesterSeeder::class);
    $this->seed(EvaluasiSeeder::class);

    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->korma = User::query()->where('username', 'korma')->firstOrFail();
    $this->semester = Semester::query()->where('status_aktif', true)->firstOrFail();
    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum OBE 2025',
        'tahun' => 2025,
        'is_active' => true,
    ]);

    KurikulumTerpilih::set($this->kurikulum->id);

    $this->mk = Mk::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Aplikasi Komputer Matematika',
        'koordinator_mk_id' => $this->korma->id,
    ]);
    $this->mkUnit = MkUnit::factory()->forMk($this->mk)->forAcademicUnit($this->prodi)->create(['kode' => 'KP21514004']);
    $this->kelas = KelasMk::query()->create([
        'mk_unit_id' => $this->mkUnit->id,
        'semester_id' => $this->semester->id,
        'kode_kelas' => 'A',
        'dosen_pengampu_id' => User::query()->where('username', 'dosen')->firstOrFail()->id,
        'koordinator_mk_id' => $this->korma->id,
    ]);

    MkTerpilih::set($this->mk->id);
});

it('menyusun rencana evaluasi per grup kategori evaluasi', function () {
    $evaluasiQuiz = Evaluasi::query()->where('kode', 'quiz')->firstOrFail();
    $cpmk = Cpmk::factory()->forMk($this->mk)->create(['kode' => 'CPMK-1']);
    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'CPL06']);
    $bok = Bok::factory()->forAcademicUnit($this->prodi)->create();
    $cplBok = CplBok::query()->create([
        'cpl_id' => $cpl->id,
        'bok_id' => $bok->id,
        'bobot' => 100,
    ]);
    $cplMk = CplMk::query()->create([
        'cpl_bok_id' => $cplBok->id,
        'mk_id' => $this->mk->id,
        'bobot' => 100,
    ]);
    $mkCpmk = MkCpmk::factory()->forCplMkAndCpmk($cplMk, $cpmk)->create();
    $subcpmk = Subcpmk::factory()->for($mkCpmk)->create(['kode' => 'SUB-01']);

    $komponen = KomponenPenilaian::query()->create([
        'mk_id' => $this->mk->id,
        'semester_id' => $this->semester->id,
        'evaluasi_id' => $evaluasiQuiz->id,
        'kode' => 'Asesmen01',
        'nama' => 'Kuis Konseptual dan Ringkasan Tertulis Terstruktur',
        'bobot' => 8,
    ]);

    SubcpmkKomponenPenilaian::query()->create([
        'subcpmk_id' => $subcpmk->id,
        'komponen_penilaian_id' => $komponen->id,
        'bobot' => 100,
    ]);

    $rencana = app(RencanaEvaluasiService::class)->build($this->mk->id, $this->semester->id);

    expect($rencana)->not->toBeNull();

    $pengetahuan = collect($rencana['groups'])->firstWhere('label', 'Pengetahuan/Kognitif');
    $quizRow = collect($pengetahuan['rows'])->firstWhere('evaluasi_nama', 'Quiz');

    expect($pengetahuan['bobot_persen'])->toBe(8.0)
        ->and($quizRow['asesmen'])->toHaveCount(1)
        ->and($quizRow['asesmen'][0]['kode'])->toBe('Asesmen01')
        ->and($quizRow['bobot_total'])->toBe(8.0)
        ->and($quizRow['cpl_kodes'])->toContain('CPL06')
        ->and($quizRow['cpmk_kodes'])->toContain('CPMK-1')
        ->and($rencana['total_bobot'])->toBe(8.0);
});

it('tetap menghitung total bobot walau belum ada kelas MK', function () {
    KelasMk::query()->delete();

    $evaluasiQuiz = Evaluasi::query()->where('kode', 'quiz')->firstOrFail();

    KomponenPenilaian::query()->create([
        'mk_id' => $this->mk->id,
        'semester_id' => $this->semester->id,
        'evaluasi_id' => $evaluasiQuiz->id,
        'kode' => 'Asesmen01',
        'nama' => 'Kuis',
        'bobot' => 8,
    ]);

    $rencana = app(RencanaEvaluasiService::class)->build($this->mk->id, $this->semester->id);

    expect($rencana)->not->toBeNull()
        ->and($rencana['total_bobot'])->toBe(8.0);
});

it('format bobot rencana evaluasi', function () {
    $service = app(RencanaEvaluasiService::class);

    expect($service->formatBobot(0))->toBe('0%')
        ->and($service->formatBobot(8))->toBe('8%')
        ->and($service->formatBobot(8.5))->toBe('8,5%');
});
