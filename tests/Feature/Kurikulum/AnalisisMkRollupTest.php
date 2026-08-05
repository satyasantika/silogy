<?php

use App\Models\User;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kalkulasi\Models\HasilCplMk;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Kurikulum\Filament\Pages\AnalisisMkFakultas;
use App\Modules\Kurikulum\Filament\Pages\AnalisisMkProdi;
use App\Modules\Kurikulum\Filament\Pages\AnalisisMkUniversitas;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Services\AnalisisMkProdiService;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkCpmk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\Penilaian\Models\Evaluasi;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

/**
 * Kurikulum fakultas/universitas tidak pernah punya mk_units/KelasMk
 * sendiri — MK-nya diadaptasi ke mk_units milik prodi turunan (mk_id sama,
 * kurikulum_id & academic_unit_id milik prodi masing-masing). Helper ini
 * membangun satu MK pada $sourceUnit yang diadaptasi oleh DUA prodi
 * berbeda, masing-masing dengan satu mahasiswa terdaftar, supaya rollup
 * AnalisisMkProdiService::mkUnitIdsUntukKurikulum() bisa dibuktikan
 * menggabungkan keduanya.
 *
 * @return array{mahasiswaA: Mahasiswa, mahasiswaB: Mahasiswa, mkUnitA: MkUnit, mkUnitB: MkUnit}
 */
function siapkanAdaptasiDuaProdiRoster(object $context, AcademicUnit $sourceUnit): array
{
    $mk = Mk::factory()->forAcademicUnit($sourceUnit)->create(['nama' => 'MK Rollup Bersama']);

    $mkUnitA = MkUnit::factory()->forMk($mk)->forAcademicUnit($context->prodiA)->create([
        'kurikulum_id' => $context->kurikulumA->id,
        'is_active' => true,
    ]);
    $mkUnitB = MkUnit::factory()->forMk($mk)->forAcademicUnit($context->prodiB)->create([
        'kurikulum_id' => $context->kurikulumB->id,
        'is_active' => true,
    ]);

    $semester = Semester::query()->where('status_aktif', true)->firstOrFail();

    $kelasA = KelasMk::query()->create(['mk_unit_id' => $mkUnitA->id, 'semester_id' => $semester->id, 'kode_kelas' => 'A', 'dosen_pengampu_id' => $context->dosen->id]);
    $kelasB = KelasMk::query()->create(['mk_unit_id' => $mkUnitB->id, 'semester_id' => $semester->id, 'kode_kelas' => 'A', 'dosen_pengampu_id' => $context->dosen->id]);

    $mahasiswaA = Mahasiswa::factory()->create(['academic_unit_id' => $context->prodiA->id, 'nim' => '242151100101', 'nama' => 'Mahasiswa Prodi A']);
    $mahasiswaB = Mahasiswa::factory()->create(['academic_unit_id' => $context->prodiB->id, 'nim' => '242151100102', 'nama' => 'Mahasiswa Prodi B']);

    KelasMkMahasiswa::query()->create(['kelas_mk_id' => $kelasA->id, 'mahasiswa_id' => $mahasiswaA->id, 'nilai_angka' => 90, 'nilai_huruf' => 'A']);
    KelasMkMahasiswa::query()->create(['kelas_mk_id' => $kelasB->id, 'mahasiswa_id' => $mahasiswaB->id, 'nilai_angka' => 50, 'nilai_huruf' => 'D']);

    return compact('mahasiswaA', 'mahasiswaB', 'mkUnitA', 'mkUnitB');
}

/**
 * Versi lengkap siapkanAdaptasiDuaProdiRoster() yang juga membangun rantai
 * CPL → BoK → CplMk → Cpmk → Subcpmk → KomponenPenilaian → NilaiMahasiswa
 * penuh (bukan sekadar nilai_angka manual), supaya kalkulasi hasil_cpl_mk
 * sungguhan teruji lewat sinkronkanKalkulasiProdi()/hasilAnalisisPerAngkatan()
 * pada rollup lintas prodi.
 *
 * @return array{cpl: Cpl, mahasiswaA: Mahasiswa, mahasiswaB: Mahasiswa}
 */
