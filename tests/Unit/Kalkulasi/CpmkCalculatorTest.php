<?php

use App\Modules\CPL\Models\CplMk;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kalkulasi\Models\HasilCpmk;
use App\Modules\Kalkulasi\Models\HasilSubcpmk;
use App\Modules\Kalkulasi\Services\CpmkCalculator;
use App\Modules\Kalkulasi\Services\SubcpmkCalculator;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkCpmk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Models\Subcpmk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Unit\Kalkulasi\Concerns\SetsUpKalkulasiFixtures;

uses(TestCase::class, RefreshDatabase::class, SetsUpKalkulasiFixtures::class);

beforeEach(function () {
    $this->seedKalkulasiBase();
    $this->subcpmkCalculator = app(SubcpmkCalculator::class);
    $this->cpmkCalculator = app(CpmkCalculator::class);
});

it('menghitung rata-rata hasil subcpmk per cpmk (kasus normal)', function () {
    $dasar = $this->createKelasPenilaianDasar();

    $subcpmk2 = $this->buatSubcpmkKedua($dasar['subcpmk']);

    $skp1 = $this->buatKomponenSkp($dasar['kelas'], $dasar['subcpmk'], $dasar['evaluasi'], 'UTS1', 100, 100);
    $skp2 = $this->buatKomponenSkp($dasar['kelas'], $subcpmk2, $dasar['evaluasi'], 'UTS2', 100, 100);

    $this->isiNilai($skp1, $dasar['kmm'], 80);
    $this->isiNilai($skp2, $dasar['kmm'], 100);

    $this->subcpmkCalculator->calculate($dasar['kelas']->id);
    $this->cpmkCalculator->calculate($dasar['kelas']->id);

    $hasil = HasilCpmk::query()
        ->where('cpmk_id', $dasar['cpmk']->id)
        ->where('kelas_mk_mahasiswa_id', $dasar['kmm']->id)
        ->first();

    expect($hasil)->not->toBeNull()
        ->and((float) $hasil->nilai_akhir)->toBe(90.0)
        ->and($hasil->kelas_mk_id)->toBe($dasar['kelas']->id);
});

it('tidak membuat hasil cpmk bila belum ada hasil subcpmk (kasus null)', function () {
    $dasar = $this->createKelasPenilaianDasar();

    $this->buatKomponenSkp($dasar['kelas'], $dasar['subcpmk'], $dasar['evaluasi'], 'UTS', 100, 100);

    $this->cpmkCalculator->calculate($dasar['kelas']->id);

    expect(HasilCpmk::query()->count())->toBe(0);
});

it('menghitung rata-rata hanya dari subcpmk yang punya hasil (kasus partial)', function () {
    $dasar = $this->createKelasPenilaianDasar();

    $subcpmk2 = $this->buatSubcpmkKedua($dasar['subcpmk']);

    $skp1 = $this->buatKomponenSkp($dasar['kelas'], $dasar['subcpmk'], $dasar['evaluasi'], 'UTS1', 100, 100);
    $this->buatKomponenSkp($dasar['kelas'], $subcpmk2, $dasar['evaluasi'], 'UTS2', 100, 100);

    $this->isiNilai($skp1, $dasar['kmm'], 70);

    $this->subcpmkCalculator->calculate($dasar['kelas']->id);
    $this->cpmkCalculator->calculate($dasar['kelas']->id);

    expect((float) HasilCpmk::query()->first()->nilai_akhir)->toBe(70.0);
});

