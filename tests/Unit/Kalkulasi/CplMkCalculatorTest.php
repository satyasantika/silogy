<?php

use App\Modules\Kalkulasi\Models\HasilCplMk;
use App\Modules\Kalkulasi\Models\HasilCpmk;
use App\Modules\Kalkulasi\Services\CplMkCalculator;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\MkCpmk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Unit\Kalkulasi\Concerns\SetsUpKalkulasiFixtures;

uses(TestCase::class, RefreshDatabase::class, SetsUpKalkulasiFixtures::class);

beforeEach(function () {
    $this->seedKalkulasiBase();
    $this->calculator = app(CplMkCalculator::class);
});

it('menghitung nilai cpl mk dari rata-rata hasil cpmk ter-mapping', function () {
    $dasar = $this->createKelasPenilaianDasar();
    $this->jalankanKalkulasiHinggaCpmk($dasar, 80);

    $this->calculator->calculate($dasar['kelas']->id, $dasar['semester']->id);

    $hasil = HasilCplMk::query()
        ->where('cpl_id', $dasar['cpl']->id)
        ->where('mk_unit_id', $dasar['mkUnit']->id)
        ->where('kelas_mk_mahasiswa_id', $dasar['kmm']->id)
        ->where('semester_id', $dasar['semester']->id)
        ->first();

    expect($hasil)->not->toBeNull()
        ->and((float) $hasil->nilai_akhir)->toBe(80.0)
        ->and((float) $hasil->nilai_berbobot)->toBe(80.0);
});

it('menghitung nilai berbobot dari bobot cpl mk dan cpl bok', function () {
    $dasar = $this->createKelasPenilaianDasar();
    $dasar['cplMk']->update(['bobot' => 50]);
    $dasar['cplBok']->update(['bobot' => 80]);

    $this->jalankanKalkulasiHinggaCpmk($dasar, 100);
    $this->calculator->calculate($dasar['kelas']->id, $dasar['semester']->id);

    $hasil = HasilCplMk::query()
        ->where('cpl_id', $dasar['cpl']->id)
        ->where('kelas_mk_mahasiswa_id', $dasar['kmm']->id)
        ->first();

    expect((float) $hasil->nilai_akhir)->toBe(100.0)
        ->and((float) $hasil->nilai_berbobot)->toBe(40.0);
});

it('menggunakan avg hasil cpmk dari beberapa cpmk pada cpl mk yang sama', function () {
    $dasar = $this->createKelasPenilaianDasar();
    $cpmk2 = Cpmk::factory()->forMk($dasar['mk'])->create();
    MkCpmk::factory()->forCplMkAndCpmk($dasar['cplMk'], $cpmk2)->create();

    HasilCpmk::query()->create([
        'cpmk_id' => $dasar['cpmk']->id,
        'kelas_mk_mahasiswa_id' => $dasar['kmm']->id,
        'kelas_mk_id' => $dasar['kelas']->id,
        'nilai_akhir' => 60,
    ]);
    HasilCpmk::query()->create([
        'cpmk_id' => $cpmk2->id,
        'kelas_mk_mahasiswa_id' => $dasar['kmm']->id,
        'kelas_mk_id' => $dasar['kelas']->id,
        'nilai_akhir' => 80,
    ]);

    $this->calculator->calculate($dasar['kelas']->id, $dasar['semester']->id);

    $hasil = HasilCplMk::query()
        ->where('cpl_id', $dasar['cpl']->id)
        ->where('kelas_mk_mahasiswa_id', $dasar['kmm']->id)
        ->first();

    expect((float) $hasil->nilai_akhir)->toBe(70.0);
});

it('melewati insert bila belum ada hasil cpmk', function () {
    $dasar = $this->createKelasPenilaianDasar();

    $this->calculator->calculate($dasar['kelas']->id, $dasar['semester']->id);

    expect(HasilCplMk::query()->count())->toBe(0);
});

it('meng-update baris hasil cpl mk via upsert', function () {
    $dasar = $this->createKelasPenilaianDasar();
    $this->jalankanKalkulasiHinggaCpmk($dasar, 70);
    $this->calculator->calculate($dasar['kelas']->id, $dasar['semester']->id);

    HasilCpmk::query()
        ->where('cpmk_id', $dasar['cpmk']->id)
        ->where('kelas_mk_mahasiswa_id', $dasar['kmm']->id)
        ->update(['nilai_akhir' => 95]);

    $this->calculator->calculate($dasar['kelas']->id, $dasar['semester']->id);

    expect(HasilCplMk::query()->count())->toBe(1)
        ->and((float) HasilCplMk::query()->first()->nilai_akhir)->toBe(95.0);
});
