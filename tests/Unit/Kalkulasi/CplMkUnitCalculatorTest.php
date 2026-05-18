<?php

use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kalkulasi\Models\HasilCplMk;
use App\Modules\Kalkulasi\Models\HasilCplMkUnit;
use App\Modules\Kalkulasi\Services\CplMkUnitCalculator;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Unit\Kalkulasi\Concerns\SetsUpKalkulasiFixtures;

uses(TestCase::class, RefreshDatabase::class, SetsUpKalkulasiFixtures::class);

beforeEach(function () {
    $this->seedKalkulasiBase();
    $this->calculator = app(CplMkUnitCalculator::class);
});

it('mengagregasi hasil cpl mk lintas mk unit prodi untuk mk universitas', function () {
    $univ = AcademicUnit::query()->where('type', 'university')->firstOrFail();
    $prodi1 = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $jur = $prodi1->parent;
    $prodi2 = AcademicUnit::factory()->studyProgram($jur)->create([
        'nama' => 'S1 Sistem Informasi',
        'code' => 'S1-SI',
        'kode_pddikti' => '57201',
    ]);

    $semester = Semester::query()
        ->where('status_aktif', true)
        ->firstOrFail();

    $this->buatKurikulumAktif($univ, 75);

    $mk = Mk::factory()->create(['academic_unit_id' => $univ->id]);
    $mkUnit1 = MkUnit::factory()->forMk($mk)->forAcademicUnit($prodi1)->create(['kode' => 'MK-UNIV-P1']);
    $mkUnit2 = MkUnit::factory()->forMk($mk)->forAcademicUnit($prodi2)->create(['kode' => 'MK-UNIV-P2']);

    $cpl = Cpl::factory()->forAcademicUnit($univ)->create();
    $bok = Bok::factory()->forAcademicUnit($univ)->create();
    $cplBok = CplBok::query()->create([
        'cpl_id' => $cpl->id,
        'bok_id' => $bok->id,
        'bobot' => 100,
    ]);
    $cplMk = CplMk::query()->create([
        'cpl_bok_id' => $cplBok->id,
        'mk_id' => $mk->id,
        'bobot' => 100,
    ]);

    $kelas1 = KelasMk::query()->create([
        'mk_unit_id' => $mkUnit1->id,
        'semester_id' => $semester->id,
        'kode_kelas' => 'A',
    ]);
    $kelas2 = KelasMk::query()->create([
        'mk_unit_id' => $mkUnit2->id,
        'semester_id' => $semester->id,
        'kode_kelas' => 'A',
    ]);

    $mhs1 = Mahasiswa::factory()->create(['academic_unit_id' => $prodi1->id]);
    $mhs2 = Mahasiswa::factory()->create(['academic_unit_id' => $prodi2->id]);

    $kmm1 = KelasMkMahasiswa::query()->create([
        'kelas_mk_id' => $kelas1->id,
        'mahasiswa_id' => $mhs1->id,
    ]);
    $kmm2 = KelasMkMahasiswa::query()->create([
        'kelas_mk_id' => $kelas2->id,
        'mahasiswa_id' => $mhs2->id,
    ]);

    HasilCplMk::query()->create([
        'cpl_id' => $cpl->id,
        'mk_unit_id' => $mkUnit1->id,
        'kelas_mk_mahasiswa_id' => $kmm1->id,
        'semester_id' => $semester->id,
        'nilai_akhir' => 80,
        'nilai_berbobot' => 80,
    ]);
    HasilCplMk::query()->create([
        'cpl_id' => $cpl->id,
        'mk_unit_id' => $mkUnit2->id,
        'kelas_mk_mahasiswa_id' => $kmm2->id,
        'semester_id' => $semester->id,
        'nilai_akhir' => 90,
        'nilai_berbobot' => 90,
    ]);

    $this->calculator->calculate($univ->id, $semester->id);

    $hasil = HasilCplMkUnit::query()
        ->where('cpl_id', $cpl->id)
        ->where('mk_id', $mk->id)
        ->where('academic_unit_id', $univ->id)
        ->where('semester_id', $semester->id)
        ->first();

    expect($hasil)->not->toBeNull()
        ->and((float) $hasil->rata_rata)->toBe(85.0)
        ->and($hasil->jumlah_mahasiswa)->toBe(2)
        ->and((float) $hasil->persentase_tercapai)->toBe(100.0);
});

it('menghitung persentase tercapai berdasarkan target kurikulum unit', function () {
    $univ = AcademicUnit::query()->where('type', 'university')->firstOrFail();
    $prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $semester = Semester::query()
        ->where('status_aktif', true)
        ->firstOrFail();

    $this->buatKurikulumAktif($univ, 80);

    $mk = Mk::factory()->create(['academic_unit_id' => $univ->id]);
    $mkUnit = MkUnit::factory()->forMk($mk)->forAcademicUnit($prodi)->create(['kode' => 'MK-TGT']);

    $cpl = Cpl::factory()->forAcademicUnit($univ)->create();
    $bok = Bok::factory()->forAcademicUnit($univ)->create();
    $cplBok = CplBok::query()->create([
        'cpl_id' => $cpl->id,
        'bok_id' => $bok->id,
        'bobot' => 100,
    ]);
    CplMk::query()->create([
        'cpl_bok_id' => $cplBok->id,
        'mk_id' => $mk->id,
        'bobot' => 100,
    ]);

    $kelas = KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $semester->id,
        'kode_kelas' => 'A',
    ]);

    $mhsLulus = Mahasiswa::factory()->create(['academic_unit_id' => $prodi->id]);
    $mhsBawah = Mahasiswa::factory()->create(['academic_unit_id' => $prodi->id]);

    $kmmLulus = KelasMkMahasiswa::query()->create([
        'kelas_mk_id' => $kelas->id,
        'mahasiswa_id' => $mhsLulus->id,
    ]);
    $kmmBawah = KelasMkMahasiswa::query()->create([
        'kelas_mk_id' => $kelas->id,
        'mahasiswa_id' => $mhsBawah->id,
    ]);

    HasilCplMk::query()->create([
        'cpl_id' => $cpl->id,
        'mk_unit_id' => $mkUnit->id,
        'kelas_mk_mahasiswa_id' => $kmmLulus->id,
        'semester_id' => $semester->id,
        'nilai_akhir' => 85,
        'nilai_berbobot' => 85,
    ]);
    HasilCplMk::query()->create([
        'cpl_id' => $cpl->id,
        'mk_unit_id' => $mkUnit->id,
        'kelas_mk_mahasiswa_id' => $kmmBawah->id,
        'semester_id' => $semester->id,
        'nilai_akhir' => 70,
        'nilai_berbobot' => 70,
    ]);

    $this->calculator->calculate($univ->id, $semester->id);

    $hasil = HasilCplMkUnit::query()
        ->where('cpl_id', $cpl->id)
        ->where('mk_id', $mk->id)
        ->where('academic_unit_id', $univ->id)
        ->first();

    expect((float) $hasil->persentase_tercapai)->toBe(50.0);
});

it('tidak membuat baris bila tidak ada hasil cpl mk', function () {
    $univ = AcademicUnit::query()->where('type', 'university')->firstOrFail();
    $semester = Semester::query()
        ->where('status_aktif', true)
        ->firstOrFail();

    Cpl::factory()->forAcademicUnit($univ)->create();

    $this->calculator->calculate($univ->id, $semester->id);

    expect(HasilCplMkUnit::query()->count())->toBe(0);
});
