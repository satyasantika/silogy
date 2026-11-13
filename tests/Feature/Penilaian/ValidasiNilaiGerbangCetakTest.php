<?php

use App\Models\User;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Penilaian\Filament\Pages\InputNilai;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use App\Modules\Penilaian\Services\ValidasiNilaiService;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

require_once __DIR__.'/../../Support/KelasValidasiFixture.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed([AcademicUnitSeeder::class, RolePermissionSeeder::class, SemesterSeeder::class, EvaluasiSeeder::class]);

    $this->dosen = User::where('username', 'dosen')->firstOrFail();
    $this->actingAs($this->dosen);
    $this->kelas = siapkanKelasValidasi($this->dosen)['kelas'];
});

function laporanGerbang(KelasMk $kelas)
{
    return Livewire::test(InputNilai::class)->set('kelasMkId', $kelas->id);
}

function validasiGerbang(KelasMk $kelas): void
{
    $sidik = laporanGerbang($kelas)->get('sidikLaporan');
    expect($sidik)->not->toBeNull();

    $validator = User::factory()->create(['full_name' => 'Validator Uji Gerbang']);
    app(ValidasiNilaiService::class)->validasi($kelas, $validator, $sidik);
}

it('kelas belum divalidasi: tombol cetak terkunci dan tanda air ada', function () {
    laporanGerbang($this->kelas)
        ->assertSeeHtml('data-silogy="status-validasi-nilai"')
        ->assertSee('Belum divalidasi')
        ->assertSeeHtml('data-silogy="alasan-cetak-terkunci"')
        ->assertSee('Belum divalidasi Validator Nilai.')
        ->assertSeeHtml('data-silogy="tanda-air-belum-divalidasi"')
        ->assertSee('BELUM DIVALIDASI')
        ->assertDontSeeHtml('onclick="silogyCetakLaporanLengkap()"')
        ->assertDontSeeHtml('data-silogy="blok-validasi-nilai"');
});

it('tanda air muncul sekali, di luar kontainer sub-tab laporan lengkap', function () {
    $html = laporanGerbang($this->kelas)->html();

    expect(substr_count($html, 'data-silogy="tanda-air-belum-divalidasi"'))->toBe(1)
        ->and(strpos($html, 'data-silogy="tanda-air-belum-divalidasi"'))
        ->toBeLessThan(strpos($html, "x-show=\"subTab === 'portofolio'\""));
});

it('kelas tervalidasi: tombol aktif, blok validasi memuat nama, tanpa tanda air', function () {
    validasiGerbang($this->kelas);

    laporanGerbang($this->kelas)
        ->assertSeeHtml('data-silogy="status-validasi-nilai"')
        ->assertSee('Tervalidasi')
        ->assertSeeHtml('onclick="silogyCetakLaporanLengkap()"')
        ->assertDontSeeHtml('data-silogy="alasan-cetak-terkunci"')
        ->assertSeeHtml('data-silogy="blok-validasi-nilai"')
        ->assertSee('Divalidasi oleh Validator Uji Gerbang')
        ->assertDontSeeHtml('data-silogy="tanda-air-belum-divalidasi"')
        ->assertDontSee('BELUM DIVALIDASI');
});

it('kelas gugur: alasan "Data berubah sejak divalidasi" dan tanda air', function () {
    validasiGerbang($this->kelas);
    NilaiMahasiswa::query()->first()->update(['nilai' => 10]);

    laporanGerbang($this->kelas)
        ->assertSee('Perlu validasi ulang')
        ->assertSee('Data berubah sejak divalidasi — perlu validasi ulang.')
        ->assertSeeHtml('data-silogy="tanda-air-belum-divalidasi"')
        ->assertDontSeeHtml('onclick="silogyCetakLaporanLengkap()"')
        ->assertDontSeeHtml('data-silogy="blok-validasi-nilai"');
});
