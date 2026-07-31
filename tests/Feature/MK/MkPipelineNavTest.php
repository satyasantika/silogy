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
use App\Modules\MK\Filament\Resources\CpmkResource\Pages\ListCpmks;
use App\Modules\MK\Filament\Resources\SubcpmkResource\Pages\ListSubcpmks;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkCpmk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\MK\Support\MkTerpilih;
use App\Modules\Penilaian\Filament\Pages\LaporanKoordinator;
use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource\Pages\ListKomponenPenilaians;
use App\Modules\Penilaian\Filament\Resources\PesertaKelasResource\Pages\ListPesertaKelas;
use App\Modules\Penilaian\Models\Evaluasi;
use App\Modules\Penilaian\Models\KomponenPenilaian;
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
    $this->korma = User::query()->where('username', 'korma')->firstOrFail();
    $this->semester = Semester::query()->where('status_aktif', true)->firstOrFail();

    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Pipeline MK',
        'tahun' => 2026,
        'is_active' => true,
    ]);
    KurikulumTerpilih::set($this->kurikulum->id);

    $this->mk = Mk::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'koordinator_mk_id' => $this->korma->id,
    ]);
    $this->mkUnit = MkUnit::factory()->forMk($this->mk)->forAcademicUnit($this->prodi)->create([
        'kurikulum_id' => $this->kurikulum->id,
        'kode' => 'IF101',
        'is_active' => true,
    ]);
    $this->kelas = KelasMk::query()->create([
        'mk_unit_id' => $this->mkUnit->id,
        'semester_id' => $this->semester->id,
        'kode_kelas' => 'A',
        'koordinator_mk_id' => $this->korma->id,
    ]);

    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create();
    $bok = Bok::factory()->forAcademicUnit($this->prodi)->create();
    $cplBok = CplBok::query()->create([
        'cpl_id' => $cpl->id,
        'bok_id' => $bok->id,
        'bobot' => 100,
    ]);
    $this->cplMk = CplMk::query()->create([
        'cpl_bok_id' => $cplBok->id,
        'mk_id' => $this->mk->id,
        'bobot' => 100,
    ]);
});

function buatCpmkDenganPemetaan(): Cpmk
{
    $cpmk = Cpmk::query()->create([
        'mk_id' => test()->mk->id,
        'kode' => 'CPMK-01',
        'deskripsi' => 'Mahasiswa memahami konsep dasar',
    ]);

    MkCpmk::query()->create([
        'cpl_mk_id' => test()->cplMk->id,
        'cpmk_id' => $cpmk->id,
        'bobot' => 100,
    ]);

    return $cpmk;
}

function buatSubcpmk(Cpmk $cpmk): Subcpmk
{
    $mkCpmk = MkCpmk::query()->where('cpmk_id', $cpmk->id)->firstOrFail();

    return Subcpmk::query()->create([
        'mk_cpmk_id' => $mkCpmk->id,
        'semester_id' => test()->semester->id,
        'kode' => 'SUB-01',
        'deskripsi' => 'Menjelaskan definisi',
    ]);
}

function buatAsesmen(): KomponenPenilaian
{
    $evaluasi = Evaluasi::query()->firstOrFail();

    return KomponenPenilaian::query()->create([
        'mk_id' => test()->mk->id,
        'semester_id' => test()->semester->id,
        'evaluasi_id' => $evaluasi->id,
        'kode' => 'ASES-01',
        'nama' => 'Kuis 1',
        'bobot' => 100,
    ]);
}

it('cpmk kosong: back ke mata kuliah, tanpa next ke sub-cpmk', function () {
    $this->actingAs($this->korma);
    MkTerpilih::set($this->mk->id);

    Livewire::test(ListCpmks::class)
        ->assertSee('« Mata Kuliah', escape: false)
        ->assertDontSee('Sub-CPMK »', escape: false);
});

