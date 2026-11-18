<?php

use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kalkulasi\Models\HasilCplMk;
use App\Modules\Kalkulasi\Models\HasilCplMkUnit;
use App\Modules\Kalkulasi\Models\HasilCplUnit;
use App\Modules\Kalkulasi\Services\CplUnitAggregator;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
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

it('capaian cpl prodi hanya menghitung mahasiswa prodi itu, bukan mahasiswa prodi lain', function () {
    $dasar = $this->createKelasPenilaianDasar();
    $prodi = AcademicUnit::query()->findOrFail($dasar['mk']->academic_unit_id);

    $this->buatKurikulumAktif($prodi, 75);

    $prodiLain = AcademicUnit::query()->where('type', 'study_program')->where('id', '!=', $prodi->id)->first()
        ?? AcademicUnit::query()->create([
            'type' => 'study_program',
            'nama' => 'Prodi Lain Uji',
            'parent_id' => $prodi->parent_id,
        ]);
    $mhsLuar = Mahasiswa::factory()->create(['academic_unit_id' => $prodiLain->id]);
    $kmmLuar = KelasMkMahasiswa::query()->create([
        'kelas_mk_id' => $dasar['kelas']->id,
        'mahasiswa_id' => $mhsLuar->id,
    ]);

    foreach ([[$dasar['kmm']->id, 90], [$kmmLuar->id, 10]] as [$kmmId, $nilai]) {
        HasilCplMk::query()->create([
            'cpl_id' => $dasar['cpl']->id,
            'mk_unit_id' => $dasar['mkUnit']->id,
            'kelas_mk_mahasiswa_id' => $kmmId,
            'semester_id' => $dasar['semester']->id,
            'nilai_akhir' => $nilai,
            'nilai_berbobot' => $nilai,
        ]);
    }

    $this->aggregator->aggregate($prodi->id, $dasar['semester']->id);

    $hasil = HasilCplUnit::query()->where('cpl_id', $dasar['cpl']->id)->firstOrFail();

    expect((float) $hasil->rata_rata)->toBe(90.0)
        ->and($hasil->jumlah_mahasiswa)->toBe(1);
});

it('capaian cpl hanya dari penawaran kurikulum yang sama dengan cpl', function () {
    $dasar = $this->createKelasPenilaianDasar();
    $prodi = AcademicUnit::query()->findOrFail($dasar['mk']->academic_unit_id);

    $kurikulum = $this->buatKurikulumAktif($prodi, 75);
    $dasar['cpl']->update(['kurikulum_id' => $kurikulum->id]);
    $dasar['mkUnit']->update(['kurikulum_id' => Kurikulum::query()->create([
        'academic_unit_id' => $prodi->id, 'nama' => 'Kurikulum Lama', 'tahun' => 2020, 'is_active' => false,
    ])->id]);

    HasilCplMk::query()->create([
        'cpl_id' => $dasar['cpl']->id,
        'mk_unit_id' => $dasar['mkUnit']->id,
        'kelas_mk_mahasiswa_id' => $dasar['kmm']->id,
        'semester_id' => $dasar['semester']->id,
        'nilai_akhir' => 90,
        'nilai_berbobot' => 90,
    ]);

    $this->aggregator->aggregate($prodi->id, $dasar['semester']->id);

    expect(HasilCplUnit::query()->where('cpl_id', $dasar['cpl']->id)->exists())->toBeFalse();
});

it('target capaian mengikuti kurikulum cpl, bukan kurikulum aktif unit', function () {
    $dasar = $this->createKelasPenilaianDasar();
    $prodi = AcademicUnit::query()->findOrFail($dasar['mk']->academic_unit_id);

    $this->buatKurikulumAktif($prodi, 95);
    $kurikulumCpl = Kurikulum::query()->create([
        'academic_unit_id' => $prodi->id, 'nama' => 'Kurikulum CPL', 'tahun' => 2021,
        'is_active' => false, 'target_capaian_lulusan' => 60,
    ]);
    $dasar['cpl']->update(['kurikulum_id' => $kurikulumCpl->id]);
    $dasar['mkUnit']->update(['kurikulum_id' => $kurikulumCpl->id]);

    HasilCplMk::query()->create([
        'cpl_id' => $dasar['cpl']->id,
        'mk_unit_id' => $dasar['mkUnit']->id,
        'kelas_mk_mahasiswa_id' => $dasar['kmm']->id,
        'semester_id' => $dasar['semester']->id,
        'nilai_akhir' => 70,
        'nilai_berbobot' => 70,
    ]);

    $this->aggregator->aggregate($prodi->id, $dasar['semester']->id);

    expect((float) HasilCplUnit::query()->where('cpl_id', $dasar['cpl']->id)->firstOrFail()->persentase_tercapai)->toBe(100.0);
});