function siapkanRollupHasilCplMk(object $context): array
{
    $semester = Semester::query()->where('status_aktif', true)->firstOrFail();

    $mk = Mk::factory()->forAcademicUnit($context->fakultas)->create(['nama' => 'MK Rollup CPL']);
    $cpl = Cpl::factory()->forKurikulum($context->kurikulumFakultas)->create(['kode' => 'CPL-ROLLUP']);
    $bok = Bok::factory()->forKurikulum($context->kurikulumFakultas)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id, 'bobot' => 100]);
    $cplMk = CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $mk->id, 'bobot' => 100]);

    $cpmk = Cpmk::factory()->forMk($mk)->create();
    $mkCpmk = MkCpmk::factory()->forCplMkAndCpmk($cplMk, $cpmk)->create();
    $subcpmk = Subcpmk::factory()->for($mkCpmk)->create(['semester_id' => $semester->id]);

    $evaluasi = Evaluasi::query()->where('kode', 'quiz')->firstOrFail();
    $komponen = KomponenPenilaian::query()->create([
        'mk_id' => $mk->id,
        'semester_id' => $semester->id,
        'evaluasi_id' => $evaluasi->id,
        'kode' => 'Asesmen01',
        'nama' => 'Kuis',
        'bobot' => 100,
    ]);
    $skp = SubcpmkKomponenPenilaian::query()->create([
        'subcpmk_id' => $subcpmk->id,
        'komponen_penilaian_id' => $komponen->id,
        'bobot' => 100,
    ]);

    $mkUnitA = MkUnit::factory()->forMk($mk)->forAcademicUnit($context->prodiA)->create(['kurikulum_id' => $context->kurikulumA->id, 'is_active' => true]);
    $mkUnitB = MkUnit::factory()->forMk($mk)->forAcademicUnit($context->prodiB)->create(['kurikulum_id' => $context->kurikulumB->id, 'is_active' => true]);

    $kelasA = KelasMk::query()->create(['mk_unit_id' => $mkUnitA->id, 'semester_id' => $semester->id, 'kode_kelas' => 'A', 'dosen_pengampu_id' => $context->dosen->id]);
    $kelasB = KelasMk::query()->create(['mk_unit_id' => $mkUnitB->id, 'semester_id' => $semester->id, 'kode_kelas' => 'A', 'dosen_pengampu_id' => $context->dosen->id]);

    $mahasiswaA = Mahasiswa::factory()->create(['academic_unit_id' => $context->prodiA->id, 'angkatan' => '2023']);
    $mahasiswaB = Mahasiswa::factory()->create(['academic_unit_id' => $context->prodiB->id, 'angkatan' => '2023']);

    $kmmA = KelasMkMahasiswa::query()->create(['kelas_mk_id' => $kelasA->id, 'mahasiswa_id' => $mahasiswaA->id]);
    $kmmB = KelasMkMahasiswa::query()->create(['kelas_mk_id' => $kelasB->id, 'mahasiswa_id' => $mahasiswaB->id]);

    NilaiMahasiswa::query()->create(['subcpmk_komponenpenilaian_id' => $skp->id, 'kelas_mk_mahasiswa_id' => $kmmA->id, 'nilai' => 90]);
    NilaiMahasiswa::query()->create(['subcpmk_komponenpenilaian_id' => $skp->id, 'kelas_mk_mahasiswa_id' => $kmmB->id, 'nilai' => 50]);

    // RecalkulasiCplJob otomatis terpicu saat NilaiMahasiswa disimpan (queue
    // 'sync' di lingkungan test) — dihapus supaya sinkronkanKalkulasiProdi()
    // (dipanggil oleh mount()) sungguh-sungguh yang mengisi ulang
    // hasil_cpl_mk, bukan efek samping job.
    HasilCplMk::query()->delete();

    return compact('cpl', 'mahasiswaA', 'mahasiswaB');
}

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SemesterSeeder::class);
    $this->seed(EvaluasiSeeder::class);

    $this->univ = AcademicUnit::query()->where('type', 'university')->firstOrFail();
    $this->fakultas = AcademicUnit::query()->where('type', 'faculty')->firstOrFail();
    $this->prodiA = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->prodiB = AcademicUnit::factory()->studyProgram($this->fakultas)->create(['nama' => 'Prodi B Rollup', 'code' => 'PRDB']);
    $this->dosen = User::query()->where('username', 'dosen')->firstOrFail();

    $this->kurikulumFakultas = Kurikulum::query()->create([
        'academic_unit_id' => $this->fakultas->id,
        'nama' => 'Kurikulum Fakultas Rollup',
        'tahun' => 2026,
        'is_active' => true,
        'target_capaian_lulusan' => 75,
    ]);

    $this->kurikulumA = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodiA->id,
        'nama' => 'Kurikulum Prodi A Rollup',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    $this->kurikulumB = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodiB->id,
        'nama' => 'Kurikulum Prodi B Rollup',
        'tahun' => 2026,
        'is_active' => true,
    ]);
});