it('meng-update hasil cpmk yang sudah ada', function () {
    $dasar = $this->createKelasPenilaianDasar();
    $skp = $this->buatKomponenSkp($dasar['kelas'], $dasar['subcpmk'], $dasar['evaluasi'], 'UTS', 100, 100);

    $this->isiNilai($skp, $dasar['kmm'], 60);
    $this->subcpmkCalculator->calculate($dasar['kelas']->id);
    $this->cpmkCalculator->calculate($dasar['kelas']->id);

    $this->isiNilai($skp, $dasar['kmm'], 100);
    $this->subcpmkCalculator->calculate($dasar['kelas']->id);
    $this->cpmkCalculator->calculate($dasar['kelas']->id);

    expect(HasilCpmk::query()->count())->toBe(1)
        ->and((float) HasilCpmk::query()->first()->nilai_akhir)->toBe(100.0);
});

it('menghitung cpmk terpisah untuk tiap cpmk di mk yang sama', function () {
    $dasar = $this->createKelasPenilaianDasar();
    $cpmk2 = Cpmk::factory()->forMk($dasar['mk'])->create();

    $cplMk = CplMk::query()->where('mk_id', $dasar['mk']->id)->firstOrFail();
    $mkCpmk2 = MkCpmk::factory()->forCplMkAndCpmk($cplMk, $cpmk2)->create();
    $subcpmk2 = Subcpmk::factory()->for($mkCpmk2)->create(['kode' => 'SUB-B']);

    $skp1 = $this->buatKomponenSkp($dasar['kelas'], $dasar['subcpmk'], $dasar['evaluasi'], 'A', 100, 100);
    $skp2 = $this->buatKomponenSkp($dasar['kelas'], $subcpmk2, $dasar['evaluasi'], 'B', 100, 100);

    $this->isiNilai($skp1, $dasar['kmm'], 50);
    $this->isiNilai($skp2, $dasar['kmm'], 90);

    $this->subcpmkCalculator->calculate($dasar['kelas']->id);
    $this->cpmkCalculator->calculate($dasar['kelas']->id);

    $hasil1 = HasilCpmk::query()->where('cpmk_id', $dasar['cpmk']->id)->first();
    $hasil2 = HasilCpmk::query()->where('cpmk_id', $cpmk2->id)->first();

    expect((float) $hasil1->nilai_akhir)->toBe(50.0)
        ->and((float) $hasil2->nilai_akhir)->toBe(90.0);
});

it('tidak memakai hasil subcpmk dari kelas mk mahasiswa lain', function () {
    $dasar = $this->createKelasPenilaianDasar();
    $lain = $this->createKelasPenilaianDasar('IF-KALK-C');

    $skp = $this->buatKomponenSkp($dasar['kelas'], $dasar['subcpmk'], $dasar['evaluasi'], 'UTS', 100, 100);
    $this->isiNilai($skp, $dasar['kmm'], 85);

    HasilSubcpmk::query()->create([
        'subcpmk_id' => $lain['subcpmk']->id,
        'kelas_mk_mahasiswa_id' => $lain['kmm']->id,
        'kelas_mk_id' => $lain['kelas']->id,
        'nilai_akhir' => 10,
    ]);

    $this->subcpmkCalculator->calculate($dasar['kelas']->id);
    $this->cpmkCalculator->calculate($dasar['kelas']->id);

    expect(HasilCpmk::query()->count())->toBe(1)
        ->and((float) HasilCpmk::query()->first()->nilai_akhir)->toBe(85.0);
});

it('tidak melakukan apa pun bila mk tidak punya cpmk', function () {
    $prodi = AcademicUnit::query()
        ->where('type', 'study_program')
        ->firstOrFail();
    $semester = Semester::query()
        ->where('status_aktif', true)
        ->firstOrFail();
    $mk = Mk::factory()->create(['academic_unit_id' => $prodi->id]);
    $mkUnit = MkUnit::factory()
        ->forMk($mk)
        ->forAcademicUnit($prodi)
        ->create(['kode' => 'IF-NOCPMK']);
    $kelas = KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $semester->id,
        'kode_kelas' => 'Z',
    ]);

    $this->cpmkCalculator->calculate($kelas->id);

    expect(HasilCpmk::query()->count())->toBe(0);
});