/**
 * MK kedua pada prodi/kurikulum yang sama, diikuti mahasiswa yang sama, dengan cpl_mk berbobot.
 *
 * @return array{mk: Mk, mkUnit: MkUnit, kmm: KelasMkMahasiswa}
 */
function tambahMkKedua(array $dasar, ?float $bobot, ?CplBok $cplBok = null): array
{
    $prodi = AcademicUnit::query()->findOrFail($dasar['mk']->academic_unit_id);
    $mk = Mk::factory()->create(['academic_unit_id' => $prodi->id]);
    $mkUnit = MkUnit::factory()->forMk($mk)->forAcademicUnit($prodi)->create([
        'kode' => 'IF-KALK2',
        'kurikulum_id' => $dasar['mkUnit']->kurikulum_id,
    ]);
    $kelas = KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $dasar['semester']->id,
        'kode_kelas' => 'A',
    ]);
    $kmm = KelasMkMahasiswa::query()->create([
        'kelas_mk_id' => $kelas->id,
        'mahasiswa_id' => $dasar['kmm']->mahasiswa_id,
    ]);

    if ($bobot !== null) {
        CplMk::query()->create([
            'cpl_bok_id' => ($cplBok ?? $dasar['cplBok'])->id,
            'mk_id' => $mk->id,
            'bobot' => $bobot,
        ]);
    }

    return compact('mk', 'mkUnit', 'kmm');
}

function buatHasilCplMk(array $dasar, MkUnit $mkUnit, KelasMkMahasiswa $kmm, ?float $nilaiAkhir, ?float $berbobot = null): void
{
    HasilCplMk::query()->create([
        'cpl_id' => $dasar['cpl']->id,
        'mk_unit_id' => $mkUnit->id,
        'kelas_mk_mahasiswa_id' => $kmm->id,
        'semester_id' => $dasar['semester']->id,
        'nilai_akhir' => $nilaiAkhir,
        'nilai_berbobot' => $berbobot ?? $nilaiAkhir,
    ]);
}

function hasilProdi(array $dasar): ?HasilCplUnit
{
    return HasilCplUnit::query()->where('cpl_id', $dasar['cpl']->id)->first();
}

it('nilai cpl prodi tertimbang memakai nilai_akhir bukan potongan nilai_berbobot', function () {
    $dasar = $this->createKelasPenilaianDasar();
    $prodi = AcademicUnit::query()->findOrFail($dasar['mk']->academic_unit_id);
    $this->buatKurikulumAktif($prodi, 75);
    $dasar['cplMk']->update(['bobot' => 30]);

    buatHasilCplMk($dasar, $dasar['mkUnit'], $dasar['kmm'], 90, 27);

    $this->aggregator->aggregate($prodi->id, $dasar['semester']->id);

    expect((float) hasilProdi($dasar)->rata_rata)->toBe(90.0)
        ->and((float) hasilProdi($dasar)->persentase_tercapai)->toBe(100.0);
});

it('dua MK berbobot 60 dan 40 dirata-ratakan tertimbang', function () {
    $dasar = $this->createKelasPenilaianDasar();
    $prodi = AcademicUnit::query()->findOrFail($dasar['mk']->academic_unit_id);
    $this->buatKurikulumAktif($prodi, 75);
    $dasar['cplMk']->update(['bobot' => 60]);
    $dua = tambahMkKedua($dasar, 40);

    buatHasilCplMk($dasar, $dasar['mkUnit'], $dasar['kmm'], 100, 60);
    buatHasilCplMk($dasar, $dua['mkUnit'], $dua['kmm'], 50, 20);

    $this->aggregator->aggregate($prodi->id, $dasar['semester']->id);

    expect((float) hasilProdi($dasar)->rata_rata)->toBe(80.0);
});

it('mk tanpa bobot cpl_mk jatuh ke rata-rata sederhana', function () {
    $dasar = $this->createKelasPenilaianDasar();
    $prodi = AcademicUnit::query()->findOrFail($dasar['mk']->academic_unit_id);
    $this->buatKurikulumAktif($prodi, 75);
    $dua = tambahMkKedua($dasar, null);
    CplMk::query()->delete();

    buatHasilCplMk($dasar, $dasar['mkUnit'], $dasar['kmm'], 80, 0);
    buatHasilCplMk($dasar, $dua['mkUnit'], $dua['kmm'], 60, 0);

    $this->aggregator->aggregate($prodi->id, $dasar['semester']->id);

    expect((float) hasilProdi($dasar)->rata_rata)->toBe(70.0);
});

