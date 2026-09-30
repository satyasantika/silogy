<?php

use App\Models\User;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Models\AcademicUnitUser;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kalender\Support\SemesterTerpilih;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Enums\StatusPerubahanCpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\PerubahanCpmkRequest;
use App\Modules\MK\Services\CpmkPakaiUlangSemesterService;
use App\Modules\MK\Services\PerubahanCpmkService;
use App\Modules\MK\Support\GerbangPerubahanCpmk;
use App\Modules\MK\Support\MkTerpilih;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SemesterSeeder::class);

    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->korma = User::query()->where('username', 'korma')->firstOrFail();
    $this->timkur = User::query()->where('username', 'timkur')->firstOrFail();
    $this->timkurFak = User::query()->where('username', 'timkurfak')->firstOrFail();
    $this->semester = Semester::query()->where('status_aktif', true)->firstOrFail();

    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Uji Persetujuan CPMK',
        'kode' => 'APRCP',
        'tahun' => 2026,
        'is_active' => true,
    ]);
    $this->mk = Mk::factory()->forKurikulum($this->kurikulum)->create([
        'koordinator_mk_id' => $this->korma->id,
        'academic_unit_id' => $this->prodi->id,
    ]);

    $cpl = Cpl::factory()->forKurikulum($this->kurikulum)->create();
    $bok = Bok::factory()->forKurikulum($this->kurikulum)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id]);
    $this->cplMk = CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $this->mk->id, 'bobot' => 100]);

    $this->actingAs($this->korma);
    KurikulumTerpilih::set($this->kurikulum->id);
    MkTerpilih::set($this->mk->id);
    SemesterTerpilih::set($this->mk->id, $this->semester->id);
});

it('semester yang CPMK-nya masih kosong boleh diisi tanpa persetujuan', function () {
    expect(GerbangPerubahanCpmk::bolehUbah($this->mk, $this->semester->id, $this->korma))->toBeTrue()
        ->and(GerbangPerubahanCpmk::butuhPersetujuan($this->mk, $this->semester->id, $this->korma))->toBeFalse();
});

it('begitu ada CPMK berjalan, koordinator perlu persetujuan untuk mengubahnya', function () {
    cpmkUntukSemester($this->mk, $this->semester, ['kode' => 'CPMK01']);

    expect(GerbangPerubahanCpmk::bolehUbah($this->mk, $this->semester->id, $this->korma))->toBeFalse()
        ->and(GerbangPerubahanCpmk::butuhPersetujuan($this->mk, $this->semester->id, $this->korma))->toBeTrue();
});

it('memakai ulang CPMK semester lalu tidak menimbulkan usulan apa pun', function () {
    $semesterLalu = Semester::query()->firstOrCreate(
        ['kode' => '19991'],
        [
            'nama' => 'Ganjil Lampau',
            'jenis' => 'ganjil',
            'tahun_mulai' => 1999,
            'tahun_selesai' => 2000,
            'status_aktif' => false,
        ],
    );

    $cpmk = cpmkUntukSemester($this->mk, $semesterLalu, ['kode' => 'CPMK01']);

    app(CpmkPakaiUlangSemesterService::class)->jalankan(
        [['line' => 1, 'status' => 'baru', 'cpmk_id' => $cpmk->id]],
        $this->mk->id,
        $this->semester->id,
    );

    expect(PerubahanCpmkRequest::query()->count())->toBe(0);
    $this->assertDatabaseHas('cpmk_semester', [
        'cpmk_id' => $cpmk->id,
        'semester_id' => $this->semester->id,
    ]);
});

