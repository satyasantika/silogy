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
use App\Modules\Penilaian\Filament\Pages\InputNilai;
use App\Modules\Penilaian\Filament\Resources\PenilaianDosenResource;
use App\Modules\Penilaian\Models\Evaluasi;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;
use App\Modules\Penilaian\Support\PenilaianMkTerpilih;
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
 * Bangun kelas untuk MK yang sama (mk_unit dipakai bersama) lengkap dengan
 * mahasiswa, komponen penilaian, dan pemetaan Sub-CPMK ala koordinator.
 *
 * @return array{kelas: KelasMk, kmms: Collection<int, KelasMkMahasiswa>, skp: SubcpmkKomponenPenilaian}
 */
function buatKelasUntukMkInputNilai(
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
    $subcpmk = Subcpmk::factory()->for($mkCpmk)->create(['kode' => 'SUB-'.$kodeKelas]);

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
    $this->semesterAktif = Semester::query()->where('status_aktif', true)->firstOrFail();
});

it('menampilkan mk terpilih, kartu kelas, dan ringkasan seluruh kelas', function () {
    $mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id, 'nama' => 'Struktur Data']);

    $kelasA = buatKelasUntukMkInputNilai($this->dosen, $mk, 'A', $this->semesterAktif->id, 2);
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

    buatKelasUntukMkInputNilai($this->dosen, $mk, 'B', $this->semesterAktif->id, 1);

    $this->actingAs($this->dosen);

    Livewire::withQueryParams(['kelas_mk_id' => $kelasA['kelas']->id])
        ->test(InputNilai::class)
        ->assertSet('mkId', $mk->id)
        ->assertSet('kelasMkId', $kelasA['kelas']->id)
        ->assertSee('Struktur Data', escape: false)
        ->assertSee('Kelas A', escape: false)
        ->assertSee('Kelas B', escape: false)
        ->assertSee('rata-rata 85', escape: false)
        ->assertSee('Belum dinilai', escape: false)
        ->assertSee('Seluruh kelas pada MK ini', escape: false)
        ->assertSee('3 mahasiswa', escape: false)
        ->assertSee('UTS', escape: false)
        ->assertSee('SUB-A', escape: false)
        ->assertSee($this->semesterAktif->nama, escape: false)
        ->assertSee('Pilih mata kuliah lain', escape: false)
        ->assertSee(PenilaianDosenResource::getUrl('index'), escape: false);
});

it('kartu kelas berfungsi sebagai filter untuk card penilaian', function () {
    $mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id]);

    $kelasA = buatKelasUntukMkInputNilai($this->dosen, $mk, 'A', $this->semesterAktif->id, 1);
    $kelasB = buatKelasUntukMkInputNilai($this->dosen, $mk, 'B', $this->semesterAktif->id, 2);

    $this->actingAs($this->dosen);

    Livewire::withQueryParams(['kelas_mk_id' => $kelasA['kelas']->id])
        ->test(InputNilai::class)
        ->assertSet('kelasMkId', $kelasA['kelas']->id)
        ->assertCount('rows', 1)
        ->call('pilihKelas', $kelasB['kelas']->id)
        ->assertSet('kelasMkId', $kelasB['kelas']->id)
        ->assertCount('rows', 2);
});

it('menolak pilihKelas untuk kelas milik dosen lain', function () {
    $dosenLain = User::factory()->create([
        'username' => 'dosenlaininputnilai',
        'email' => 'dosenlaininputnilai@silogy.test',
        'full_name' => 'Dosen Lain Input Nilai',
    ]);
    $dosenLain->assignRole('Dosen Pengampu');

    $mkSaya = Mk::factory()->create(['academic_unit_id' => $this->prodi->id]);
    $kelasSaya = buatKelasUntukMkInputNilai($this->dosen, $mkSaya, 'A', $this->semesterAktif->id, 1);

    $mkOrang = Mk::factory()->create(['academic_unit_id' => $this->prodi->id]);
    $kelasOrang = buatKelasUntukMkInputNilai($dosenLain, $mkOrang, 'A', $this->semesterAktif->id, 1);

    $this->actingAs($this->dosen);

    Livewire::withQueryParams(['kelas_mk_id' => $kelasSaya['kelas']->id])
        ->test(InputNilai::class)
        ->assertSet('kelasMkId', $kelasSaya['kelas']->id)
        ->call('pilihKelas', $kelasOrang['kelas']->id)
        ->assertSet('kelasMkId', $kelasSaya['kelas']->id);
});

it('mengingat mk terpilih lewat session saat kembali tanpa parameter', function () {
    $mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id]);
    $kelas = buatKelasUntukMkInputNilai($this->dosen, $mk, 'A', $this->semesterAktif->id, 1);

    $this->actingAs($this->dosen);

    Livewire::withQueryParams(['kelas_mk_id' => $kelas['kelas']->id])
        ->test(InputNilai::class)
        ->assertSet('mkId', $mk->id);

    expect(PenilaianMkTerpilih::currentId())->toBe($mk->id);

    Livewire::test(InputNilai::class)
        ->assertSet('mkId', $mk->id);
});