it('dua kelas pada mk yang sama dirata-ratakan dulu per mk', function () {
    $dasar = $this->createKelasPenilaianDasar();
    $prodi = AcademicUnit::query()->findOrFail($dasar['mk']->academic_unit_id);
    $this->buatKurikulumAktif($prodi, 75);
    $dasar['cplMk']->update(['bobot' => 50]);
    $dua = tambahMkKedua($dasar, 50);

    $kelasB = KelasMk::query()->create([
        'mk_unit_id' => $dasar['mkUnit']->id,
        'semester_id' => $dasar['semester']->id,
        'kode_kelas' => 'B',
    ]);
    $kmmB = KelasMkMahasiswa::query()->create([
        'kelas_mk_id' => $kelasB->id,
        'mahasiswa_id' => $dasar['kmm']->mahasiswa_id,
    ]);

    buatHasilCplMk($dasar, $dasar['mkUnit'], $dasar['kmm'], 100);
    buatHasilCplMk($dasar, $dasar['mkUnit'], $kmmB, 60);
    buatHasilCplMk($dasar, $dua['mkUnit'], $dua['kmm'], 100);

    $this->aggregator->aggregate($prodi->id, $dasar['semester']->id);

    // MK pertama = 80 (bukan 100/60 terpisah), MK kedua = 100, bobot sama -> 90.
    expect((float) hasilProdi($dasar)->rata_rata)->toBe(90.0);
});

it('satu cpl ke mk lewat dua bok menjumlahkan bobot', function () {
    $dasar = $this->createKelasPenilaianDasar();
    $prodi = AcademicUnit::query()->findOrFail($dasar['mk']->academic_unit_id);
    $this->buatKurikulumAktif($prodi, 75);
    $dasar['cplMk']->update(['bobot' => 30]);
    $bok2 = Bok::factory()->forAcademicUnit($prodi)->create();
    $cplBok2 = CplBok::query()->create([
        'cpl_id' => $dasar['cpl']->id,
        'bok_id' => $bok2->id,
        'bobot' => 50,
    ]);
    CplMk::query()->create([
        'cpl_bok_id' => $cplBok2->id,
        'mk_id' => $dasar['mk']->id,
        'bobot' => 20,
    ]);
    $dua = tambahMkKedua($dasar, 25);

    buatHasilCplMk($dasar, $dasar['mkUnit'], $dasar['kmm'], 100);
    buatHasilCplMk($dasar, $dua['mkUnit'], $dua['kmm'], 60);

    $this->aggregator->aggregate($prodi->id, $dasar['semester']->id);

    // Bobot MK pertama 30+20 = 50, MK kedua 25 -> (100*50 + 60*25)/75 = 86.67.
    expect((float) hasilProdi($dasar)->rata_rata)->toBe(86.67);
});

it('baris nilai_akhir null diabaikan', function () {
    $dasar = $this->createKelasPenilaianDasar();
    $prodi = AcademicUnit::query()->findOrFail($dasar['mk']->academic_unit_id);
    $this->buatKurikulumAktif($prodi, 75);

    buatHasilCplMk($dasar, $dasar['mkUnit'], $dasar['kmm'], null, 50);

    $this->aggregator->aggregate($prodi->id, $dasar['semester']->id);

    expect(hasilProdi($dasar))->toBeNull();
});

it('rata-rata prodi adalah rerata nilai per mahasiswa, bukan rerata baris', function () {
    $dasar = $this->createKelasPenilaianDasar();
    $prodi = AcademicUnit::query()->findOrFail($dasar['mk']->academic_unit_id);
    $this->buatKurikulumAktif($prodi, 75);
    $dasar['cplMk']->update(['bobot' => 50]);
    $dua = tambahMkKedua($dasar, 50);

    $mhsY = Mahasiswa::factory()->create(['academic_unit_id' => $prodi->id]);
    $kmmY = KelasMkMahasiswa::query()->create([
        'kelas_mk_id' => $dasar['kelas']->id,
        'mahasiswa_id' => $mhsY->id,
    ]);

    buatHasilCplMk($dasar, $dasar['mkUnit'], $dasar['kmm'], 100);
    buatHasilCplMk($dasar, $dua['mkUnit'], $dua['kmm'], 100);
    buatHasilCplMk($dasar, $dasar['mkUnit'], $kmmY, 0);

    $this->aggregator->aggregate($prodi->id, $dasar['semester']->id);

    $hasil = hasilProdi($dasar);

    expect((float) $hasil->rata_rata)->toBe(50.0)
        ->and((float) $hasil->persentase_tercapai)->toBe(50.0)
        ->and($hasil->jumlah_mahasiswa)->toBe(2);
});

