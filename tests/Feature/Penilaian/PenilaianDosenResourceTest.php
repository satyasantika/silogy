<?php

use App\Models\User;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Institusi\Filament\Resources\AcademicUnitResource;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Kelas\Policies\KelasMkPolicy;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkCpmk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\Penilaian\Filament\Resources\PenilaianDosenResource;
use App\Modules\Penilaian\Filament\Resources\PenilaianDosenResource\Pages\ListPenilaianDosens;
use App\Modules\Penilaian\Models\Evaluasi;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;
use App\Modules\Penilaian\Services\PenilaianDosenService;
use App\Modules\Penilaian\Support\PenilaianSemesterTerpilih;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\MahasiswaSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Bangun kelas MK milik dosen lengkap dengan mahasiswa; opsional beri nilai
 * lewat sub-CPMK/komponen bersama agar rata-rata dapat dihitung.
 *
 * @return array{kelas: KelasMk, kmms: Collection<int, KelasMkMahasiswa>, skp: SubcpmkKomponenPenilaian}
 */
function buatKelasPenilaianDosen(
    User $dosen,
    Mk $mk,
    string $kodeKelas,
    string $semesterId,
    int $jumlahMahasiswa = 1,
): array {
    $prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $mkUnit = MkUnit::query()->firstOrCreate(
        ['mk_id' => $mk->id, 'academic_unit_id' => $prodi->id],
        MkUnit::factory()->make()->toArray(),
    );

    $kelas = KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $semesterId,
        'kode_kelas' => $kodeKelas,
        'dosen_pengampu_id' => $dosen->id,
    ]);

    $kmms = collect();

    foreach (range(1, $jumlahMahasiswa) as $i) {
        $mahasiswa = Mahasiswa::factory()->create(['academic_unit_id' => $prodi->id]);
        $kmms->push(KelasMkMahasiswa::query()->create([
            'kelas_mk_id' => $kelas->id,
            'mahasiswa_id' => $mahasiswa->id,
        ]));
    }

    $cpmk = Cpmk::factory()->forMk($mk)->create();
    $cpl = Cpl::factory()->forAcademicUnit($prodi)->create();
    $bok = Bok::factory()->forAcademicUnit($prodi)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id, 'bobot' => 100]);
    $cplMk = CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $mk->id, 'bobot' => 100]);
    $mkCpmk = MkCpmk::factory()->forCplMkAndCpmk($cplMk, $cpmk)->create();
    $subcpmk = Subcpmk::factory()->for($mkCpmk)->create();

    $evaluasi = Evaluasi::query()->where('kode', 'uts')->firstOrFail();
    $komponen = KomponenPenilaian::query()->updateOrCreate(
        ['mk_id' => $mk->id, 'semester_id' => $semesterId, 'kode' => 'UTS'],
        ['evaluasi_id' => $evaluasi->id, 'nama' => 'UTS', 'bobot' => 100],
    );
    $skp = SubcpmkKomponenPenilaian::query()->create([
        'subcpmk_id' => $subcpmk->id,
        'komponen_penilaian_id' => $komponen->id,
        'bobot' => 100,
    ]);

    return compact('kelas', 'kmms', 'skp');
}

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SemesterSeeder::class);
    $this->seed(EvaluasiSeeder::class);
    $this->seed(MahasiswaSeeder::class);

    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->dosen = User::where('username', 'dosen')->firstOrFail();
    $this->korma = User::where('username', 'korma')->firstOrFail();
    $this->semesterAktif = Semester::query()->where('status_aktif', true)->firstOrFail();
});

it('hanya dosen pengampu yang dapat mengakses halaman penilaian', function () {
    $this->actingAs($this->dosen);
    expect(PenilaianDosenResource::canAccess())->toBeTrue();

    $this->actingAs($this->korma);
    expect(PenilaianDosenResource::canAccess())->toBeFalse();
});

