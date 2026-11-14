<?php

use App\Models\User;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use App\Modules\Penilaian\Models\RincianNilaiKelas;
use App\Modules\Penilaian\Services\PenilaianMatrixService;
use App\Modules\Penilaian\Services\RincianNilaiService;
use App\Modules\Penilaian\Services\SubcpmkAsesmenPemetaanService;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/../../Support/KelasValidasiFixture.php';

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed([AcademicUnitSeeder::class, RolePermissionSeeder::class, SemesterSeeder::class, EvaluasiSeeder::class]));

if (! function_exists('aturSkorPivot')) {
    function aturSkorPivot(string $pivotId, string $kmmId, float $nilai): void
    {
        NilaiMahasiswa::query()
            ->where('subcpmk_komponenpenilaian_id', $pivotId)
            ->where('kelas_mk_mahasiswa_id', $kmmId)
            ->update(['nilai' => $nilai]);
    }
}

if (! function_exists('kelasDirinciTersimpan')) {
    /**
     * Kelas dua Sub-CPMK yang dirinci: peserta pertama SUB-A 80, SUB-B 40
     * (bobot pivot 100 dan 300, jadi nilai asesmen 50); nilai akhir tersimpan.
     */
    function kelasDirinciTersimpan(): array
    {
        $data = siapkanKelasDuaSubcpmk(User::factory()->create());
        app(RincianNilaiService::class)->nyalakan($data['kelas'], $data['komponen'], User::factory()->create());
        aturSkorPivot($data['pivot2']->id, $data['kmm'][0]->id, 40);
        app(PenilaianMatrixService::class)->simpanNilaiAkhirKelas($data['kelas']);

        expect((float) $data['kmm'][0]->fresh()->nilai_angka)->toBe(50.0);

        return $data;
    }
}

it('bobot pivot asesmen dirinci dibagi ulang lewat layanan pemetaan: nilai akhir tersimpan ikut dihitung ulang', function () {
    $data = kelasDirinciTersimpan();

    SubcpmkAsesmenPemetaanService::redistribusiBobotMerata($data['komponen'], (string) $data['pivot']->semester_id);

    // Bobot kini 50/50: (80 + 40) / 2 = 60.
    $kmm1 = $data['kmm'][0]->fresh();
    expect((float) $kmm1->nilai_angka)->toBe(60.0)
        ->and($kmm1->nilai_huruf)->toBe('C+')
        ->and((float) $data['kmm'][1]->fresh()->nilai_angka)->toBe(60.0);
});

it('Sub-CPMK baru dipetakan ke asesmen dirinci: nilai akhir tersimpan mengikuti bobot baru', function () {
    $data = kelasDirinciTersimpan();
    $subcpmkBaru = Subcpmk::factory()
        ->for($data['pivot']->subcpmk->mkCpmk)
        ->untukSemester((string) $data['pivot']->semester_id)
        ->create(['kode' => 'SUB-C']);

    SubcpmkAsesmenPemetaanService::petakanSubcpmk($data['komponen'], $subcpmkBaru, (string) $data['pivot']->semester_id);

    // Tiga pivot berbobot sama; SUB-C belum terisi: (80 + 40) / 2 = 60.
    expect((float) $data['kmm'][0]->fresh()->nilai_angka)->toBe(60.0);
});

it('pivot asesmen dirinci dihapus: nilai akhir tersimpan dihitung ulang dari pivot yang tersisa', function () {
    $data = siapkanKelasTigaSubcpmk(User::factory()->create());
    [$kmm1] = $data['kmm'];
    app(RincianNilaiService::class)->nyalakan($data['kelas'], $data['komponen'], User::factory()->create());
    aturSkorPivot($data['pivot2']->id, $kmm1->id, 40);
    aturSkorPivot($data['pivot3']->id, $kmm1->id, 90);
    app(PenilaianMatrixService::class)->simpanNilaiAkhirKelas($data['kelas']);
    // (80×100 + 40×300 + 90×200) / 600
    expect((float) $kmm1->fresh()->nilai_angka)->toBe(63.33);

    $data['pivot3']->delete();

    // (80×100 + 40×300) / 400
    expect((float) $kmm1->fresh()->nilai_angka)->toBe(50.0)
        ->and(RincianNilaiKelas::query()->count())->toBe(1);
});

it('kelas tanpa rincian tidak disentuh saat bobot pivot berubah', function () {
    $data = siapkanKelasDuaSubcpmk(User::factory()->create());
    foreach ($data['kmm'] as $kmm) {
        $kmm->update(['nilai_angka' => 70, 'nilai_huruf' => 'B']);
    }

    SubcpmkAsesmenPemetaanService::redistribusiBobotMerata($data['komponen'], (string) $data['pivot']->semester_id);

    foreach ($data['kmm'] as $kmm) {
        expect((float) $kmm->fresh()->nilai_angka)->toBe(70.0);
    }
});

it('nilai akhir yang belum pernah tersimpan tetap kosong saat pemetaan asesmen dirinci berubah', function () {
    $data = siapkanKelasDuaSubcpmk(User::factory()->create());
    app(RincianNilaiService::class)->nyalakan($data['kelas'], $data['komponen'], User::factory()->create());

    SubcpmkAsesmenPemetaanService::redistribusiBobotMerata($data['komponen'], (string) $data['pivot']->semester_id);

    expect($data['kmm'][0]->fresh()->nilai_angka)->toBeNull();
});

it('asesmen dirinci tinggal satu pivot: penanda dihapus dan tidak hidup lagi saat pemetaan bertambah', function () {
    $data = siapkanKelasDuaSubcpmk(User::factory()->create());
    $layanan = app(RincianNilaiService::class);
    $layanan->nyalakan($data['kelas'], $data['komponen'], User::factory()->create());

    $data['pivot2']->delete();

    expect(RincianNilaiKelas::query()->count())->toBe(0);

    $subcpmkBaru = Subcpmk::factory()
        ->for($data['pivot']->subcpmk->mkCpmk)
        ->untukSemester((string) $data['pivot']->semester_id)
        ->create(['kode' => 'SUB-C']);
    SubcpmkAsesmenPemetaanService::petakanSubcpmk($data['komponen'], $subcpmkBaru, (string) $data['pivot']->semester_id);

    expect($layanan->rincianUntukKelas($data['kelas']))->toBe([]);
});