it('mk berbobot nol dikeluarkan dari rata-rata tertimbang mahasiswa', function () {
    $dasar = $this->createKelasPenilaianDasar();
    $prodi = AcademicUnit::query()->findOrFail($dasar['mk']->academic_unit_id);
    $this->buatKurikulumAktif($prodi, 75);
    $dasar['cplMk']->update(['bobot' => 40]);
    $dua = tambahMkKedua($dasar, null);

    buatHasilCplMk($dasar, $dasar['mkUnit'], $dasar['kmm'], 90);
    buatHasilCplMk($dasar, $dua['mkUnit'], $dua['kmm'], 10);

    $this->aggregator->aggregate($prodi->id, $dasar['semester']->id);

    expect((float) hasilProdi($dasar)->rata_rata)->toBe(90.0);
});

it('memangkas hasil cpl unit prodi untuk cpl tanpa sumber lagi, tanpa menyentuh baris lain', function () {
    $dasar = $this->createKelasPenilaianDasar();
    $prodi = AcademicUnit::query()->findOrFail($dasar['mk']->academic_unit_id);
    $univ = AcademicUnit::query()->where('type', 'university')->firstOrFail();
    $this->buatKurikulumAktif($prodi, 75);

    $cplB = Cpl::factory()->forAcademicUnit($prodi)->create();
    $cplB->update(['kurikulum_id' => $dasar['cpl']->kurikulum_id]);
    foreach ([$dasar['cpl'], $cplB] as $cpl) {
        HasilCplMk::query()->create([
            'cpl_id' => $cpl->id, 'mk_unit_id' => $dasar['mkUnit']->id,
            'kelas_mk_mahasiswa_id' => $dasar['kmm']->id, 'semester_id' => $dasar['semester']->id,
            'nilai_akhir' => 90, 'nilai_berbobot' => 90,
        ]);
    }

    $semesterLain = Semester::query()->create([
        'kode' => '20991',
        'nama' => 'Ganjil 2099/2100',
        'tahun_mulai' => 2099,
        'tahun_selesai' => 2100,
        'jenis' => 'ganjil',
        'status_aktif' => false,
    ]);
    $barisSemesterLain = HasilCplUnit::query()->create([
        'cpl_id' => $dasar['cpl']->id, 'academic_unit_id' => $prodi->id, 'semester_id' => $semesterLain->id,
        'rata_rata' => 10, 'persentase_tercapai' => 0, 'jumlah_mahasiswa' => 1,
    ]);
    $barisUnitLain = HasilCplUnit::query()->create([
        'cpl_id' => $dasar['cpl']->id, 'academic_unit_id' => $univ->id, 'semester_id' => $dasar['semester']->id,
        'rata_rata' => 10, 'persentase_tercapai' => 0, 'jumlah_mahasiswa' => 1,
    ]);

    $this->aggregator->aggregate($prodi->id, $dasar['semester']->id);

    $cari = fn (Cpl $cpl) => HasilCplUnit::query()
        ->where('cpl_id', $cpl->id)->where('academic_unit_id', $prodi->id)->where('semester_id', $dasar['semester']->id);
    $barisB = $cari($cplB)->firstOrFail();
    expect($cari($dasar['cpl'])->exists())->toBeTrue();

    HasilCplMk::query()->where('cpl_id', $dasar['cpl']->id)->delete();
    $this->aggregator->aggregate($prodi->id, $dasar['semester']->id);

    expect($cari($dasar['cpl'])->exists())->toBeFalse()
        ->and(HasilCplUnit::query()->whereKey($barisSemesterLain->id)->exists())->toBeTrue()
        ->and(HasilCplUnit::query()->whereKey($barisUnitLain->id)->exists())->toBeTrue();

    $barisBBaru = $cari($cplB)->firstOrFail();
    expect($barisBBaru->id)->toBe($barisB->id)
        ->and($barisBBaru->created_at->toDateTimeString())->toBe($barisB->created_at->toDateTimeString());
});
