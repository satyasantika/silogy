<?php

use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkCpmk;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\Penilaian\Models\Evaluasi;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;
use App\Modules\Penilaian\Services\NormalisasiBobotSubcpmkService;
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

    $prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $semester = Semester::query()->where('status_aktif', true)->firstOrFail();
    $mk = Mk::factory()->create(['academic_unit_id' => $prodi->id]);
    $cpl = Cpl::factory()->forAcademicUnit($prodi)->create();
    $bok = Bok::factory()->forAcademicUnit($prodi)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id, 'bobot' => 100]);
    $cplMk = CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $mk->id, 'bobot' => 100]);
    $cpmk = Cpmk::query()->create(['mk_id' => $mk->id, 'kode' => 'CPMK-01', 'deskripsi' => 'Uji']);
    $mkCpmk = MkCpmk::query()->create(['cpl_mk_id' => $cplMk->id, 'cpmk_id' => $cpmk->id, 'bobot' => 100]);

    $this->komponen = KomponenPenilaian::query()->create([
        'mk_id' => $mk->id,
        'semester_id' => $semester->id,
        'evaluasi_id' => Evaluasi::query()->where('kode', 'quiz')->value('id'),
        'kode' => 'Asesmen01',
        'nama' => 'Kuis',
        'bobot' => 7.5,
    ]);

    $this->sub1 = Subcpmk::query()->create([
        'mk_cpmk_id' => $mkCpmk->id, 'semester_id' => $semester->id, 'kode' => 'SUB-01', 'deskripsi' => 'Sub 1',
    ]);
    $this->sub2 = Subcpmk::query()->create([
        'mk_cpmk_id' => $mkCpmk->id, 'semester_id' => $semester->id, 'kode' => 'SUB-02', 'deskripsi' => 'Sub 2',
    ]);
});

it('menormalisasi bobot pivot dengan 2 desimal agar total tepat bobot Asesmen desimal', function () {
    SubcpmkKomponenPenilaian::query()->create(['subcpmk_id' => $this->sub1->id, 'komponen_penilaian_id' => $this->komponen->id, 'bobot' => 1]);
    SubcpmkKomponenPenilaian::query()->create(['subcpmk_id' => $this->sub2->id, 'komponen_penilaian_id' => $this->komponen->id, 'bobot' => 1]);

    $hasil = app(NormalisasiBobotSubcpmkService::class)->normalisasi($this->komponen, desimal: 2);

    expect($hasil['status'])->toBe('dinormalisasi')
        ->and($hasil['jumlah'])->toBe(2);

    $bobots = SubcpmkKomponenPenilaian::query()
        ->where('komponen_penilaian_id', $this->komponen->id)
        ->pluck('bobot')
        ->map(fn ($b) => (float) $b)
        ->all();

    expect(array_sum($bobots))->toBe(7.5)
        ->and($bobots)->toEqualCanonicalizing([3.75, 3.75]);
});

it('menormalisasi default ke satuan: target 7.5 menjadi 8', function () {
    SubcpmkKomponenPenilaian::query()->create(['subcpmk_id' => $this->sub1->id, 'komponen_penilaian_id' => $this->komponen->id, 'bobot' => 1]);
    SubcpmkKomponenPenilaian::query()->create(['subcpmk_id' => $this->sub2->id, 'komponen_penilaian_id' => $this->komponen->id, 'bobot' => 1]);

    $hasil = app(NormalisasiBobotSubcpmkService::class)->normalisasi($this->komponen);

    expect($hasil['status'])->toBe('dinormalisasi');

    $bobots = SubcpmkKomponenPenilaian::query()
        ->where('komponen_penilaian_id', $this->komponen->id)
        ->pluck('bobot')
        ->map(fn ($b) => (float) $b)
        ->all();

    expect(array_sum($bobots))->toBe(8.0)
        ->and($bobots)->toEqualCanonicalizing([4.0, 4.0]);
});

it('mengembalikan status sudah_pas bila total dan desimal sudah sesuai', function () {
    SubcpmkKomponenPenilaian::query()->create(['subcpmk_id' => $this->sub1->id, 'komponen_penilaian_id' => $this->komponen->id, 'bobot' => 5]);
    SubcpmkKomponenPenilaian::query()->create(['subcpmk_id' => $this->sub2->id, 'komponen_penilaian_id' => $this->komponen->id, 'bobot' => 2.5]);

    $hasil = app(NormalisasiBobotSubcpmkService::class)->normalisasi($this->komponen, desimal: 2);

    expect($hasil['status'])->toBe('sudah_pas');
});

it('mengembalikan status kosong bila belum ada Sub-CPMK yang berinteraksi', function () {
    $hasil = app(NormalisasiBobotSubcpmkService::class)->normalisasi($this->komponen);

    expect($hasil['status'])->toBe('kosong');
});
