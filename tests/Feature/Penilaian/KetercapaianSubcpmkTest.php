<?php

use App\Models\User;
use App\Modules\Auth\Support\ActiveRole;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalkulasi\Models\HasilSubcpmk;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\Penilaian\Filament\Pages\InputNilai;
use App\Modules\Penilaian\Filament\Pages\ValidasiNilai;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use App\Modules\Penilaian\Services\DataLaporanKelasService;
use App\Modules\Penilaian\Services\RincianNilaiService;
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
});

function isiNilaiPivotSubcpmk(string $pivotId, string $kmmId, ?float $nilai): void
{
    NilaiMahasiswa::query()->updateOrCreate(
        ['subcpmk_komponenpenilaian_id' => $pivotId, 'kelas_mk_mahasiswa_id' => $kmmId],
        ['nilai' => $nilai],
    );
}

/** Semua peserta kelas diberi skor yang sama per pivot: [pivot => skor]. */
function isiSkorPerPivot(array $data, array $skorPerPivot): void
{
    foreach ($data['kmm'] as $kmm) {
        foreach ($skorPerPivot as $pivotKey => $skor) {
            isiNilaiPivotSubcpmk($data[$pivotKey]->id, $kmm->id, $skor);
        }
    }
}

/** @return array<string, array<string, mixed>> baris C3 menurut kode */
function barisSubcpmk(KelasMk $kelas): array
{
    $baris = app(DataLaporanKelasService::class)->muat($kelas)['ketercapaianSubcpmk'];

    return collect($baris)->keyBy('kode')->all();
}

it('tiga Sub-CPMK pada satu asesmen tak-dirinci: semua tak dibedakan, tanpa Terlemah, status mengikuti target', function () {
    $data = siapkanKelasTigaSubcpmk(User::factory()->create());
    // Tanpa rincian, simpan menyebar satu angka asesmen ke semua pivotnya.
    foreach ($data['kmm'] as $i => $kmm) {
        foreach (['pivot2', 'pivot3'] as $kunci) {
            isiNilaiPivotSubcpmk($data[$kunci]->id, $kmm->id, [80, 60][$i]);
        }
    }

    $baris = barisSubcpmk($data['kelas']);

    expect(array_keys($baris))->toBe(['SUB-A', 'SUB-B', 'SUB-C']);
    foreach ($baris as $b) {
        expect($b['dasar'])->toBe('tak_dibedakan')
            ->and($b['terlemah'])->toBeFalse()
            ->and($b['rata_rata'])->toBe(70.0)
            ->and($b['status'])->toBe('lemah');
    }
});

it('asesmen dirinci 80/60/70 dengan target 75: terpisah, tercapai/lemah/lemah, hanya 60 terlemah', function () {
    $data = siapkanKelasTigaSubcpmk(User::factory()->create());
    app(RincianNilaiService::class)->nyalakan($data['kelas'], $data['komponen'], User::factory()->create());
    isiSkorPerPivot($data, ['pivot' => 80, 'pivot2' => 60, 'pivot3' => 70]);

    $baris = barisSubcpmk($data['kelas']);

    expect($baris['SUB-A'])->toMatchArray(['rata_rata' => 80.0, 'status' => 'tercapai', 'dasar' => 'terpisah', 'terlemah' => false])
        ->and($baris['SUB-B'])->toMatchArray(['rata_rata' => 60.0, 'status' => 'lemah', 'dasar' => 'terpisah', 'terlemah' => true])
        ->and($baris['SUB-C'])->toMatchArray(['rata_rata' => 70.0, 'status' => 'lemah', 'dasar' => 'terpisah', 'terlemah' => false]);
});

it('seri terendah 60/60/80 menandai kedua yang terendah sebagai Terlemah', function () {
    $data = siapkanKelasTigaSubcpmk(User::factory()->create());
    app(RincianNilaiService::class)->nyalakan($data['kelas'], $data['komponen'], User::factory()->create());
    isiSkorPerPivot($data, ['pivot' => 60, 'pivot2' => 60, 'pivot3' => 80]);

    $baris = barisSubcpmk($data['kelas']);

    expect($baris['SUB-A']['terlemah'])->toBeTrue()
        ->and($baris['SUB-B']['terlemah'])->toBeTrue()
        ->and($baris['SUB-C']['terlemah'])->toBeFalse();
});

