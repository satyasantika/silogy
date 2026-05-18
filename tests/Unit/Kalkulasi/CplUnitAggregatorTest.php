<?php

use App\Modules\CPL\Models\Cpl;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kalkulasi\Models\HasilCplMk;
use App\Modules\Kalkulasi\Models\HasilCplMkUnit;
use App\Modules\Kalkulasi\Models\HasilCplUnit;
use App\Modules\Kalkulasi\Services\CplUnitAggregator;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Models\Mk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Unit\Kalkulasi\Concerns\SetsUpKalkulasiFixtures;

uses(TestCase::class, RefreshDatabase::class, SetsUpKalkulasiFixtures::class);

beforeEach(function () {
    $this->seedKalkulasiBase();
    $this->aggregator = app(CplUnitAggregator::class);
});

it('mengagregasi hasil cpl unit prodi dari nilai berbobot hasil cpl mk', function () {
    $dasar = $this->createKelasPenilaianDasar();
    $prodi = AcademicUnit::query()->findOrFail($dasar['mk']->academic_unit_id);

    $this->buatKurikulumAktif($prodi, 75);

    HasilCplMk::query()->create([
        'cpl_id' => $dasar['cpl']->id,
        'mk_unit_id' => $dasar['mkUnit']->id,
        'kelas_mk_mahasiswa_id' => $dasar['kmm']->id,
        'semester_id' => $dasar['semester']->id,
        'nilai_akhir' => 90,
        'nilai_berbobot' => 90,
    ]);

    $this->aggregator->aggregate($prodi->id, $dasar['semester']->id);

    $hasil = HasilCplUnit::query()
        ->where('cpl_id', $dasar['cpl']->id)
        ->where('academic_unit_id', $prodi->id)
        ->where('semester_id', $dasar['semester']->id)
        ->first();

    expect($hasil)->not->toBeNull()
        ->and((float) $hasil->rata_rata)->toBe(90.0)
        ->and($hasil->jumlah_mahasiswa)->toBe(1)
        ->and((float) $hasil->persentase_tercapai)->toBe(100.0);
});

it('menghitung persentase tercapai prodi dari threshold kurikulum', function () {
    $dasar = $this->createKelasPenilaianDasar();
    $prodi = AcademicUnit::query()->findOrFail($dasar['mk']->academic_unit_id);

    $this->buatKurikulumAktif($prodi, 80);

    $mhs2 = Mahasiswa::factory()->create([
        'academic_unit_id' => $prodi->id,
    ]);
    $kmm2 = KelasMkMahasiswa::query()->create([
        'kelas_mk_id' => $dasar['kelas']->id,
        'mahasiswa_id' => $mhs2->id,
    ]);

    HasilCplMk::query()->create([
        'cpl_id' => $dasar['cpl']->id,
        'mk_unit_id' => $dasar['mkUnit']->id,
        'kelas_mk_mahasiswa_id' => $dasar['kmm']->id,
        'semester_id' => $dasar['semester']->id,
        'nilai_akhir' => 85,
        'nilai_berbobot' => 85,
    ]);
    HasilCplMk::query()->create([
        'cpl_id' => $dasar['cpl']->id,
        'mk_unit_id' => $dasar['mkUnit']->id,
        'kelas_mk_mahasiswa_id' => $kmm2->id,
        'semester_id' => $dasar['semester']->id,
        'nilai_akhir' => 70,
        'nilai_berbobot' => 70,
    ]);

    $this->aggregator->aggregate($prodi->id, $dasar['semester']->id);

    expect((float) HasilCplUnit::query()->first()->persentase_tercapai)->toBe(50.0);
});

it('mengagregasi hasil cpl unit non-prodi dari hasil cpl mk unit', function () {
    $univ = AcademicUnit::query()->where('type', 'university')->firstOrFail();
    $semester = Semester::query()
        ->where('status_aktif', true)
        ->firstOrFail();

    $cpl = Cpl::factory()->forAcademicUnit($univ)->create();
    $mk1 = Mk::factory()->create(['academic_unit_id' => $univ->id]);
    $mk2 = Mk::factory()->create(['academic_unit_id' => $univ->id]);

    HasilCplMkUnit::query()->create([
        'cpl_id' => $cpl->id,
        'mk_id' => $mk1->id,
        'academic_unit_id' => $univ->id,
        'semester_id' => $semester->id,
        'rata_rata' => 80,
        'persentase_tercapai' => 100,
        'jumlah_mahasiswa' => 2,
    ]);
    HasilCplMkUnit::query()->create([
        'cpl_id' => $cpl->id,
        'mk_id' => $mk2->id,
        'academic_unit_id' => $univ->id,
        'semester_id' => $semester->id,
        'rata_rata' => 60,
        'persentase_tercapai' => 50,
        'jumlah_mahasiswa' => 2,
    ]);

    $this->aggregator->aggregate($univ->id, $semester->id);

    $hasil = HasilCplUnit::query()
        ->where('cpl_id', $cpl->id)
        ->where('academic_unit_id', $univ->id)
        ->first();

    expect($hasil)->not->toBeNull()
        ->and((float) $hasil->rata_rata)->toBe(70.0)
        ->and($hasil->jumlah_mahasiswa)->toBe(4)
        ->and((float) $hasil->persentase_tercapai)->toBe(75.0);
});

it('tidak membuat hasil cpl unit bila tidak ada sumber agregat', function () {
    $prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $semester = Semester::query()
        ->where('status_aktif', true)
        ->firstOrFail();

    Cpl::factory()->forAcademicUnit($prodi)->create();

    $this->aggregator->aggregate($prodi->id, $semester->id);

    expect(HasilCplUnit::query()->count())->toBe(0);
});
