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
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
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

it('state tervalidasi yang tidak disentuh tetap sesuai validasi setelah render ulang dan pembaruan tanpa efek', function () {
    validasiGerbang($this->kelas);

    $test = laporanGerbang($this->kelas);
    expect($test->instance()->laporanSesuaiValidasi())->toBeTrue();

    // Render ulang dari state terhidrasi (round-trip klien).
    $test->call('$refresh');
    expect($test->instance()->laporanSesuaiValidasi())->toBeTrue();

    // Pembaruan properti yang tidak memengaruhi isi laporan.
    $test->set('showKalkulasiBadge', true)
        ->assertSeeHtml('onclick="silogyCetakLaporanLengkap()"')
        ->assertSeeHtml('data-silogy="blok-validasi-nilai"')
        ->assertDontSeeHtml('data-silogy="tanda-air-belum-divalidasi"');
    expect($test->instance()->laporanSesuaiValidasi())->toBeTrue();
});

it('nilai diubah tanpa disimpan pada kelas tervalidasi mengunci cetak, mengembalikannya membuka lagi', function () {
    $kelas = $this->kelas;
    validasiGerbang($kelas);

    $test = laporanGerbang($kelas);
    $nilai = $test->get('nilai');
    $kmmId = array_key_first($nilai);
    $komponenId = array_key_first($nilai[$kmmId]);
    $asli = $nilai[$kmmId][$komponenId];

    $test->set("nilai.{$kmmId}.{$komponenId}", '15')
        ->assertSeeHtml('data-silogy="tanda-air-belum-divalidasi"')
        ->assertDontSeeHtml('onclick="silogyCetakLaporanLengkap()"')
        ->assertDontSeeHtml('data-silogy="blok-validasi-nilai"')
        ->assertSee('Ada perubahan nilai yang belum disimpan — simpan lalu minta validasi ulang.')
        ->assertSee('Perlu validasi ulang')
        ->assertDontSee('Tervalidasi')
        ->assertSet('statusValidasi', 'tervalidasi');

    expect($kelas->validasiNilai()->first()->gugur_at)->toBeNull();

    $test->set("nilai.{$kmmId}.{$komponenId}", $asli)
        ->assertDontSeeHtml('data-silogy="tanda-air-belum-divalidasi"')
        ->assertSeeHtml('onclick="silogyCetakLaporanLengkap()"')
        ->assertSeeHtml('data-silogy="blok-validasi-nilai"')
        ->assertSee('Tervalidasi');
});

it('tempel dari Excel tanpa disimpan pada kelas tervalidasi mengunci cetak', function () {
    validasiGerbang($this->kelas);

    $test = laporanGerbang($this->kelas);
    $rows = $test->get('rows');
    $columns = $test->get('columns');
    $baris = collect($rows)->map(fn (array $row): string => implode("\t", [
        $row['nim'], $row['nama'], ...array_fill(0, count($columns), '12'),
    ]))->implode("\n");
    $nilaiAwal = $test->get('nilai');

    $test->call('applyTempel', $baris)
        ->assertSeeHtml('data-silogy="tanda-air-belum-divalidasi"')
        ->assertDontSeeHtml('onclick="silogyCetakLaporanLengkap()"')
        ->assertDontSeeHtml('data-silogy="blok-validasi-nilai"')
        ->assertSee('Ada perubahan nilai yang belum disimpan — simpan lalu minta validasi ulang.');

    expect($test->get('nilai'))->not->toBe($nilaiAwal)
        ->and($this->kelas->validasiNilai()->first()->gugur_at)->toBeNull();
});

it('sidik dan status validasi terkunci dari klien', function (string $properti, mixed $nilai) {
    laporanGerbang($this->kelas)->set($properti, $nilai);
})->with([
    'sidikLaporan' => ['sidikLaporan', 'palsu'],
    'statusValidasi' => ['statusValidasi', 'tervalidasi'],
    'infoValidasi' => ['infoValidasi', ['nama' => 'X', 'jabatan' => null, 'waktu' => 'kemarin']],
])->throws(CannotUpdateLockedPropertyException::class);