it('koordinator mengajukan usulan dan tim kurikulum prodi menyetujuinya', function () {
    cpmkUntukSemester($this->mk, $this->semester, ['kode' => 'CPMK01']);

    $usulan = app(PerubahanCpmkService::class)
        ->ajukan($this->mk, $this->semester->id, $this->korma, 'Rumusan CPMK perlu disesuaikan dengan OBE terbaru.');

    expect($usulan->status)->toBe(StatusPerubahanCpmk::Diajukan)
        ->and($usulan->academic_unit_id)->toBe($this->prodi->id)
        // Potret CPMK berjalan ikut tersimpan supaya peninjau tahu apa yang diganti.
        ->and($usulan->ringkasan_usulan)->toHaveCount(1);

    $this->assertDatabaseHas('state_transitions', [
        'model_id' => $usulan->id,
        'to_state' => 'diajukan',
    ]);

    // Selama menunggu, gerbangnya tetap tertutup.
    expect(GerbangPerubahanCpmk::bolehUbah($this->mk, $this->semester->id, $this->korma))->toBeFalse();

    expect($this->timkur->can('setujui', $usulan))->toBeTrue();
    app(PerubahanCpmkService::class)->setujui($usulan, $this->timkur, 'Silakan diperbarui.');

    expect($usulan->fresh()->status)->toBe(StatusPerubahanCpmk::Disetujui)
        ->and(GerbangPerubahanCpmk::bolehUbah($this->mk, $this->semester->id, $this->korma))->toBeTrue();
});

it('tim kurikulum unit induk juga berwenang menyetujui', function () {
    cpmkUntukSemester($this->mk, $this->semester, ['kode' => 'CPMK01']);

    $usulan = app(PerubahanCpmkService::class)
        ->ajukan($this->mk, $this->semester->id, $this->korma, 'Perlu penyesuaian.');

    expect($this->timkurFak->can('setujui', $usulan))->toBeTrue();
});

it('pengusul tidak boleh menyetujui usulannya sendiri', function () {
    cpmkUntukSemester($this->mk, $this->semester, ['kode' => 'CPMK01']);

    $usulan = app(PerubahanCpmkService::class)
        ->ajukan($this->mk, $this->semester->id, $this->korma, 'Perlu penyesuaian.');

    expect($this->korma->can('setujui', $usulan))->toBeFalse();
});

it('tim kurikulum unit lain tidak berwenang menyetujui', function () {
    cpmkUntukSemester($this->mk, $this->semester, ['kode' => 'CPMK01']);

    $usulan = app(PerubahanCpmkService::class)
        ->ajukan($this->mk, $this->semester->id, $this->korma, 'Perlu penyesuaian.');

    // Prodi kedua dibuat sendiri, bukan diandalkan dari seeder: isolasi
    // antar unit adalah jaminan keamanan inti fitur ini dan harus benar-benar
    // diuji, bukan dilewati kalau seeder kebetulan hanya punya satu prodi.
    $prodiLain = AcademicUnit::query()->create([
        'parent_id' => $this->prodi->parent_id,
        'type' => 'study_program',
        'nama' => 'Program Studi Pembanding',
        'kode' => 'PSBAND',
        'status' => 'aktif',
    ]);

    $timkurLain = User::factory()->create();
    $timkurLain->assignRole('Tim Kurikulum');
    AcademicUnitUser::query()->create([
        'academic_unit_id' => $prodiLain->id,
        'user_id' => $timkurLain->id,
        'status_tim_kurikulum' => true,
    ]);

    expect($timkurLain->fresh()->can('setujui', $usulan))->toBeFalse();
});

it('menolak usulan menutup kembali gerbangnya', function () {
    cpmkUntukSemester($this->mk, $this->semester, ['kode' => 'CPMK01']);

    $usulan = app(PerubahanCpmkService::class)
        ->ajukan($this->mk, $this->semester->id, $this->korma, 'Perlu penyesuaian.');

    app(PerubahanCpmkService::class)->tolak($usulan, $this->timkur, 'Belum ada dasar evaluasinya.');

    expect($usulan->fresh()->status)->toBe(StatusPerubahanCpmk::Ditolak)
        ->and(GerbangPerubahanCpmk::bolehUbah($this->mk, $this->semester->id, $this->korma))->toBeFalse();
});

it('tidak boleh ada dua usulan terbuka untuk mk dan semester yang sama', function () {
    cpmkUntukSemester($this->mk, $this->semester, ['kode' => 'CPMK01']);

    app(PerubahanCpmkService::class)
        ->ajukan($this->mk, $this->semester->id, $this->korma, 'Usulan pertama.');

    expect(fn () => app(PerubahanCpmkService::class)
        ->ajukan($this->mk, $this->semester->id, $this->korma, 'Usulan kedua.'))
        ->toThrow(ValidationException::class);
});
