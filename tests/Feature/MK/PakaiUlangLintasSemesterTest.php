<?php

use App\Models\User;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kalender\Support\SemesterTerpilih;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Filament\Resources\SubcpmkResource\Pages\ListSubcpmks;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkCpmk;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\MK\Services\CpmkPakaiUlangSemesterService;
use App\Modules\MK\Services\SubcpmkPakaiUlangSemesterService;
use App\Modules\MK\Support\MkTerpilih;
use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;
use App\Modules\Penilaian\Services\KomponenPenilaianPakaiUlangSemesterService;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SemesterSeeder::class);
    $this->seed(EvaluasiSeeder::class);

    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->korma = User::query()->where('username', 'korma')->firstOrFail();

    $this->semesterLalu = Semester::query()->where('status_aktif', true)->firstOrFail();
    // Semester tujuan harus BERKODE LEBIH BESAR dari semester sumber:
    // KaskadeReuseSemester menentukan "CPMK baru di semester ini" dengan
    // membandingkan semesters.kode, bukan urutan pembuatan baris.
    $this->semesterBaru = Semester::query()->firstOrCreate(
        ['kode' => '29991'],
        [
            'nama' => 'Ganjil Uji Pakai Ulang',
            'jenis' => 'ganjil',
            'tahun_mulai' => 2999,
            'tahun_selesai' => 3000,
            'status_aktif' => false,
        ],
    );

    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Uji Pakai Ulang',
        'kode' => 'PKULG',
        'tahun' => 2026,
        'is_active' => true,
    ]);
    $this->mk = Mk::factory()->forKurikulum($this->kurikulum)->create(['koordinator_mk_id' => $this->korma->id]);

    $cpl = Cpl::factory()->forKurikulum($this->kurikulum)->create();
    $bok = Bok::factory()->forKurikulum($this->kurikulum)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id]);
    $cplMk = CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $this->mk->id, 'bobot' => 100]);

    $this->cpmk = cpmkUntukSemester($this->mk, $this->semesterLalu, ['kode' => 'CPMK01']);
    $this->mkCpmk = MkCpmk::query()->create([
        'cpl_mk_id' => $cplMk->id,
        'cpmk_id' => $this->cpmk->id,
        'bobot' => 100,
    ]);

    $this->actingAs($this->korma);
    KurikulumTerpilih::set($this->kurikulum->id);
    MkTerpilih::set($this->mk->id);
    SemesterTerpilih::set($this->mk->id, $this->semesterBaru->id);
});

it('memakai ulang CPMK semester lalu tanpa menambah baris cpmk', function () {
    $jumlahSebelum = Cpmk::query()->count();

    $service = app(CpmkPakaiUlangSemesterService::class);
    $baris = $service->resolveBaris($this->semesterLalu->id, $this->mk->id, $this->semesterBaru->id);

    expect($baris)->toHaveCount(1)
        ->and($baris[0]['status'])->toBe('baru');

    $service->jalankan($baris, $this->mk->id, $this->semesterBaru->id);

    // Yang bertambah hanya lampiran semester — ID CPMK-nya sama persis.
    expect(Cpmk::query()->count())->toBe($jumlahSebelum);
    $this->assertDatabaseHas('cpmk_semester', [
        'cpmk_id' => $this->cpmk->id,
        'semester_id' => $this->semesterBaru->id,
    ]);
});

it('melampirkan dua kali bersifat idempoten', function () {
    $service = app(CpmkPakaiUlangSemesterService::class);

    foreach (range(1, 2) as $ignored) {
        $baris = $service->resolveBaris($this->semesterLalu->id, $this->mk->id, $this->semesterBaru->id);
        $service->jalankan($baris, $this->mk->id, $this->semesterBaru->id);
    }

    expect(DB::table('cpmk_semester')
        ->where('cpmk_id', $this->cpmk->id)
        ->where('semester_id', $this->semesterBaru->id)
        ->count())->toBe(1);
});

it('memakai ulang Sub-CPMK memakai ID lama, bukan menyalin baris baru', function () {
    $subcpmk = subcpmkUntukSemester($this->mkCpmk, $this->semesterLalu, ['kode' => 'SUB01']);

    // CPMK induknya harus lebih dulu berlaku di semester tujuan.
    app(CpmkPakaiUlangSemesterService::class)->jalankan(
        app(CpmkPakaiUlangSemesterService::class)
            ->resolveBaris($this->semesterLalu->id, $this->mk->id, $this->semesterBaru->id),
        $this->mk->id,
        $this->semesterBaru->id,
    );

    $service = app(SubcpmkPakaiUlangSemesterService::class);
    $baris = $service->resolveBaris($this->semesterLalu->id, $this->mk->id, $this->semesterBaru->id);

    expect($baris)->toHaveCount(1)
        ->and($baris[0]['status'])->toBe('baru');

    $service->jalankan($baris, $this->mk->id, $this->semesterBaru->id);

    expect(Subcpmk::query()->count())->toBe(1);
    $this->assertDatabaseHas('subcpmk_semester', [
        'subcpmk_id' => $subcpmk->id,
        'semester_id' => $this->semesterBaru->id,
    ]);
});