it('toolbar penilaian: semester kiri tanpa indikator filter dan tanpa urutkan menurut', function () {
    $mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id, 'nama' => 'Toolbar Penilaian']);
    buatKelasPenilaianDosen($this->dosen, $mk, 'A', $this->semesterAktif->id, 1);

    $this->actingAs($this->dosen);
    PenilaianSemesterTerpilih::set($this->semesterAktif->id);

    Livewire::test(ListPenilaianDosens::class)->loadTable()
        ->assertSee('Semester', escape: false)
        ->assertSee('Tarik data', escape: false)
        ->assertDontSee('Filter aktif', escape: false)
        ->assertDontSee('Urutkan menurut', escape: false)
        ->assertDontSee('Semester terpilih:', escape: false)
        ->assertSeeHtml('silogy-mk-semester-toolbar')
        ->assertSeeHtml('silogy-penilaian-dosen')
        ->assertTableActionExists('importSintesysDosenPengampu');
});

it('menampilkan card prodi berisi tabel kode, nama mk, kelas, mhs, status', function () {
    $mk = Mk::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Struktur Data',
        'sks_teori' => 2,
        'sks_praktik' => 1,
        'sks_lapangan' => 0,
        'sks' => 3,
    ]);

    $kelasA = buatKelasPenilaianDosen($this->dosen, $mk, 'A', $this->semesterAktif->id, 2);
    $kodeMk = $kelasA['kelas']->mkUnit->kode;

    NilaiMahasiswa::query()->create([
        'subcpmk_komponenpenilaian_id' => $kelasA['skp']->id,
        'kelas_mk_mahasiswa_id' => $kelasA['kmms'][0]->id,
        'nilai' => 80,
    ]);
    NilaiMahasiswa::query()->create([
        'subcpmk_komponenpenilaian_id' => $kelasA['skp']->id,
        'kelas_mk_mahasiswa_id' => $kelasA['kmms'][1]->id,
        'nilai' => 90,
    ]);

    buatKelasPenilaianDosen($this->dosen, $mk, 'B', $this->semesterAktif->id, 1);

    $this->actingAs($this->dosen);
    PenilaianSemesterTerpilih::set($this->semesterAktif->id);

    [, $namaProdi] = AcademicUnitResource::jenisDanNamaUntukCard($this->prodi);

    $html = Livewire::test(ListPenilaianDosens::class)->loadTable()->html();

    expect($html)
        ->toContain('silogy-penilaian-kpi__bento')
        ->toContain('silogy-penilaian-kpi__donut')
        ->toContain('Progress penilaian')
        ->toContain('Program Studi · '.$namaProdi)
        ->toContain('silogy-penilaian-prodi__table')
        ->toContain('silogy-penilaian-prodi__nama')
        ->toContain('Struktur Data')
        ->toContain($kodeMk)
        ->toContain('Belum dinilai')
        ->toContain('Nilai')
        ->toContain('Edit nilai')
        ->toContain('silogy-penilaian-prodi__status--pending')
        ->toContain('silogy-penilaian-prodi__status--ok')
        ->toContain('silogy-penilaian-prodi__laporan')
        ->toContain('85')
        ->not->toContain('3 SKS');

    expect(
        str_contains($html, 'tab=laporan')
        || str_contains($html, 'tab&#61;laporan')
    )->toBeTrue();
});

it('menampilkan status menunggu asesmen bila koordinator belum siap, tanpa badge Belum dinilai', function () {
    $mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id, 'nama' => 'MK Menunggu Asesmen']);
    $mkUnit = MkUnit::query()->firstOrCreate(
        ['mk_id' => $mk->id, 'academic_unit_id' => $this->prodi->id],
        MkUnit::factory()->make()->toArray(),
    );
    KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $this->semesterAktif->id,
        'kode_kelas' => 'Z',
        'dosen_pengampu_id' => $this->dosen->id,
    ]);

    $this->actingAs($this->dosen);
    PenilaianSemesterTerpilih::set($this->semesterAktif->id);

    $html = Livewire::test(ListPenilaianDosens::class)->loadTable()->html();

    expect($html)
        ->toContain('MK Menunggu Asesmen')
        ->toContain('Menunggu persiapan asesmen MK oleh koordinator')
        ->toContain('silogy-penilaian-prodi__status--wait')
        ->not->toContain('Belum dinilai');
});