it('hanya satu Sub-CPMK terukur terpisah: tidak ada tanda Terlemah', function () {
    $data = siapkanKelasTigaSubcpmk(User::factory()->create(), null, true);
    isiSkorPerPivot($data, ['pivot' => 80, 'pivot3' => 40]);

    $baris = barisSubcpmk($data['kelas']);

    expect($baris['SUB-A']['dasar'])->toBe('tak_dibedakan')
        ->and($baris['SUB-B']['dasar'])->toBe('tak_dibedakan')
        ->and($baris['SUB-C']['dasar'])->toBe('terpisah')
        ->and($baris['SUB-C']['rata_rata'])->toBe(40.0)
        ->and(collect($baris)->pluck('terlemah')->contains(true))->toBeFalse();
});

it('mencapai dan dinilai dihitung dari mahasiswa bernilai: 80, 70, null menjadi 1 dari 2', function () {
    $data = siapkanKelasDuaSubcpmk(User::factory()->create());
    isiNilaiPivotSubcpmk($data['pivot']->id, $data['kmm'][0]->id, 80);
    isiNilaiPivotSubcpmk($data['pivot']->id, $data['kmm'][1]->id, 70);
    $kmm3 = KelasMkMahasiswa::query()->create([
        'kelas_mk_id' => $data['kelas']->id,
        'mahasiswa_id' => Mahasiswa::factory()->create(['academic_unit_id' => $data['kelas']->mkUnit->academic_unit_id])->id,
    ]);
    isiNilaiPivotSubcpmk($data['pivot']->id, $kmm3->id, null);

    $baris = barisSubcpmk($data['kelas']);

    expect($baris['SUB-A']['mencapai'])->toBe(1)
        ->and($baris['SUB-A']['dinilai'])->toBe(2)
        ->and($baris['SUB-A']['rata_rata'])->toBe(75.0)
        ->and(HasilSubcpmk::query()->where('kelas_mk_mahasiswa_id', $kmm3->id)->whereNotNull('nilai_akhir')->count())->toBe(0);
});

it('sub-tab laporan InputNilai memuat bagian Ketercapaian Sub-CPMK dan catatan tak dapat dibedakan', function () {
    $dosen = User::where('username', 'dosen')->firstOrFail();
    $this->actingAs($dosen);
    $data = siapkanKelasTigaSubcpmk($dosen);

    Livewire::test(InputNilai::class)
        ->set('kelasMkId', $data['kelas']->id)
        ->assertSee('Ketercapaian Sub-CPMK')
        ->assertSee('Tidak dapat dibedakan')
        ->assertSee('hanya diukur lewat asesmen bersama yang tidak dirinci, sehingga angkanya sama dengan Sub-CPMK lain pada asesmen itu.')
        ->assertDontSee('Terlemah');
});

it('laporan dengan Sub-CPMK terukur terpisah menampilkan Terukur terpisah dan Terlemah', function () {
    $dosen = User::where('username', 'dosen')->firstOrFail();
    $this->actingAs($dosen);
    $data = siapkanKelasTigaSubcpmk($dosen);
    app(RincianNilaiService::class)->nyalakan($data['kelas'], $data['komponen'], $dosen);
    isiSkorPerPivot($data, ['pivot' => 80, 'pivot2' => 60, 'pivot3' => 70]);

    Livewire::test(InputNilai::class)
        ->set('kelasMkId', $data['kelas']->id)
        ->assertSee('Terukur terpisah')
        ->assertSee('Terlemah')
        ->assertDontSee('hanya diukur lewat asesmen bersama');
});

it('halaman ValidasiNilai setelah bukaKelas memuat Ketercapaian Sub-CPMK', function () {
    $prodi = AcademicUnit::where('type', 'study_program')->orderBy('id')->firstOrFail();
    $validator = User::factory()->create();
    jadikanValidator($validator, $prodi);
    $this->actingAs($validator);
    ActiveRole::set($validator, 'validator_nilai');
    $data = siapkanKelasTigaSubcpmk(User::factory()->create(), $prodi);

    Livewire::test(ValidasiNilai::class)
        ->call('bukaKelas', $data['kelas']->id)
        ->assertSee('Ketercapaian Sub-CPMK')
        ->assertSee('Tidak dapat dibedakan');
});

it('ketercapaianSubcpmk tidak masuk sidik sehingga sidik kelas tanpa rincian tidak berubah', function () {
    $data = siapkanKelasTigaSubcpmk(User::factory()->create());
    $layanan = app(DataLaporanKelasService::class);
    $muat = $layanan->muat($data['kelas']);

    expect($muat)->toHaveKey('ketercapaianSubcpmk')
        ->and($layanan->untukSidik($muat))->not->toHaveKeys(['ketercapaianSubcpmk', 'rincian', 'nilaiRinci']);

    $berbeda = $muat;
    $berbeda['ketercapaianSubcpmk'] = [];
    expect($layanan->sidik($berbeda))->toBe($layanan->sidik($muat));
});
