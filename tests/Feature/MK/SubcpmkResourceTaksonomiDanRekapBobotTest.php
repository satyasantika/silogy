<?php

use App\Models\User;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Filament\Resources\SubcpmkResource\Pages\ListSubcpmks;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkCpmk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\MK\Support\MkTerpilih;
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
    $this->semester = Semester::query()->where('status_aktif', true)->firstOrFail();

    $this->mk = Mk::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'koordinator_mk_id' => $this->korma->id,
    ]);
    MkUnit::factory()->forMk($this->mk)->forAcademicUnit($this->prodi)->create(['kode' => 'IF101']);

    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create();
    $bok = Bok::factory()->forAcademicUnit($this->prodi)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id, 'bobot' => 100]);
    $cplMk = CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $this->mk->id, 'bobot' => 100]);
    $cpmk = Cpmk::query()->create(['mk_id' => $this->mk->id, 'kode' => 'CPMK-01', 'deskripsi' => 'Uji']);
    $this->mkCpmk = MkCpmk::query()->create(['cpl_mk_id' => $cplMk->id, 'cpmk_id' => $cpmk->id, 'bobot' => 100]);

    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Uji Subcpmk',
        'tahun' => 2026,
        'is_active' => true,
    ]);
    KurikulumTerpilih::set($this->kurikulum->id);

    $this->actingAs($this->korma);
    MkTerpilih::set($this->mk->id);
});

it('menampilkan badge taksonomi bloom pada tabel subcpmk', function () {
    Subcpmk::query()->create([
        'mk_cpmk_id' => $this->mkCpmk->id,
        'semester_id' => $this->semester->id,
        'kode' => 'SubCPMK04.1',
        'deskripsi' => 'Uji taksonomi',
        'bloom_kognitif' => 'C2',
        'bloom_afektif' => 'A2',
        'bloom_psikomotorik' => 'P1',
    ]);

    Livewire::test(ListSubcpmks::class)->loadTable()
        ->assertSee('C2', escape: false)
        ->assertSee('A2', escape: false)
        ->assertSee('P1', escape: false);
});

it('menampilkan rekap bobot evaluasi berdekatan dengan kolom bobot subcpmk', function () {
    $subcpmk = Subcpmk::query()->create([
        'mk_cpmk_id' => $this->mkCpmk->id,
        'semester_id' => $this->semester->id,
        'kode' => 'SubCPMK04.1',
        'deskripsi' => 'Uji rekap bobot',
        'bobot' => 15,
    ]);

    $tugas = Evaluasi::query()->where('kode', 'tugas')->firstOrFail();
    $proyek = Evaluasi::query()->where('kode', 'proyek_individu')->firstOrFail();
    $partisipasi = Evaluasi::query()->where('kode', 'partisipasi_individu')->firstOrFail();

    $komponenTugas = KomponenPenilaian::query()->create([
        'mk_id' => $this->mk->id, 'semester_id' => $this->semester->id,
        'evaluasi_id' => $tugas->id, 'kode' => 'T1', 'nama' => 'Tugas 1', 'bobot' => 25,
    ]);
    $komponenProyek = KomponenPenilaian::query()->create([
        'mk_id' => $this->mk->id, 'semester_id' => $this->semester->id,
        'evaluasi_id' => $proyek->id, 'kode' => 'PR1', 'nama' => 'Proyek 1', 'bobot' => 15,
    ]);
    $komponenPartisipasi = KomponenPenilaian::query()->create([
        'mk_id' => $this->mk->id, 'semester_id' => $this->semester->id,
        'evaluasi_id' => $partisipasi->id, 'kode' => 'PT1', 'nama' => 'Partisipasi 1', 'bobot' => 10,
    ]);

    // Kontribusi ke nilai akhir = bobot komponen (dari 100%) x bobot pivot Sub-CPMK.
    SubcpmkKomponenPenilaian::query()->create(['subcpmk_id' => $subcpmk->id, 'komponen_penilaian_id' => $komponenTugas->id, 'bobot' => 20]); // 25 * 20% = 5
    SubcpmkKomponenPenilaian::query()->create(['subcpmk_id' => $subcpmk->id, 'komponen_penilaian_id' => $komponenProyek->id, 'bobot' => 20]); // 15 * 20% = 3
    SubcpmkKomponenPenilaian::query()->create(['subcpmk_id' => $subcpmk->id, 'komponen_penilaian_id' => $komponenPartisipasi->id, 'bobot' => 20]); // 10 * 20% = 2

    Livewire::test(ListSubcpmks::class)->loadTable()
        ->assertSee('Bobot evaluasi: 10%', escape: false)
        ->assertSee('Tugas (5%)', escape: false)
        ->assertSee('Proyek Individu (3%)', escape: false)
        ->assertSee('Partisipasi Individu (2%)', escape: false);
});

it('menampilkan keterangan belum ada asesmen terpetakan bila subcpmk belum dipetakan ke komponen penilaian', function () {
    Subcpmk::query()->create([
        'mk_cpmk_id' => $this->mkCpmk->id,
        'semester_id' => $this->semester->id,
        'kode' => 'SubCPMK04.2',
        'deskripsi' => 'Belum dipetakan',
    ]);

    Livewire::test(ListSubcpmks::class)->loadTable()
        ->assertSee('Belum ada asesmen terpetakan', escape: false);
});

it('tidak lagi menampilkan kolom semester pada tabel subcpmk', function () {
    Subcpmk::query()->create([
        'mk_cpmk_id' => $this->mkCpmk->id,
        'semester_id' => $this->semester->id,
        'kode' => 'SubCPMK04.3',
        'deskripsi' => 'Uji tanpa semester',
    ]);

    $test = Livewire::test(ListSubcpmks::class)->loadTable();

    expect($test->instance()->getTable()->getColumn('semester.kode'))->toBeNull();
});

it('tidak lagi menggunakan pagination pada tabel subcpmk', function () {
    $test = Livewire::test(ListSubcpmks::class)->loadTable();

    expect($test->instance()->getTable()->isPaginated())->toBeFalse();
});