it('service barisKelasUntukUnit menyertakan tautan input dan laporan tanpa tombol hapus bila sudah dinilai', function () {
    $mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id, 'nama' => 'Basis Data']);
    $kelas = buatKelasPenilaianDosen($this->dosen, $mk, 'A', $this->semesterAktif->id, 1);

    NilaiMahasiswa::query()->create([
        'subcpmk_komponenpenilaian_id' => $kelas['skp']->id,
        'kelas_mk_mahasiswa_id' => $kelas['kmms'][0]->id,
        'nilai' => 88,
    ]);

    $baris = PenilaianDosenService::barisKelasUntukUnit(
        $this->prodi,
        $this->dosen,
        $this->semesterAktif->id,
    )->first();

    expect($baris)->not->toBeNull()
        ->and($baris['sudah_dinilai'])->toBeTrue()
        ->and($baris['asesmen_siap'])->toBeTrue()
        ->and($baris['url_input'])->toContain('kelas_mk_id='.$kelas['kelas']->id)
        ->and($baris['url_laporan'])->toContain('tab=laporan');

    $html = PenilaianDosenService::tabelKelasUnitHtml(
        $this->prodi,
        $this->dosen,
        $this->semesterAktif->id,
    )->toHtml();

    expect($html)
        ->not->toContain('silogy-penilaian-prodi__hapus')
        ->not->toContain("mountAction('hapusKelas'")
        ->toContain('Edit nilai')
        ->toContain($kelas['kelas']->id);
});

it('tombol hapus kelas muncul dengan mountAction JSON.parse bila kelas belum dinilai', function () {
    $mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id, 'nama' => 'MK Belum Dinilai Hapus']);
    $kelas = buatKelasPenilaianDosen($this->dosen, $mk, 'A', $this->semesterAktif->id, 1);
    $kelasId = $kelas['kelas']->id;

    $html = PenilaianDosenService::tabelKelasUnitHtml(
        $this->prodi,
        $this->dosen,
        $this->semesterAktif->id,
    )->toHtml();

    expect($html)
        ->toContain('silogy-penilaian-prodi__hapus')
        ->toContain("mountAction('hapusKelas', JSON.parse('{\\u0022kelasMkId\\u0022:\\u0022".$kelasId."\\u0022}'))")
        ->toContain('stroke="currentColor"')
        ->toContain('aria-label="Hapus kelas A"');
});

it('dosen pengampu dapat menghapus kelas belum dinilai dari halaman penilaian beserta kontrak mahasiswa', function () {
    $mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id, 'nama' => 'MK Hapus Kelas']);
    $kelas = buatKelasPenilaianDosen($this->dosen, $mk, 'A', $this->semesterAktif->id, 2);
    $kmmIds = $kelas['kmms']->pluck('id')->all();

    $this->actingAs($this->dosen);
    PenilaianSemesterTerpilih::set($this->semesterAktif->id);

    expect(app(KelasMkPolicy::class)->delete($this->dosen, $kelas['kelas']))->toBeTrue();

    Livewire::test(ListPenilaianDosens::class)
        ->loadTable()
        ->assertSee('silogy-penilaian-prodi__hapus', escape: false)
        ->assertSee("mountAction('hapusKelas', JSON.parse(", escape: false)
        ->callAction('hapusKelas', arguments: ['kelasMkId' => $kelas['kelas']->id]);

    expect(KelasMk::query()->find($kelas['kelas']->id))->toBeNull()
        ->and(KelasMkMahasiswa::query()->whereIn('id', $kmmIds)->count())->toBe(0)
        ->and(KomponenPenilaian::query()
            ->where('mk_id', $mk->id)
            ->where('semester_id', $this->semesterAktif->id)
            ->exists())->toBeTrue();
});