it('canAccess mengizinkan tim kurikulum fakultas dan menolak user tanpa scope unit tsb', function () {
    $this->actingAs(User::query()->where('username', 'timkurfak')->firstOrFail());
    expect(AnalisisMkFakultas::canAccess())->toBeTrue();

    $luar = User::factory()->create();
    $luar->assignRole('Tim Kurikulum');
    $this->actingAs($luar);
    expect(AnalisisMkFakultas::canAccess())->toBeFalse();
});

it('roster analisis mk fakultas menggabungkan mahasiswa dari prodi turunan dan menampilkan kolom Prodi', function () {
    $this->actingAs(User::query()->where('username', 'timkurfak')->firstOrFail());
    $data = siapkanAdaptasiDuaProdiRoster($this, $this->fakultas);

    // MK lokal murni milik prodi A (bukan adaptasi dari kurikulum fakultas)
    // — mahasiswanya tidak boleh ikut tampil pada rollup fakultas.
    $mkLokal = Mk::factory()->forAcademicUnit($this->prodiA)->create(['nama' => 'MK Lokal Prodi A']);
    $mkUnitLokal = MkUnit::factory()->forMk($mkLokal)->forAcademicUnit($this->prodiA)->create(['kurikulum_id' => $this->kurikulumA->id, 'is_active' => true]);
    $semester = Semester::query()->where('status_aktif', true)->firstOrFail();
    $kelasLokal = KelasMk::query()->create(['mk_unit_id' => $mkUnitLokal->id, 'semester_id' => $semester->id, 'kode_kelas' => 'A', 'dosen_pengampu_id' => $this->dosen->id]);
    $mahasiswaLokal = Mahasiswa::factory()->create(['academic_unit_id' => $this->prodiA->id, 'nim' => '242151100199', 'nama' => 'Mahasiswa MK Lokal']);
    KelasMkMahasiswa::query()->create(['kelas_mk_id' => $kelasLokal->id, 'mahasiswa_id' => $mahasiswaLokal->id, 'nilai_angka' => 80, 'nilai_huruf' => 'A']);

    KurikulumTerpilih::set($this->kurikulumFakultas->id);

    Livewire::test(AnalisisMkFakultas::class)
        ->assertSee($data['mahasiswaA']->nim, escape: false)
        ->assertSee($data['mahasiswaB']->nim, escape: false)
        ->assertSee($this->prodiA->nama, escape: false)
        ->assertSee($this->prodiB->nama, escape: false)
        ->assertDontSee($mahasiswaLokal->nim, escape: false);
});

