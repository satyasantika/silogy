<?php

use App\Modules\Kalkulasi\Jobs\RecalkulasiCplJob;
use App\Modules\Kalkulasi\Models\HasilCplMk;
use App\Modules\Kalkulasi\Services\CplMkCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Unit\Kalkulasi\Concerns\SetsUpKalkulasiFixtures;

uses(RefreshDatabase::class, SetsUpKalkulasiFixtures::class);

beforeEach(function () {
    $this->seedKalkulasiBase();

    $this->dasar = $this->createKelasPenilaianDasar();
    $this->dasar['cplMk']->update(['bobot' => 50]);
    $this->jalankanKalkulasiHinggaCpmk($this->dasar, 100);
    app(CplMkCalculator::class)->calculate($this->dasar['kelas']->id, $this->dasar['semester']->id);

    // Meniru baris lama: nilai berbobot basi (0) dari rumus yang masih memakai bobot CPL x BoK.
    HasilCplMk::query()->update(['nilai_berbobot' => 0]);
});

function nilaiBerbobotTersimpan(): float
{
    return (float) HasilCplMk::query()->firstOrFail()->nilai_berbobot;
}

it('menghitung ulang nilai berbobot basi dari nilai akhir dan bobot cpl mk', function () {
    expect(nilaiBerbobotTersimpan())->toBe(0.0);

    $this->artisan('kalkulasi:hitung-ulang-cpl', ['--sync' => true])
        ->expectsOutputToContain('1 kelas')
        ->assertSuccessful();

    expect(nilaiBerbobotTersimpan())->toBe(50.0);
});

it('aman dijalankan berulang kali', function () {
    $this->artisan('kalkulasi:hitung-ulang-cpl', ['--sync' => true])->assertSuccessful();
    $this->artisan('kalkulasi:hitung-ulang-cpl', ['--sync' => true])->assertSuccessful();

    expect(HasilCplMk::query()->count())->toBe(1)
        ->and(nilaiBerbobotTersimpan())->toBe(50.0);
});

it('menerapkan filter unit dan semester yang cocok', function () {
    $this->artisan('kalkulasi:hitung-ulang-cpl', [
        '--sync' => true,
        '--unit' => $this->dasar['mkUnit']->academic_unit_id,
        '--semester' => $this->dasar['semester']->id,
    ])->expectsOutputToContain('1 kelas')->assertSuccessful();

    expect(nilaiBerbobotTersimpan())->toBe(50.0);
});

it('melewati kelas di luar filter unit', function () {
    $this->artisan('kalkulasi:hitung-ulang-cpl', [
        '--sync' => true,
        '--unit' => '00000000-0000-0000-0000-000000000000',
    ])->expectsOutputToContain('0 kelas')->assertSuccessful();

    expect(nilaiBerbobotTersimpan())->toBe(0.0);
});

it('melewati kelas di luar filter semester', function () {
    $this->artisan('kalkulasi:hitung-ulang-cpl', [
        '--sync' => true,
        '--semester' => '00000000-0000-0000-0000-000000000000',
    ])->expectsOutputToContain('0 kelas')->assertSuccessful();

    expect(nilaiBerbobotTersimpan())->toBe(0.0);
});

it('mengantrekan job per kelas tanpa --sync', function () {
    Queue::fake();

    $this->artisan('kalkulasi:hitung-ulang-cpl')->assertSuccessful();

    Queue::assertPushed(
        RecalkulasiCplJob::class,
        fn ($job) => $job->kelasMkId === $this->dasar['kelas']->id,
    );
    expect(nilaiBerbobotTersimpan())->toBe(0.0);
});
