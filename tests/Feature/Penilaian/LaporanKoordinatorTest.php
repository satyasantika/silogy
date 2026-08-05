<?php

use App\Models\User;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkCpmk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\MK\Support\MkTerpilih;
use App\Modules\Penilaian\Filament\Pages\LaporanKoordinator;
use App\Modules\Penilaian\Models\Evaluasi;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SemesterSeeder::class);
    $this->seed(EvaluasiSeeder::class);

    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->korma = User::where('username', 'korma')->firstOrFail();
    $this->dosen = User::where('username', 'dosen')->firstOrFail();
    $this->semester = Semester::query()->where('status_aktif', true)->firstOrFail();

    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Uji Laporan Koordinator',
        'tahun' => 2026,
        'is_active' => true,
    ]);
    KurikulumTerpilih::set($this->kurikulum->id);

    $this->mk = Mk::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'koordinator_mk_id' => $this->korma->id,
    ]);
    $this->mkUnit = MkUnit::factory()->forMk($this->mk)->forAcademicUnit($this->prodi)->create(['kode' => 'LAP101']);

    $this->actingAs($this->korma);
    MkTerpilih::set($this->mk->id);
});

/**
 * Membangun rantai CPL-CPMK-SubCPMK + satu KomponenPenilaian (mk+semester,
 * bobot 100, terpetakan penuh) — dipakai bersama oleh kelas manapun pada
 * MK/semester ini, karena KomponenPenilaian kini berskala mk+semester
 * (bukan per kelas).
 */
function siapkanPenugasanLaporanKoordinator(Mk $mk, AcademicUnit $prodi, string $semesterId): void
{
    $cpmk = Cpmk::factory()->forMk($mk)->create();
    $cpl = Cpl::factory()->forAcademicUnit($prodi)->create();
    $bok = Bok::factory()->forAcademicUnit($prodi)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id, 'bobot' => 100]);
    $cplMk = CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $mk->id, 'bobot' => 100]);
    $mkCpmk = MkCpmk::factory()->forCplMkAndCpmk($cplMk, $cpmk)->create();
    $subcpmk = Subcpmk::factory()->for($mkCpmk)->create();

    $evaluasi = Evaluasi::query()->where('kode', 'uts')->firstOrFail();
    $komponen = KomponenPenilaian::query()->create([
        'mk_id' => $mk->id,
        'semester_id' => $semesterId,
        'evaluasi_id' => $evaluasi->id,
        'kode' => 'UTS',
        'nama' => 'UTS',
        'bobot' => 100,
    ]);
    SubcpmkKomponenPenilaian::query()->create([
        'subcpmk_id' => $subcpmk->id,
        'komponen_penilaian_id' => $komponen->id,
        'bobot' => 100,
    ]);
}

/**
 * @return array{kelas: KelasMk, kmm: KelasMkMahasiswa}
 */
function buatKelasUntukDosen(MkUnit $mkUnit, User $dosen, AcademicUnit $prodi, string $semesterId, string $kodeKelas): array
{
    $kelas = KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $semesterId,
        'kode_kelas' => $kodeKelas,
        'dosen_pengampu_id' => $dosen->id,
    ]);

    $mahasiswa = Mahasiswa::factory()->create(['academic_unit_id' => $prodi->id]);
    $kmm = KelasMkMahasiswa::query()->create([
        'kelas_mk_id' => $kelas->id,
        'mahasiswa_id' => $mahasiswa->id,
    ]);

    return ['kelas' => $kelas, 'kmm' => $kmm];
}

it('koordinator MK dapat mengakses Laporan, dosen pengampu murni tidak', function () {
    expect(LaporanKoordinator::canAccess())->toBeTrue();

    $this->actingAs($this->dosen);
    expect(LaporanKoordinator::canAccess())->toBeFalse();
});