it('action hapusKelas menolak kelas yang sudah dinilai', function () {
    $mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id, 'nama' => 'MK Sudah Dinilai']);
    $kelas = buatKelasPenilaianDosen($this->dosen, $mk, 'A', $this->semesterAktif->id, 1);

    NilaiMahasiswa::query()->create([
        'subcpmk_komponenpenilaian_id' => $kelas['skp']->id,
        'kelas_mk_mahasiswa_id' => $kelas['kmms'][0]->id,
        'nilai' => 77,
    ]);

    $this->actingAs($this->dosen);
    PenilaianSemesterTerpilih::set($this->semesterAktif->id);

    Livewire::test(ListPenilaianDosens::class)
        ->loadTable()
        ->assertDontSee('silogy-penilaian-prodi__hapus', escape: false)
        ->callAction('hapusKelas', arguments: ['kelasMkId' => $kelas['kelas']->id]);

    expect(KelasMk::query()->find($kelas['kelas']->id))->not->toBeNull();
});

it('dosen lain tidak dapat menghapus kelas yang bukan miliknya', function () {
    $dosenLain = User::factory()->create([
        'username' => 'dosenlainhapuskelas',
        'email' => 'dosenlainhapuskelas@silogy.test',
    ]);
    $dosenLain->assignRole('Dosen Pengampu');

    $mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id]);
    $kelas = buatKelasPenilaianDosen($this->dosen, $mk, 'A', $this->semesterAktif->id, 1);

    expect(app(KelasMkPolicy::class)->delete($dosenLain, $kelas['kelas']))->toBeFalse();
});

it('service rekapKpiPengampu menghitung prodi mk kelas dan progress', function () {
    $mkSiap = Mk::factory()->create(['academic_unit_id' => $this->prodi->id, 'nama' => 'MK KPI Siap']);
    $kelasSiap = buatKelasPenilaianDosen($this->dosen, $mkSiap, 'A', $this->semesterAktif->id, 1);
    NilaiMahasiswa::query()->create([
        'subcpmk_komponenpenilaian_id' => $kelasSiap['skp']->id,
        'kelas_mk_mahasiswa_id' => $kelasSiap['kmms'][0]->id,
        'nilai' => 90,
    ]);

    $mkTunggu = Mk::factory()->create(['academic_unit_id' => $this->prodi->id, 'nama' => 'MK KPI Tunggu']);
    $mkUnitTunggu = MkUnit::query()->firstOrCreate(
        ['mk_id' => $mkTunggu->id, 'academic_unit_id' => $this->prodi->id],
        MkUnit::factory()->make()->toArray(),
    );
    KelasMk::query()->create([
        'mk_unit_id' => $mkUnitTunggu->id,
        'semester_id' => $this->semesterAktif->id,
        'kode_kelas' => 'B',
        'dosen_pengampu_id' => $this->dosen->id,
    ]);

    $kpi = PenilaianDosenService::rekapKpiPengampu($this->dosen, $this->semesterAktif->id);

    expect($kpi['jumlah_prodi'])->toBe(1)
        ->and($kpi['jumlah_mk'])->toBe(2)
        ->and($kpi['jumlah_kelas'])->toBe(2)
        ->and($kpi['kelas_siap'])->toBe(1)
        ->and($kpi['kelas_dinilai'])->toBe(1)
        ->and($kpi['kelas_menunggu_asesmen'])->toBe(1)
        ->and($kpi['mk_menunggu'])->toBe(1)
        ->and($kpi['progress_persen'])->toBe(100);
});

it('mengelompokkan kelas lintas mk dalam satu card prodi tanpa pagination', function () {
    $mkZ = Mk::factory()->create(['academic_unit_id' => $this->prodi->id, 'nama' => 'Zoologi Terapan']);
    $mkA = Mk::factory()->create(['academic_unit_id' => $this->prodi->id, 'nama' => 'Algoritma Dasar']);
    buatKelasPenilaianDosen($this->dosen, $mkZ, 'A', $this->semesterAktif->id, 1);
    buatKelasPenilaianDosen($this->dosen, $mkA, 'A', $this->semesterAktif->id, 1);

    $this->actingAs($this->dosen);
    PenilaianSemesterTerpilih::set($this->semesterAktif->id);

    [, $namaProdi] = AcademicUnitResource::jenisDanNamaUntukCard($this->prodi);

    $html = Livewire::test(ListPenilaianDosens::class)->loadTable()->html();
    $posJudul = strpos($html, 'Program Studi · '.$namaProdi);
    $posA = strpos($html, 'Algoritma Dasar');
    $posZ = strpos($html, 'Zoologi Terapan');

    expect($posJudul)->not->toBeFalse()
        ->and($posA)->not->toBeFalse()
        ->and($posZ)->not->toBeFalse()
        ->and($posJudul)->toBeLessThan($posA)
        ->and($posJudul)->toBeLessThan($posZ)
        ->and(substr_count($html, 'silogy-penilaian-prodi__table-wrap'))->toBe(1)
        ->and($html)->not->toContain('fi-pagination');
});

