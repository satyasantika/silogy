<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use App\Modules\Penilaian\Services\DataLaporanKelasService;
use App\Modules\Penilaian\Services\PenilaianMatrixService;
use App\Modules\Penilaian\Services\RincianNilaiService;
use App\Modules\Penilaian\Services\SidikLaporanKelas;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

require_once __DIR__.'/../../Support/KelasValidasiFixture.php';

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed([AcademicUnitSeeder::class, RolePermissionSeeder::class, SemesterSeeder::class, EvaluasiSeeder::class]));

function tulisNilaiPivot(string $pivotId, string $kmmId, ?float $nilai): void
{
    NilaiMahasiswa::query()->updateOrCreate(
        ['subcpmk_komponenpenilaian_id' => $pivotId, 'kelas_mk_mahasiswa_id' => $kmmId],
        ['nilai' => $nilai],
    );
}

it('sidik kelas tanpa rincian tidak memuat kunci rincian dan nilaiRinci', function () {
    $data = siapkanKelasDuaSubcpmk(User::factory()->create());
    $layanan = app(DataLaporanKelasService::class);

    $muat = $layanan->muat($data['kelas']);
    $untukSidik = $layanan->untukSidik($muat);

    expect($muat['rincian'])->toBe([])
        ->and($muat['nilaiRinci'])->toBe([])
        ->and($untukSidik)->not->toHaveKeys(['rincian', 'nilaiRinci'])
        ->and($layanan->sidik($muat))->toBe(app(SidikLaporanKelas::class)->dariData($untukSidik));
});

it('sidik kelas dengan rincian menyala berbeda dan memuat rincian', function () {
    $data = siapkanKelasDuaSubcpmk(User::factory()->create());
    $layanan = app(DataLaporanKelasService::class);
    $sebelum = $layanan->sidik($layanan->muat($data['kelas']));

    app(RincianNilaiService::class)->nyalakan($data['kelas'], $data['komponen'], User::factory()->create());
    $muat = $layanan->muat($data['kelas']);

    expect($muat['rincian'])->toHaveKey($data['komponen']->id)
        ->and($layanan->untukSidik($muat))->toHaveKeys(['rincian', 'nilaiRinci'])
        ->and($layanan->sidik($muat))->not->toBe($sebelum);
});

it('nilai asesmen dirinci adalah rata-rata tertimbang pivot', function () {
    $data = siapkanKelasDuaSubcpmk(User::factory()->create());
    app(RincianNilaiService::class)->nyalakan($data['kelas'], $data['komponen'], User::factory()->create());
    [$kmm1, $kmm2] = $data['kmm'];

    tulisNilaiPivot($data['pivot']->id, $kmm1->id, 80);
    tulisNilaiPivot($data['pivot2']->id, $kmm1->id, 60);
    tulisNilaiPivot($data['pivot']->id, $kmm2->id, 50);
    tulisNilaiPivot($data['pivot2']->id, $kmm2->id, 50);

    $muat = app(DataLaporanKelasService::class)->muat($data['kelas']);

    // bobot pivot 100 dan 300: (80*100 + 60*300) / 400 = 65
    expect($muat['nilai'][$kmm1->id][$data['komponen']->id])->toBe('65.00')
        ->and($muat['nilai'][$kmm2->id][$data['komponen']->id])->toBe('50.00')
        ->and($muat['nilaiRinci'][$kmm1->id][$data['pivot']->id])->toBe('80.00')
        ->and($muat['nilaiRinci'][$kmm1->id][$data['pivot2']->id])->toBe('60.00');
});

it('sub-kolom terisi sebagian: nilai dihitung dari yang terisi tetapi kelas tidak lengkap dan validasi ditolak', function () {
    $prodi = AcademicUnit::where('type', 'study_program')->firstOrFail();
    $data = siapkanKelasDuaSubcpmk(User::factory()->create(), $prodi);
    app(RincianNilaiService::class)->nyalakan($data['kelas'], $data['komponen'], User::factory()->create());
    [$kmm1, $kmm2] = $data['kmm'];
    foreach ($data['kmm'] as $kmm) {
        tulisNilaiPivot($data['pivot']->id, $kmm->id, 80);
        tulisNilaiPivot($data['pivot2']->id, $kmm->id, 60);
    }
    $validator = User::factory()->create();
    jadikanValidator($validator, $prodi);
    $matrix = app(PenilaianMatrixService::class);

    expect($matrix->kelengkapanKelas($data['kelas'])['status_kelengkapan'])->toBe('lengkap')
        ->and(Gate::forUser($validator)->allows('validasiNilai', $data['kelas']))->toBeTrue();

    NilaiMahasiswa::query()
        ->where('subcpmk_komponenpenilaian_id', $data['pivot2']->id)
        ->where('kelas_mk_mahasiswa_id', $kmm2->id)
        ->delete();

    $kelengkapan = $matrix->kelengkapanKelas($data['kelas']);
    $muat = app(DataLaporanKelasService::class)->muat($data['kelas']);

    expect($kelengkapan['status_kelengkapan'])->toBe('sebagian')
        ->and($kelengkapan['nilai_terisi'])->toBe(1)
        ->and($kelengkapan['mahasiswa_belum_lengkap'])->toBe(1)
        ->and($muat['nilai'][$kmm2->id][$data['komponen']->id])->toBe('80.00')
        ->and(Gate::forUser($validator)->denies('validasiNilai', $data['kelas']))->toBeTrue();
});

it('tanpa rincian, pivot berbeda nilai terbaca dari Sub-CPMK berkode terkecil dan stabil', function () {
    $data = siapkanKelasDuaSubcpmk(User::factory()->create());
    [$kmm1] = $data['kmm'];
    tulisNilaiPivot($data['pivot']->id, $kmm1->id, 80);
    tulisNilaiPivot($data['pivot2']->id, $kmm1->id, 30);
    $matrix = app(PenilaianMatrixService::class);

    $ids = $matrix->pivotIdsByKomponen($matrix->komponenUntukKelas($data['kelas']));
    $rows = $matrix->barisUntukKelas($data['kelas']);

    expect($ids[$data['komponen']->id])->toBe([$data['pivot']->id, $data['pivot2']->id]);

    $pertama = $matrix->nilaiUntukMatrix($rows, $ids);
    $kedua = $matrix->nilaiUntukMatrix($rows, $ids);

    expect($pertama[$kmm1->id][$data['komponen']->id])->toBe('80.00')
        ->and($kedua)->toBe($pertama);
});