it('menampilkan laporan berdasarkan mata kuliah terpilih tanpa membutuhkan kurikulum terpilih', function () {
    // Kasus produksi: korma tanpa pivot academic_unit_users → KurikulumTerpilih
    // gagal di-set saat pilih MK, tapi laporan tetap harus jalan dari MkTerpilih.
    $this->korma->academicUnits()->detach();
    KurikulumTerpilih::set(null);
    session()->forget(KurikulumTerpilih::SESSION_KEY);
    MkTerpilih::set($this->mk->id);

    siapkanPenugasanLaporanKoordinator($this->mk, $this->prodi, $this->semester->id);
    buatKelasUntukDosen($this->mkUnit, $this->dosen, $this->prodi, $this->semester->id, 'A');

    expect(KurikulumTerpilih::current())->toBeNull()
        ->and(MkTerpilih::currentId())->toBe($this->mk->id)
        ->and(LaporanKoordinator::canAccess())->toBeTrue();

    Livewire::test(LaporanKoordinator::class)
        ->assertSeeHtml('data-silogy="banner-header-panel"')
        ->assertDontSee('Belum ada kurikulum terpilih', escape: false)
        ->assertSee('Pilih kelas', escape: false)
        ->assertSee('Kelas A', escape: false)
        ->assertDontSee('Pilih Kelas MK', escape: false);
});

it('menampilkan seluruh kelas pada MK lintas dosen pengampu, masing-masing dengan keterangan dosennya', function () {
    siapkanPenugasanLaporanKoordinator($this->mk, $this->prodi, $this->semester->id);

    $dosenLain = User::factory()->create([
        'username' => 'dosenlainlaporan',
        'email' => 'dosenlainlaporan@silogy.test',
        'full_name' => 'Dosen Lain Laporan',
    ]);
    $dosenLain->assignRole('Dosen Pengampu');

    buatKelasUntukDosen($this->mkUnit, $this->dosen, $this->prodi, $this->semester->id, 'A');
    buatKelasUntukDosen($this->mkUnit, $dosenLain, $this->prodi, $this->semester->id, 'B');

    $html = Livewire::test(LaporanKoordinator::class)->html();

    expect($html)
        ->toContain('Kelas A')
        ->toContain('Dosen: '.$this->dosen->full_name)
        ->toContain('Kelas B')
        ->toContain('Dosen: '.$dosenLain->full_name);
});

it('identitas laporan mengikuti dosen pengampu kelas yang sedang dipilih, bukan koordinator yang login', function () {
    siapkanPenugasanLaporanKoordinator($this->mk, $this->prodi, $this->semester->id);

    $dosenLain = User::factory()->create([
        'username' => 'dosenlainidentitas',
        'email' => 'dosenlainidentitas@silogy.test',
        'full_name' => 'Dosen Lain Identitas',
    ]);
    $dosenLain->assignRole('Dosen Pengampu');

    $kelasA = buatKelasUntukDosen($this->mkUnit, $this->dosen, $this->prodi, $this->semester->id, 'A');
    $kelasB = buatKelasUntukDosen($this->mkUnit, $dosenLain, $this->prodi, $this->semester->id, 'B');

    $test = Livewire::test(LaporanKoordinator::class);

    expect($test->instance()->getIdentitasMkProperty()['dosen'])->toBe($this->dosen->full_name);

    $test->call('pilihKelas', $kelasB['kelas']->id);

    expect($test->instance()->getIdentitasMkProperty()['dosen'])->toBe($dosenLain->full_name);
});