it('menyembunyikan mk pada semester lain dan menampilkannya saat filter diganti', function () {
    $semesterLain = Semester::query()->where('id', '!=', $this->semesterAktif->id)->firstOrFail();

    $mkAktif = Mk::factory()->create(['academic_unit_id' => $this->prodi->id, 'nama' => 'Aljabar Linear']);
    buatKelasPenilaianDosen($this->dosen, $mkAktif, 'A', $this->semesterAktif->id, 1);

    $mkLain = Mk::factory()->create(['academic_unit_id' => $this->prodi->id, 'nama' => 'Kalkulus Lanjut']);
    buatKelasPenilaianDosen($this->dosen, $mkLain, 'A', $semesterLain->id, 1);

    $this->actingAs($this->dosen);
    PenilaianSemesterTerpilih::set($this->semesterAktif->id);

    Livewire::test(ListPenilaianDosens::class)
        ->assertSee('Aljabar Linear', escape: false)
        ->assertDontSee('Kalkulus Lanjut', escape: false);

    PenilaianSemesterTerpilih::set($semesterLain->id);

    Livewire::test(ListPenilaianDosens::class)
        ->assertSee('Kalkulus Lanjut', escape: false)
        ->assertDontSee('Aljabar Linear', escape: false);
});

it('tidak menampilkan mk milik dosen lain', function () {
    $dosenLain = User::factory()->create([
        'username' => 'dosenlainpenilaian',
        'email' => 'dosenlainpenilaian@silogy.test',
        'full_name' => 'Dosen Lain Penilaian',
    ]);
    $dosenLain->assignRole('Dosen Pengampu');

    $mkSaya = Mk::factory()->create(['academic_unit_id' => $this->prodi->id, 'nama' => 'MK Saya']);
    buatKelasPenilaianDosen($this->dosen, $mkSaya, 'A', $this->semesterAktif->id, 1);

    $mkOrang = Mk::factory()->create(['academic_unit_id' => $this->prodi->id, 'nama' => 'MK Orang Lain']);
    buatKelasPenilaianDosen($dosenLain, $mkOrang, 'A', $this->semesterAktif->id, 1);

    $this->actingAs($this->dosen);
    PenilaianSemesterTerpilih::set($this->semesterAktif->id);

    Livewire::test(ListPenilaianDosens::class)
        ->assertSee('MK Saya', escape: false)
        ->assertDontSee('MK Orang Lain', escape: false);
});

it('service ringkasanKelas menghitung rata-rata dan status belum dinilai', function () {
    $mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id]);
    $kelas = buatKelasPenilaianDosen($this->dosen, $mk, 'C', $this->semesterAktif->id, 1);

    $sebelum = PenilaianDosenService::ringkasanKelas($kelas['kelas']);
    expect($sebelum['sudah_dinilai'])->toBeFalse()
        ->and($sebelum['rata_rata'])->toBeNull()
        ->and($sebelum['jumlah_mahasiswa'])->toBe(1);

    NilaiMahasiswa::query()->create([
        'subcpmk_komponenpenilaian_id' => $kelas['skp']->id,
        'kelas_mk_mahasiswa_id' => $kelas['kmms'][0]->id,
        'nilai' => 77.5,
    ]);

    $sesudah = PenilaianDosenService::ringkasanKelas($kelas['kelas']->fresh());
    expect($sesudah['sudah_dinilai'])->toBeTrue()
        ->and($sesudah['rata_rata'])->toBe(77.5);
});
