<?php

use App\Modules\AI\Builders\AnalisisCplBuilder;
use App\Modules\AI\Exceptions\AnalisisCplDataKosongException;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kalkulasi\Models\HasilCplMkUnit;
use App\Modules\Kalkulasi\Models\HasilCplUnit;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Unit\Kalkulasi\Concerns\SetsUpKalkulasiFixtures;

uses(TestCase::class, RefreshDatabase::class, SetsUpKalkulasiFixtures::class);

beforeEach(function () {
    $this->seedKalkulasiBase();
    $this->builder = new AnalisisCplBuilder;
});

it('builds prompt for prodi unit', function () {
    $dasar = $this->createKelasPenilaianDasar();
    $prodi = AcademicUnit::query()->findOrFail($dasar['mk']->academic_unit_id);
    $semester = $dasar['semester'];

    $this->buatKurikulumAktif($prodi, 80);

    HasilCplUnit::query()->create([
        'cpl_id' => $dasar['cpl']->id,
        'academic_unit_id' => $prodi->id,
        'semester_id' => $semester->id,
        'rata_rata' => 82.5,
        'persentase_tercapai' => 75.0,
        'jumlah_mahasiswa' => 1,
    ]);

    $hasil = $this->builder
        ->forUnit($prodi, $semester)
        ->withType('ringkasan_cpl')
        ->build();

    expect($hasil)->toHaveKeys(['prompt', 'context'])
        ->and($hasil['context']['unit']['nama'])->toBe($prodi->nama)
        ->and($hasil['context']['semester']['nama'])->toBe($semester->nama)
        ->and($hasil['context']['target_capaian_lulusan'])->toBe(80)
        ->and($hasil['context']['hasil_cpl_unit'])->toHaveCount(1)
        ->and($hasil['context']['hasil_cpl_unit'][0]['kode'])->toBe($dasar['cpl']->kode)
        ->and($hasil['prompt'])->toContain('analis akademik OBE')
        ->and($hasil['prompt'])->toContain($prodi->nama)
        ->and($hasil['prompt'])->toContain('80%')
        ->and($hasil['prompt'])->toContain('Format keluaran: Markdown dengan heading H3');
});

it('includes lowest 5 mk in context', function () {
    $dasar = $this->createKelasPenilaianDasar();
    $prodi = AcademicUnit::query()->findOrFail($dasar['mk']->academic_unit_id);
    $semester = $dasar['semester'];

    $this->buatKurikulumAktif($prodi, 75);

    HasilCplUnit::query()->create([
        'cpl_id' => $dasar['cpl']->id,
        'academic_unit_id' => $prodi->id,
        'semester_id' => $semester->id,
        'rata_rata' => 70,
        'persentase_tercapai' => 60,
        'jumlah_mahasiswa' => 1,
    ]);

    $persentasePerMk = [95, 88, 72, 65, 55, 40, 30];

    foreach ($persentasePerMk as $indeks => $persentase) {
        $mk = Mk::factory()->create([
            'academic_unit_id' => $prodi->id,
            'nama' => 'Mata Kuliah Terendah '.($indeks + 1),
        ]);

        MkUnit::factory()->forMk($mk)->forAcademicUnit($prodi)->create([
            'kode' => 'MK-TERENDAH-'.($indeks + 1),
        ]);

        HasilCplMkUnit::query()->create([
            'cpl_id' => $dasar['cpl']->id,
            'mk_id' => $mk->id,
            'academic_unit_id' => $prodi->id,
            'semester_id' => $semester->id,
            'rata_rata' => $persentase,
            'persentase_tercapai' => $persentase,
            'jumlah_mahasiswa' => 10,
        ]);
    }

    $hasil = $this->builder
        ->forUnit($prodi, $semester)
        ->build();

    expect($hasil['context']['mk_terendah'])->toHaveCount(5);

    $kodeTerendah = collect($hasil['context']['mk_terendah'])->pluck('mk_kode')->all();

    expect($kodeTerendah)->toBe([
        'MK-TERENDAH-7',
        'MK-TERENDAH-6',
        'MK-TERENDAH-5',
        'MK-TERENDAH-4',
        'MK-TERENDAH-3',
    ]);

    expect($hasil['context']['mk_terendah'][0]['persentase_tercapai_terendah'])->toBe(30.0)
        ->and($hasil['context']['mk_terendah'][4]['persentase_tercapai_terendah'])->toBe(72.0);
});

it('throws when no hasil cpl unit data', function () {
    $prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $semester = Semester::query()->where('status_aktif', true)->firstOrFail();

    $this->builder
        ->forUnit($prodi, $semester)
        ->build();
})->throws(AnalisisCplDataKosongException::class);