it('rollup mk_unit_ids hanya mengambil prodi turunan, mengabaikan unit non-prodi dan mk tidak aktif', function () {
    $data = siapkanAdaptasiDuaProdiRoster($this, $this->fakultas);

    // mk_unit tidak aktif pada prodi lain dengan mk_id sama tidak ikut rollup.
    $prodiC = AcademicUnit::factory()->studyProgram($this->fakultas)->create(['nama' => 'Prodi C Nonaktif', 'code' => 'PRDC']);
    $kurikulumC = Kurikulum::query()->create([
        'academic_unit_id' => $prodiC->id,
        'nama' => 'Kurikulum Prodi C',
        'tahun' => 2026,
        'is_active' => true,
    ]);
    MkUnit::factory()->forMk(Mk::query()->where('nama', 'MK Rollup Bersama')->firstOrFail())
        ->forAcademicUnit($prodiC)
        ->create(['kurikulum_id' => $kurikulumC->id, 'is_active' => false]);

    $mkUnitIds = app(AnalisisMkProdiService::class)->mkUnitIdsUntukKurikulum($this->kurikulumFakultas->fresh(['academicUnit']));

    expect($mkUnitIds->all())->toEqualCanonicalizing([$data['mkUnitA']->id, $data['mkUnitB']->id]);
});

it('rollup mengagregasi ketercapaian cpl lintas prodi turunan tanpa bocor ke kurikulum lain', function () {
    $this->actingAs(User::query()->where('username', 'timkurfak')->firstOrFail());
    $data = siapkanRollupHasilCplMk($this);

    KurikulumTerpilih::set($this->kurikulumFakultas->id);

    $test = Livewire::test(AnalisisMkFakultas::class);
    $hasilAnalisis = $test->get('hasilAnalisis');

    expect($hasilAnalisis['pemetaan'])->toHaveCount(1);

    $cplGroup = $hasilAnalisis['pemetaan'][0];
    expect($cplGroup['cpl_kode'])->toBe('CPL-ROLLUP')
        ->and($cplGroup['ketercapaian']['jumlah_mahasiswa'])->toBe(2)
        ->and($cplGroup['ketercapaian']['rata_rata'])->toBe(70.0);

    $mkRow = $cplGroup['mk_rows'][0];
    expect($mkRow['rata_rata_keseluruhan'])->toBe(70.0);
});