it('cpmk terisi: next ke sub-cpmk, dan sub-cpmk back ke cpmk', function () {
    buatCpmkDenganPemetaan();

    $this->actingAs($this->korma);
    MkTerpilih::set($this->mk->id);

    Livewire::test(ListCpmks::class)
        ->assertSee('Sub-CPMK »', escape: false);

    Livewire::test(ListSubcpmks::class)
        ->assertSee('« CPMK', escape: false)
        ->assertDontSee('Asesmen »', escape: false);
});

it('next bertahap sampai mahasiswa, dan mahasiswa saling bertaut dengan laporan', function () {
    $cpmk = buatCpmkDenganPemetaan();
    buatSubcpmk($cpmk);
    buatAsesmen();

    $mahasiswa = Mahasiswa::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'nim' => '227000001',
    ]);
    KelasMkMahasiswa::query()->create([
        'kelas_mk_id' => $this->kelas->id,
        'mahasiswa_id' => $mahasiswa->id,
    ]);

    $this->actingAs($this->korma);
    MkTerpilih::set($this->mk->id);

    Livewire::test(ListSubcpmks::class)->assertSee('Asesmen »', escape: false);
    // Asesmen: next tunggal diganti dua tombol (Laporan & Mahasiswa), sudah
    // ada komponen penilaian untuk semester aktif (semester terpilih default).
    Livewire::test(ListKomponenPenilaians::class)
        ->assertSee('Laporan »', escape: false)
        ->assertSee('Mahasiswa »', escape: false);
    Livewire::test(LaporanKoordinator::class)
        ->assertSee('« Asesmen', escape: false)
        ->assertSee('Mahasiswa »', escape: false);
    // Mahasiswa: back melompat balik ke Asesmen (bukan ke Laporan), next ke Laporan.
    Livewire::test(ListPesertaKelas::class)
        ->assertSee('« Asesmen', escape: false)
        ->assertSee('Laporan »', escape: false)
        ->assertDontSee('« Laporan', escape: false);
});

it('asesmen: tombol Laporan/Mahasiswa hanya muncul bila ada komponen penilaian pada semester terpilih', function () {
    $cpmk = buatCpmkDenganPemetaan();
    buatSubcpmk($cpmk);

    $semesterLain = Semester::query()->create([
        'kode' => '20231',
        'nama' => 'Ganjil 2023/2024',
        'tahun_mulai' => 2023,
        'tahun_selesai' => 2024,
        'jenis' => 'ganjil',
        'status_aktif' => false,
    ]);

    // Komponen penilaian dibuat untuk semester LAIN, bukan semester yang
    // sedang dipilih ($this->semester, aktif) — tombol lanjutan tidak boleh
    // muncul karena secara semester belum ada asesmen.
    KomponenPenilaian::query()->create([
        'mk_id' => $this->mk->id,
        'semester_id' => $semesterLain->id,
        'evaluasi_id' => Evaluasi::query()->firstOrFail()->id,
        'kode' => 'ASES-LAIN',
        'nama' => 'Kuis semester lain',
        'bobot' => 100,
    ]);

    $this->actingAs($this->korma);
    MkTerpilih::set($this->mk->id);

    Livewire::test(ListKomponenPenilaians::class)
        ->assertDontSee('Laporan »', escape: false)
        ->assertDontSee('Mahasiswa »', escape: false);

    // Begitu komponen penilaian ditambahkan untuk semester yang sedang
    // dipilih, kedua tombol langsung muncul.
    buatAsesmen();

    Livewire::test(ListKomponenPenilaians::class)
        ->assertSee('Laporan »', escape: false)
        ->assertSee('Mahasiswa »', escape: false);
});

it('cpmk: tombol next ke sub-cpmk muncul setelah impor massal tanpa reload', function () {
    $this->actingAs($this->korma);
    MkTerpilih::set($this->mk->id);

    $halaman = Livewire::test(ListCpmks::class)
        ->assertDontSee('Sub-CPMK »', escape: false);

    $halaman->callAction('bulkImport', [
        'import_mk_id' => $this->mk->id,
        'rows' => "CPMK-IMPOR-01\tMahasiswa mampu menganalisis\t",
        'mode_duplikat' => 'lewati',
    ]);

    $halaman->assertSee('Sub-CPMK »', escape: false);
});