it('memblokir Sub-CPMK milik CPMK yang baru disusun untuk semester ini', function () {
    subcpmkUntukSemester($this->mkCpmk, $this->semesterLalu, ['kode' => 'SUB01']);

    // CPMK sengaja TIDAK dipakai ulang; sebagai gantinya CPMK baru dibuat
    // khusus untuk semester tujuan. Turunannya wajib baru.
    cpmkUntukSemester($this->mk, $this->semesterBaru, ['kode' => 'CPMK-BARU']);

    $baris = app(SubcpmkPakaiUlangSemesterService::class)
        ->resolveBaris($this->semesterLalu->id, $this->mk->id, $this->semesterBaru->id);

    expect($baris)->toHaveCount(1)
        ->and($baris[0]['status'])->toBe('terblokir')
        ->and($baris[0]['keterangan'])->toContain('harus baru');
});

it('memblokir Asesmen yang mengukur Sub-CPMK yang belum berlaku di semester tujuan', function () {
    $subcpmk = subcpmkUntukSemester($this->mkCpmk, $this->semesterLalu, ['kode' => 'SUB01']);
    $komponen = komponenUntukSemester($this->mk, $this->semesterLalu, ['kode' => 'UTS', 'bobot' => 100]);

    SubcpmkKomponenPenilaian::query()->create([
        'subcpmk_id' => $subcpmk->id,
        'komponen_penilaian_id' => $komponen->id,
        'semester_id' => $this->semesterLalu->id,
        'bobot' => 100,
    ]);

    $baris = app(KomponenPenilaianPakaiUlangSemesterService::class)
        ->resolveBaris($this->semesterLalu->id, $this->mk->id, $this->semesterBaru->id);

    expect($baris)->toHaveCount(1)
        ->and($baris[0]['status'])->toBe('terblokir')
        ->and($baris[0]['keterangan'])->toContain('SUB01');
});

it('memakai ulang Asesmen membawa bobot dan pemetaan Sub-CPMK ke semester tujuan', function () {
    $subcpmk = subcpmkUntukSemester($this->mkCpmk, $this->semesterLalu, ['kode' => 'SUB01']);
    $komponen = komponenUntukSemester($this->mk, $this->semesterLalu, ['kode' => 'UTS', 'bobot' => 40]);

    SubcpmkKomponenPenilaian::query()->create([
        'subcpmk_id' => $subcpmk->id,
        'komponen_penilaian_id' => $komponen->id,
        'semester_id' => $this->semesterLalu->id,
        'bobot' => 40,
    ]);

    // Rantai induknya dipakai ulang lebih dulu: CPMK, lalu Sub-CPMK.
    subcpmkUntukSemester($this->mkCpmk, $this->semesterBaru, ['kode' => 'SUB01']);
    cpmkUntukSemester($this->mk, $this->semesterBaru, ['kode' => 'CPMK01']);

    $service = app(KomponenPenilaianPakaiUlangSemesterService::class);
    $baris = $service->resolveBaris($this->semesterLalu->id, $this->mk->id, $this->semesterBaru->id);

    expect($baris[0]['status'])->toBe('baru');

    $service->jalankan($baris, $this->mk->id, $this->semesterLalu->id, $this->semesterBaru->id);

    expect($komponen->bobotUntukSemester($this->semesterBaru->id))->toBe(40.0);
    $this->assertDatabaseHas('subcpmk_komponenpenilaian', [
        'subcpmk_id' => $subcpmk->id,
        'komponen_penilaian_id' => $komponen->id,
        'semester_id' => $this->semesterBaru->id,
    ]);
});

it('modal pakai ulang menampilkan opsi sepaket dan tanpa pilihan timpa', function () {
    subcpmkUntukSemester($this->mkCpmk, $this->semesterLalu, ['kode' => 'SUB01']);

    Livewire\Livewire::test(ListSubcpmks::class)
        ->assertActionExists('pakaiUlangAntarSemester')
        ->mountAction('pakaiUlangAntarSemester')
        ->assertSchemaStateSet(['cakupan' => 'paket'])
        // "Timpa data lama" tidak lagi ada: di bawah semantik pakai-ulang,
        // duplikatnya adalah baris yang sama — menimpanya justru mengubah
        // semester sumber.
        ->assertSchemaComponentDoesNotExist('mode_duplikat');
});
