<?php

use App\Models\User;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\Penilaian\Models\CatatanRtlKelas;
use App\Modules\Penilaian\Services\CatatanRtlService;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SemesterSeeder::class);

    $prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->dosen = User::query()->where('username', 'dosen')->firstOrFail();
    $this->korma = User::query()->where('username', 'korma')->firstOrFail();

    $mk = Mk::factory()->create(['academic_unit_id' => $prodi->id]);
    $mkUnit = MkUnit::factory()->forMk($mk)->forAcademicUnit($prodi)->create();
    $this->kelas = KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => Semester::query()->where('status_aktif', true)->value('id'),
        'kode_kelas' => 'A',
        'dosen_pengampu_id' => $this->dosen->id,
    ]);

    foreach (['cpl1' => 'CPL-01', 'cpl2' => 'CPL-02', 'cpl3' => 'CPL-03'] as $kunci => $kode) {
        $cpl = Cpl::factory()->forAcademicUnit($prodi)->create(['kode' => $kode]);
        $cplBok = CplBok::query()->create([
            'cpl_id' => $cpl->id,
            'bok_id' => Bok::factory()->forAcademicUnit($prodi)->create()->id,
            'bobot' => 100,
        ]);
        CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $mk->id, 'bobot' => 30]);
        $this->{$kunci} = $cpl;
    }

    // CPL-01 tidak tercapai, CPL-02 tercapai, CPL-03 belum punya data.
    $this->ketercapaian = [
        ['cpl_id' => $this->cpl1->id, 'cpl_kode' => 'CPL-01', 'cpl_deskripsi' => 'Deskripsi satu', 'kontribusi' => [], 'rata_rata' => 61.5, 'tercapai' => false],
        ['cpl_id' => $this->cpl2->id, 'cpl_kode' => 'CPL-02', 'cpl_deskripsi' => 'Deskripsi dua', 'kontribusi' => [], 'rata_rata' => 82.0, 'tercapai' => true],
        ['cpl_id' => $this->cpl3->id, 'cpl_kode' => 'CPL-03', 'cpl_deskripsi' => 'Deskripsi tiga', 'kontribusi' => [], 'rata_rata' => null, 'tercapai' => false],
    ];

    $this->service = app(CatatanRtlService::class);
});

it('blok rtl hanya memuat cpl yang punya data tetapi di bawah target', function () {
    $blok = $this->service->blok($this->kelas, $this->ketercapaian);

    expect(array_column($blok['baris'], 'kode'))->toBe(['CPL-01'])
        ->and($blok['baris'][0]['rata_rata'])->toBe(61.5)
        ->and($blok['baris'][0]['analisis'])->toBeNull()
        ->and($blok['semua_tercapai'])->toBeFalse()
        ->and($blok['umum'])->toBeNull()
        ->and($blok['terakhir'])->toBeNull();
});

it('semua tercapai bila tidak ada cpl di bawah target', function () {
    $tercapai = array_map(fn (array $k): array => [...$k, 'rata_rata' => 90.0, 'tercapai' => true], $this->ketercapaian);

    $blok = $this->service->blok($this->kelas, $tercapai);

    expect($blok['baris'])->toBe([])
        ->and($blok['semua_tercapai'])->toBeTrue();
});

it('dosen pengampu menyimpan analisis, rtl, dan catatan umum; simpan ulang memperbarui', function () {
    $this->service->simpan($this->kelas, $this->dosen, [
        'cpl' => [$this->cpl1->id => ['analisis' => 'Latihan soal kurang', 'rencana' => 'Tambah kuis mingguan']],
        'umum' => 'Kehadiran rendah di pekan 5-7.',
    ]);
    $this->service->simpan($this->kelas, $this->dosen, [
        'cpl' => [$this->cpl1->id => ['analisis' => 'Latihan soal kurang', 'rencana' => 'Tambah kuis dan tutor sebaya']],
        'umum' => 'Kehadiran rendah di pekan 5-7.',
    ]);

    $blok = $this->service->blok($this->kelas, $this->ketercapaian);

    expect(CatatanRtlKelas::query()->count())->toBe(2)
        ->and($blok['baris'][0]['analisis'])->toBe('Latihan soal kurang')
        ->and($blok['baris'][0]['rencana'])->toBe('Tambah kuis dan tutor sebaya')
        ->and($blok['umum'])->toBe('Kehadiran rendah di pekan 5-7.')
        ->and($blok['terakhir']['nama'])->toBe($this->dosen->namaDenganGelar());
});

it('catatan cpl yang kemudian tercapai disimpan tetapi disembunyikan, dan muncul lagi bila kembali tidak tercapai', function () {
    $this->service->simpan($this->kelas, $this->dosen, [
        'cpl' => [$this->cpl1->id => ['analisis' => 'A', 'rencana' => 'R']],
    ]);

    $sekarangTercapai = $this->ketercapaian;
    $sekarangTercapai[0] = [...$sekarangTercapai[0], 'rata_rata' => 80.0, 'tercapai' => true];

    expect($this->service->blok($this->kelas, $sekarangTercapai)['baris'])->toBe([])
        ->and(CatatanRtlKelas::query()->where('cpl_id', $this->cpl1->id)->exists())->toBeTrue()
        ->and($this->service->blok($this->kelas, $this->ketercapaian)['baris'][0]['analisis'])->toBe('A');
});

it('selain dosen pengampu kelas tidak boleh menyimpan', function () {
    $this->service->simpan($this->kelas, $this->korma, ['umum' => 'coba']);
})->throws(AuthorizationException::class);

it('cpl di luar mata kuliah kelas diabaikan saat menyimpan', function () {
    $cplLain = Cpl::factory()->forAcademicUnit(AcademicUnit::query()->where('type', 'study_program')->firstOrFail())->create();

    $this->service->simpan($this->kelas, $this->dosen, [
        'cpl' => [$cplLain->id => ['analisis' => 'X', 'rencana' => 'Y']],
    ]);

    expect(CatatanRtlKelas::query()->count())->toBe(0);
});
