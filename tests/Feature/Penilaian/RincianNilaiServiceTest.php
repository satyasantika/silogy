<?php

use App\Models\User;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use App\Modules\Penilaian\Models\RincianNilaiKelas;
use App\Modules\Penilaian\Services\RincianNilaiService;
use App\Modules\Penilaian\Services\ValidasiNilaiService;
use App\Modules\Penilaian\Support\StatusValidasiNilai;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/../../Support/KelasValidasiFixture.php';

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed([AcademicUnitSeeder::class, RolePermissionSeeder::class, SemesterSeeder::class, EvaluasiSeeder::class]));

function nilaiPivot(string $pivotId, string $kmmId): ?float
{
    $nilai = NilaiMahasiswa::query()
        ->where('subcpmk_komponenpenilaian_id', $pivotId)
        ->where('kelas_mk_mahasiswa_id', $kmmId)
        ->value('nilai');

    return $nilai === null ? null : (float) $nilai;
}

it('nilaiAsesmen: rata-rata tertimbang atas sub-kolom yang terisi', function () {
    $layanan = app(RincianNilaiService::class);
    $bobot = ['a' => 10.0, 'b' => 30.0];

    expect($layanan->nilaiAsesmen(['a' => '80', 'b' => '60'], $bobot))->toBe(65.0);
    expect($layanan->nilaiAsesmen(['a' => '80', 'b' => null], $bobot))->toBe(80.0);
    expect($layanan->nilaiAsesmen(['a' => null, 'b' => null], $bobot))->toBeNull();
});

it('nyalakan menolak asesmen yang hanya punya satu pivot', function () {
    $data = siapkanKelasValidasi(User::factory()->create());
    $layanan = app(RincianNilaiService::class);

    expect($layanan->bisaDirinci($data['komponen']))->toBeFalse();
    expect(fn () => $layanan->nyalakan($data['kelas'], $data['komponen'], User::factory()->create()))
        ->toThrow(ValidationException::class);
    expect(RincianNilaiKelas::query()->count())->toBe(0);
});

it('nyalakan lalu matikan tanpa mengubah angka tidak menggeser nilai', function () {
    $data = siapkanKelasDuaSubcpmk(User::factory()->create());
    $layanan = app(RincianNilaiService::class);
    foreach ($data['kmm'] as $kmm) {
        $kmm->update(['nilai_angka' => 70]);
    }

    $layanan->nyalakan($data['kelas'], $data['komponen'], User::factory()->create());
    expect(RincianNilaiKelas::query()->count())->toBe(1);
    $layanan->matikan($data['kelas'], $data['komponen']);

    expect(RincianNilaiKelas::query()->count())->toBe(0);
    foreach ($data['kmm'] as $i => $kmm) {
        $semula = [80.0, 60.0][$i];
        expect(nilaiPivot($data['pivot']->id, $kmm->id))->toBe($semula);
        expect(nilaiPivot($data['pivot2']->id, $kmm->id))->toBe($semula);
        expect((float) $kmm->fresh()->nilai_angka)->toBe(70.0);
    }
});

it('nyalakan menyebar nilai pivot pertama menurut urutan ke semua pivot', function () {
    $data = siapkanKelasDuaSubcpmk(User::factory()->create());
    [$kmm1] = $data['kmm'];
    NilaiMahasiswa::query()->create([
        'subcpmk_komponenpenilaian_id' => $data['pivot2']->id,
        'kelas_mk_mahasiswa_id' => $kmm1->id,
        'nilai' => 90,
    ]);
    NilaiMahasiswa::query()
        ->where('subcpmk_komponenpenilaian_id', $data['pivot']->id)
        ->where('kelas_mk_mahasiswa_id', $kmm1->id)
        ->update(['nilai' => 60]);

    app(RincianNilaiService::class)->nyalakan($data['kelas'], $data['komponen'], User::factory()->create());

    expect(nilaiPivot($data['pivot']->id, $kmm1->id))->toBe(60.0);
    expect(nilaiPivot($data['pivot2']->id, $kmm1->id))->toBe(60.0);
});

it('rincianUntukKelas mengurutkan pivot dan mengabaikan penanda asesmen yang kini satu pivot', function () {
    $data = siapkanKelasDuaSubcpmk(User::factory()->create());
    $layanan = app(RincianNilaiService::class);
    $layanan->nyalakan($data['kelas'], $data['komponen'], User::factory()->create());

    $rincian = $layanan->rincianUntukKelas($data['kelas']);
    expect($rincian)->toHaveKey($data['komponen']->id);
    expect(array_column($rincian[$data['komponen']->id], 'subcpmk_kode'))->toBe(['SUB-A', 'SUB-B']);
    expect(array_column($rincian[$data['komponen']->id], 'bobot'))->toBe([100.0, 300.0]);

    $data['pivot2']->delete();

    expect($layanan->rincianUntukKelas($data['kelas']))->toBe([]);
});

it('nyalakan yang mengubah nilai pivot menggugurkan validasi kelas', function () {
    $data = siapkanKelasDuaSubcpmk(User::factory()->create());
    $validasi = app(ValidasiNilaiService::class);
    $validasi->validasi($data['kelas'], User::factory()->create(), str_repeat('a', 64));
    expect($validasi->status($data['kelas']))->toBe(StatusValidasiNilai::Tervalidasi);

    app(RincianNilaiService::class)->nyalakan($data['kelas'], $data['komponen'], User::factory()->create());

    expect($validasi->status($data['kelas']))->not->toBe(StatusValidasiNilai::Tervalidasi);
});
