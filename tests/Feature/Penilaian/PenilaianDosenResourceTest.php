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

it('menampilkan kode penawaran setelah nama lalu sks, serta badge kelas ringkas', function () {
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

    Livewire::test(ListPenilaianDosens::class)->loadTable()
        ->assertSee('Struktur Data', escape: false)
        ->assertSee($kodeMk, escape: false)
        ->assertSee('3 SKS', escape: false)
        ->assertSeeHtml('silogy-penilaian-card__kode')
        ->assertSeeHtml('silogy-penilaian-card__sks')
        ->assertSee('2 mhs · rata-rata 85', escape: false)
        ->assertSee('1 mhs · Belum dinilai', escape: false)
        ->assertDontSee('Kelas A ·', escape: false);
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