it('analisis mk universitas menggabungkan roster prodi yang mengadaptasi MK level universitas', function () {
    $dosenTimkur = User::query()->where('username', 'dosentimkur')->firstOrFail();
    $this->actingAs($dosenTimkur);

    $kurikulumUniv = Kurikulum::query()->create([
        'academic_unit_id' => $this->univ->id,
        'nama' => 'Kurikulum Universitas Rollup',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    $mk = Mk::factory()->forAcademicUnit($this->univ)->create(['nama' => 'MK Universal Wajib']);
    $mkUnit = MkUnit::factory()->forMk($mk)->forAcademicUnit($this->prodiA)->create([
        'kurikulum_id' => $this->kurikulumA->id,
        'is_active' => true,
    ]);

    $semester = Semester::query()->where('status_aktif', true)->firstOrFail();
    $kelas = KelasMk::query()->create(['mk_unit_id' => $mkUnit->id, 'semester_id' => $semester->id, 'kode_kelas' => 'A', 'dosen_pengampu_id' => $this->dosen->id]);
    $mahasiswa = Mahasiswa::factory()->create(['academic_unit_id' => $this->prodiA->id, 'nim' => '242151100301', 'nama' => 'Mahasiswa Univ Rollup']);
    KelasMkMahasiswa::query()->create(['kelas_mk_id' => $kelas->id, 'mahasiswa_id' => $mahasiswa->id, 'nilai_angka' => 88, 'nilai_huruf' => 'A']);

    KurikulumTerpilih::set($kurikulumUniv->id);

    expect(AnalisisMkUniversitas::canAccess())->toBeTrue();

    Livewire::test(AnalisisMkUniversitas::class)
        ->assertSee($mahasiswa->nim, escape: false)
        ->assertSee($this->prodiA->nama, escape: false);
});

it('tombol lihat kode per prodi hanya muncul di halaman fakultas/universitas dan modal menampilkan seluruh prodi pengadaptasi', function () {
    $this->actingAs(User::query()->where('username', 'timkurfak')->firstOrFail());
    siapkanRollupHasilCplMk($this);

    $mkUnitA = MkUnit::query()->where('kurikulum_id', $this->kurikulumA->id)->firstOrFail();
    $mkUnitB = MkUnit::query()->where('kurikulum_id', $this->kurikulumB->id)->firstOrFail();

    KurikulumTerpilih::set($this->kurikulumFakultas->id);

    Livewire::test(AnalisisMkFakultas::class)
        ->assertSee('Lihat per prodi', escape: false)
        ->mountAction('kodePerProdi', arguments: ['mkId' => $mkUnitA->mk_id, 'nama' => 'MK Rollup CPL'])
        ->assertMountedActionModalSee($this->prodiA->nama, escape: false)
        ->assertMountedActionModalSee($this->prodiB->nama, escape: false)
        ->assertMountedActionModalSee($mkUnitA->kode, escape: false)
        ->assertMountedActionModalSee($mkUnitB->kode, escape: false);

    KurikulumTerpilih::set($this->kurikulumA->id);
    $this->actingAs(User::query()->where('username', 'timkur')->firstOrFail());

    Livewire::test(AnalisisMkProdi::class)
        ->assertDontSee('Lihat per prodi', escape: false);
});

it('kolom Tab 1 hanya menampilkan nama MK tanpa kode untuk fakultas, tapi tetap nama (kode) untuk prodi', function () {
    $this->actingAs(User::query()->where('username', 'timkurfak')->firstOrFail());
    siapkanRollupHasilCplMk($this);

    KurikulumTerpilih::set($this->kurikulumFakultas->id);

    Livewire::test(AnalisisMkFakultas::class)
        ->assertSee('Mata Kuliah', escape: false)
        ->assertDontSee('Nama Mata Kuliah (Kode)', escape: false)
        ->assertSee('MK Rollup CPL', escape: false);

    // Prodi A punya CPL/MK sendiri (bukan hasil adaptasi) untuk membuktikan
    // Tab 1 prodi tetap memakai format lama "Nama (Kode)".
    $cplProdi = Cpl::factory()->forKurikulum($this->kurikulumA)->create(['kode' => 'CPL-PRODI-A']);
    $bokProdi = Bok::factory()->forKurikulum($this->kurikulumA)->create();
    $cplBokProdi = CplBok::query()->create(['cpl_id' => $cplProdi->id, 'bok_id' => $bokProdi->id, 'bobot' => 100]);
    $mkProdi = Mk::factory()->forKurikulum($this->kurikulumA)->create(['nama' => 'MK Milik Prodi A']);
    MkUnit::factory()->forMk($mkProdi)->forKurikulum($this->kurikulumA)->create(['kode' => 'PRA101', 'is_active' => true]);
    CplMk::query()->create(['cpl_bok_id' => $cplBokProdi->id, 'mk_id' => $mkProdi->id, 'bobot' => 100]);

    KurikulumTerpilih::set($this->kurikulumA->id);
    $this->actingAs(User::query()->where('username', 'timkur')->firstOrFail());

    Livewire::test(AnalisisMkProdi::class)
        ->assertSee('Nama Mata Kuliah (Kode)', escape: false)
        ->assertSee('MK Milik Prodi A (PRA101)', escape: false);
});

it('kolom Aspek Mata Kuliah menampilkan badge SKS untuk fakultas dan prodi (prodi tetap nama + kode)', function () {
    $this->actingAs(User::query()->where('username', 'timkurfak')->firstOrFail());
    siapkanRollupHasilCplMk($this);

    $mk = Mk::query()->where('nama', 'MK Rollup CPL')->firstOrFail();
    $mkUnitA = MkUnit::query()->where('kurikulum_id', $this->kurikulumA->id)->firstOrFail();

    KurikulumTerpilih::set($this->kurikulumFakultas->id);

    Livewire::test(AnalisisMkFakultas::class)
        ->assertSee($mk->total_sks.' SKS', escape: false)
        ->assertDontSee($mkUnitA->kode, escape: false);

    // Prodi A: format "Nama (Kode)" + badge SKS.
    $cplProdi = Cpl::factory()->forKurikulum($this->kurikulumA)->create(['kode' => 'CPL-PRODI-SKS']);
    $bokProdi = Bok::factory()->forKurikulum($this->kurikulumA)->create();
    $cplBokProdi = CplBok::query()->create(['cpl_id' => $cplProdi->id, 'bok_id' => $bokProdi->id, 'bobot' => 100]);
    $mkProdi = Mk::factory()->forKurikulum($this->kurikulumA)->create([
        'nama' => 'MK Prodi Untuk SKS',
        'sks_teori' => 3,
        'sks_praktik' => 0,
        'sks_lapangan' => 0,
        'sks' => 3,
    ]);
    MkUnit::factory()->forMk($mkProdi)->forKurikulum($this->kurikulumA)->create(['kode' => 'PRSKS01', 'is_active' => true]);
    CplMk::query()->create(['cpl_bok_id' => $cplBokProdi->id, 'mk_id' => $mkProdi->id, 'bobot' => 100]);

    KurikulumTerpilih::set($this->kurikulumA->id);
    $this->actingAs(User::query()->where('username', 'timkur')->firstOrFail());

    Livewire::test(AnalisisMkProdi::class)
        ->assertSee('MK Prodi Untuk SKS (PRSKS01)', escape: false)
        ->assertSee('3 SKS', escape: false);
});

it('tab hasil analisis dan grafik CPL tetap menampilkan struktur CPL/MK fakultas walau belum ada prodi yang menawarkan', function () {
    $this->actingAs(User::query()->where('username', 'timkurfak')->firstOrFail());

    // CPL->BoK->CplMk->MK lengkap pada kurikulum fakultas, TAPI TANPA satu
    // pun MkUnit — belum ada prodi yang mengadaptasi/menawarkan MK ini.
    $mk = Mk::factory()->forKurikulum($this->kurikulumFakultas)->create(['nama' => 'MK Belum Ditawarkan']);
    $cpl = Cpl::factory()->forKurikulum($this->kurikulumFakultas)->create(['kode' => 'CPL-BELUM-TAWAR']);
    $bok = Bok::factory()->forKurikulum($this->kurikulumFakultas)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id, 'bobot' => 100]);
    CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $mk->id, 'bobot' => 100]);

    KurikulumTerpilih::set($this->kurikulumFakultas->id);

    $mkUnitIds = app(AnalisisMkProdiService::class)->mkUnitIdsUntukKurikulum($this->kurikulumFakultas->fresh(['academicUnit']));
    expect($mkUnitIds->isEmpty())->toBeTrue();

    $test = Livewire::test(AnalisisMkFakultas::class);

    expect($test->get('pemetaanCplMk'))->toHaveCount(1);

    $hasilAnalisis = $test->get('hasilAnalisis');
    expect($hasilAnalisis['pemetaan'])->toHaveCount(1)
        ->and($hasilAnalisis['pemetaan'][0]['cpl_kode'])->toBe('CPL-BELUM-TAWAR')
        ->and($hasilAnalisis['pemetaan'][0]['ketercapaian'])->toBeNull();

    $test
        ->assertSee('MK Belum Ditawarkan', escape: false)
        ->assertSee('Menunggu selesai penilaian', escape: false)
        ->assertDontSee('Belum ada CPL yang dibebankan', escape: false);
});

it('menu analisis mk cuma tampil untuk halaman yang levelnya cocok dengan kurikulum yang dikerjakan', function () {
    $dosenTimkur = User::query()->where('username', 'dosentimkur')->firstOrFail();
    $this->actingAs($dosenTimkur);

    KurikulumTerpilih::set($this->kurikulumA->id);
    expect(AnalisisMkProdi::shouldRegisterNavigation())->toBeTrue()
        ->and(AnalisisMkFakultas::shouldRegisterNavigation())->toBeFalse()
        ->and(AnalisisMkUniversitas::shouldRegisterNavigation())->toBeFalse();

    KurikulumTerpilih::set($this->kurikulumFakultas->id);
    expect(AnalisisMkProdi::shouldRegisterNavigation())->toBeFalse()
        ->and(AnalisisMkFakultas::shouldRegisterNavigation())->toBeTrue()
        ->and(AnalisisMkUniversitas::shouldRegisterNavigation())->toBeFalse();
});
