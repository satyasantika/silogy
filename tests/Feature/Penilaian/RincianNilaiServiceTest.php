<?php

use App\Models\User;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use App\Modules\Penilaian\Models\RincianNilaiKelas;
use App\Modules\Penilaian\Services\RincianNilaiService;
use App\Modules\Penilaian\Services\ValidasiNilaiService;
use App\Modules\Penilaian\Support\StatusValidasiNilai;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/../../Support/KelasValidasiFixture.php';

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed([AcademicUnitSeeder::class, RolePermissionSeeder::class, SemesterSeeder::class, EvaluasiSeeder::class]));

if (! function_exists('nilaiPivot')) {
    function nilaiPivot(string $pivotId, string $kmmId): ?float
    {
        $nilai = NilaiMahasiswa::query()
            ->where('subcpmk_komponenpenilaian_id', $pivotId)
            ->where('kelas_mk_mahasiswa_id', $kmmId)
            ->value('nilai');

        return $nilai === null ? null : (float) $nilai;
    }
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

    expect($layanan->bisaDirinci($data['kelas'], $data['komponen']))->toBeFalse();
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
    // Pivot pertama dibuat lebih dulu tetapi kodenya berurutan sesudah pivot kedua.
    $data['pivot']->subcpmk()->update(['kode' => 'SUB-Z']);
    $data['pivot2']->subcpmk()->update(['kode' => 'SUB-B']);
    $layanan = app(RincianNilaiService::class);
    $layanan->nyalakan($data['kelas'], $data['komponen'], User::factory()->create());

    $rincian = $layanan->rincianUntukKelas($data['kelas']);
    expect($rincian)->toHaveKey($data['komponen']->id);
    expect(array_column($rincian[$data['komponen']->id], 'subcpmk_kode'))->toBe(['SUB-B', 'SUB-Z']);
    expect(array_column($rincian[$data['komponen']->id], 'bobot'))->toBe([300.0, 100.0]);

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

it('nyalakan berulang tidak menimpa nilai sub-kolom yang sudah dirinci', function () {
    $data = siapkanKelasDuaSubcpmk(User::factory()->create());
    $layanan = app(RincianNilaiService::class);
    $oleh = User::factory()->create();
    [$kmm1] = $data['kmm'];

    $layanan->nyalakan($data['kelas'], $data['komponen'], $oleh);
    NilaiMahasiswa::query()
        ->where('subcpmk_komponenpenilaian_id', $data['pivot2']->id)
        ->where('kelas_mk_mahasiswa_id', $kmm1->id)
        ->update(['nilai' => 95]);

    $layanan->nyalakan($data['kelas'], $data['komponen'], $oleh);

    expect(nilaiPivot($data['pivot']->id, $kmm1->id))->toBe(80.0);
    expect(nilaiPivot($data['pivot2']->id, $kmm1->id))->toBe(95.0);
    expect(RincianNilaiKelas::query()->count())->toBe(1);
});

it('nyalakan menolak asesmen yang hanya punya satu pivot pada semester kelas', function () {
    $data = siapkanKelasDuaSubcpmk(User::factory()->create());
    $layanan = app(RincianNilaiService::class);
    $semesterLain = Semester::query()
        ->where('id', '!=', $data['kelas']->semester_id)->firstOrFail();
    $data['pivot2']->update(['semester_id' => $semesterLain->id]);

    expect($layanan->bisaDirinci($data['kelas'], $data['komponen']))->toBeFalse();
    try {
        $layanan->nyalakan($data['kelas'], $data['komponen'], User::factory()->create());
        $this->fail('Seharusnya melempar ValidationException');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('rincian');
    }
    expect(RincianNilaiKelas::query()->count())->toBe(0);
});

it('matikan menulis nilai asesmen tertimbang ke semua pivot', function () {
    $data = siapkanKelasDuaSubcpmk(User::factory()->create());
    $layanan = app(RincianNilaiService::class);
    $data['pivot']->update(['bobot' => 10]);
    $data['pivot2']->update(['bobot' => 30]);
    [$kmm1] = $data['kmm'];

    $layanan->nyalakan($data['kelas'], $data['komponen'], User::factory()->create());
    NilaiMahasiswa::query()
        ->where('subcpmk_komponenpenilaian_id', $data['pivot2']->id)
        ->where('kelas_mk_mahasiswa_id', $kmm1->id)
        ->update(['nilai' => 60]);
    $layanan->matikan($data['kelas'], $data['komponen']);

    expect(nilaiPivot($data['pivot']->id, $kmm1->id))->toBe(65.0);
    expect(nilaiPivot($data['pivot2']->id, $kmm1->id))->toBe(65.0);
});

it('nyalakan menyebar nilai yang tampil walau Sub-CPMK berkode terendah dipetakan setelah penilaian', function () {
    $data = siapkanKelasDuaSubcpmk(User::factory()->create());
    // Pivot kedua (belum bernilai) kini berurutan pertama menurut kode.
    $data['pivot2']->subcpmk()->update(['kode' => 'SUB-0']);

    app(RincianNilaiService::class)->nyalakan($data['kelas'], $data['komponen'], User::factory()->create());

    foreach ($data['kmm'] as $i => $kmm) {
        $tampil = [80.0, 60.0][$i];
        expect(nilaiPivot($data['pivot']->id, $kmm->id))->toBe($tampil)
            ->and(nilaiPivot($data['pivot2']->id, $kmm->id))->toBe($tampil);
    }
});

it('nyalakan yang bersamaan dengan pembuatan penanda lain dianggap sudah menyala, tanpa galat', function () {
    $data = siapkanKelasDuaSubcpmk(User::factory()->create());
    $oleh = User::factory()->create();
    $sudahDisisipkan = false;

    // Meniru permintaan lain yang menyisipkan penanda di antara "cari" dan
    // "buat": tepat setelah penanda dicari dan belum ditemukan.
    DB::listen(function (QueryExecuted $query) use (&$sudahDisisipkan, $data, $oleh): void {
        if ($sudahDisisipkan || ! str_starts_with($query->sql, 'select') || ! str_contains($query->sql, 'rincian_nilai_kelas')) {
            return;
        }

        $sudahDisisipkan = true;
        DB::table('rincian_nilai_kelas')->insert([
            'id' => (string) Str::uuid(),
            'kelas_mk_id' => $data['kelas']->id,
            'komponen_penilaian_id' => $data['komponen']->id,
            'dinyalakan_oleh' => $oleh->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    app(RincianNilaiService::class)->nyalakan($data['kelas'], $data['komponen'], $oleh);
    $sudahDisisipkan = true;

    // Penanda milik permintaan lain dipakai apa adanya: tidak menyebar ulang.
    expect(RincianNilaiKelas::query()->count())->toBe(1)
        ->and(nilaiPivot($data['pivot2']->id, $data['kmm'][0]->id))->toBeNull();
});