it('tidak bisa membuka kelas MK di luar cakupan koordinasi', function () {
    siapkanPenugasanLaporanKoordinator($this->mk, $this->prodi, $this->semester->id);
    $kelasSaya = buatKelasUntukDosen($this->mkUnit, $this->dosen, $this->prodi, $this->semester->id, 'A');

    $mkOrangLain = Mk::factory()->create(['academic_unit_id' => $this->prodi->id]);
    $mkUnitOrangLain = MkUnit::factory()->forMk($mkOrangLain)->forAcademicUnit($this->prodi)->create(['kode' => 'LAIN101']);
    siapkanPenugasanLaporanKoordinator($mkOrangLain, $this->prodi, $this->semester->id);
    $kelasOrangLain = buatKelasUntukDosen($mkUnitOrangLain, $this->dosen, $this->prodi, $this->semester->id, 'A');

    $test = Livewire::test(LaporanKoordinator::class);

    expect($test->instance()->kelasMkId)->toBe($kelasSaya['kelas']->id);

    $test->call('pilihKelas', $kelasOrangLain['kelas']->id);

    expect($test->instance()->kelasMkId)->toBe($kelasSaya['kelas']->id);
});

it('menomori langkah pilih semester, pilih kelas, lalu pilih tab Laporan', function () {
    siapkanPenugasanLaporanKoordinator($this->mk, $this->prodi, $this->semester->id);
    buatKelasUntukDosen($this->mkUnit, $this->dosen, $this->prodi, $this->semester->id, 'A');

    $html = Livewire::test(LaporanKoordinator::class)->html();

    expect($html)
        ->not->toContain('Urutan kerja')
        ->not->toContain('data-silogy="petunjuk-urutan-laporan"')
        ->toContain('data-silogy="langkah-kelas"')
        ->toContain('Pilih kelas')
        ->toContain('data-silogy="langkah-tab-laporan"')
        ->toContain('Pilih tab Laporan')
        ->toContain('silogy-langkah-batas');
});

it('menggabungkan banner, semester, kelas, tab, dan data laporan dalam satu kartu', function () {
    siapkanPenugasanLaporanKoordinator($this->mk, $this->prodi, $this->semester->id);
    buatKelasUntukDosen($this->mkUnit, $this->dosen, $this->prodi, $this->semester->id, 'A');

    $html = Livewire::test(LaporanKoordinator::class)->html();

    expect(substr_count($html, 'data-silogy="banner-header-panel"'))->toBe(1)
        ->and(strpos($html, 'data-silogy="banner-header-panel"'))
        ->toBeLessThan(strpos($html, 'data-silogy="langkah-semester"'))
        ->and(strpos($html, 'data-silogy="langkah-semester"'))
        ->toBeLessThan(strpos($html, 'data-silogy="langkah-kelas"'))
        ->and(strpos($html, 'data-silogy="langkah-kelas"'))
        ->toBeLessThan(strpos($html, 'data-silogy="langkah-tab-laporan"'))
        ->and(strpos($html, 'data-silogy="langkah-tab-laporan"'))
        ->toBeLessThan(strpos($html, 'data-silogy="laporan-koordinator-data"'))
        ->and($html)->toContain('data-silogy-laporan-koordinator-panel')
        ->and($html)->toContain('Portofolio');
});

it('memakai garis pemisah sebelum pilih kelas dan pilih tab Laporan', function () {
    siapkanPenugasanLaporanKoordinator($this->mk, $this->prodi, $this->semester->id);
    buatKelasUntukDosen($this->mkUnit, $this->dosen, $this->prodi, $this->semester->id, 'A');

    $html = Livewire::test(LaporanKoordinator::class)->html();

    expect(substr_count($html, 'silogy-langkah-batas'))->toBeGreaterThanOrEqual(2)
        ->and(strpos($html, 'data-silogy="langkah-kelas"'))
        ->toBeLessThan(strpos($html, 'data-silogy="langkah-tab-laporan"'));

    preg_match('/data-silogy="langkah-kelas"[^>]*>/', $html, $kelasTag);
    preg_match('/data-silogy="langkah-tab-laporan"[^>]*>/', $html, $tabTag);

    expect($kelasTag[0] ?? '')->toContain('silogy-langkah-batas')
        ->and($tabTag[0] ?? '')->toContain('silogy-langkah-batas');
});
