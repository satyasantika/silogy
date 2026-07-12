<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Support\MkTerpilih;
use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource\Pages\CreateKomponenPenilaian;
use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource\Pages\EditKomponenPenilaian;
use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource\Pages\ListKomponenPenilaians;
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

    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->first();
    $this->korma = User::where('username', 'korma')->first();
    $this->superadmin = User::where('username', 'superadmin')->first();
    $this->semester = Semester::query()->where('status_aktif', true)->first();

    $this->mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id]);
    $this->mkUnit = MkUnit::factory()->forMk($this->mk)->forAcademicUnit($this->prodi)->create(['kode' => 'IF101']);

    $this->kelasA = KelasMk::query()->create([
        'mk_unit_id' => $this->mkUnit->id,
        'semester_id' => $this->semester->id,
        'kode_kelas' => 'A',
        'koordinator_mk_id' => $this->korma->id,
    ]);
    $this->kelasB = KelasMk::query()->create([
        'mk_unit_id' => $this->mkUnit->id,
        'semester_id' => $this->semester->id,
        'kode_kelas' => 'B',
        'koordinator_mk_id' => $this->korma->id,
    ]);

    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Korma Massal',
        'tahun' => 2026,
        'is_active' => true,
    ]);
    KurikulumTerpilih::set($this->kurikulum->id);

    $this->evaluasi = Evaluasi::query()->where('kode', 'uts')->firstOrFail();
});

it('membuat asesmen baru menghasilkan satu baris untuk mk dan semester terpilih, bukan satu per kelas', function () {
    $this->actingAs($this->korma);
    MkTerpilih::set($this->mk->id);

    Livewire::test(CreateKomponenPenilaian::class)
        ->fillForm([
            'evaluasi_id' => $this->evaluasi->id,
            'kode' => 'UTS',
            'nama' => 'UTS Teori',
            'bobot' => 100,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $komponens = KomponenPenilaian::query()->where('kode', 'UTS')->get();

    expect($komponens)->toHaveCount(1)
        ->and($komponens->first()->mk_id)->toBe($this->mk->id)
        ->and($komponens->first()->semester_id)->toBe($this->semester->id)
        ->and($komponens->first()->nama)->toBe('UTS Teori');
});

it('label field pada form adalah mata kuliah, bukan kelas mk', function () {
    $this->actingAs($this->korma);
    MkTerpilih::set($this->mk->id);

    Livewire::test(CreateKomponenPenilaian::class)
        ->assertSee('Mata Kuliah', escape: false)
        ->assertDontSee('Kelas MK', escape: false);
});

it('mengedit satu-satunya asesmen langsung berlaku untuk seluruh kelas mk terkait', function () {
    $uts = KomponenPenilaian::query()->create([
        'mk_id' => $this->mk->id,
        'semester_id' => $this->semester->id,
        'evaluasi_id' => $this->evaluasi->id,
        'kode' => 'UTS',
        'nama' => 'UTS',
        'bobot' => 100,
    ]);

    $this->actingAs($this->korma);
    MkTerpilih::set($this->mk->id);

    Livewire::test(EditKomponenPenilaian::class, ['record' => $uts->getRouteKey()])
        ->assertSee('Mata Kuliah', escape: false)
        ->fillForm(['nama' => 'UTS Direvisi', 'kode' => 'UTS-1'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(KomponenPenilaian::query()->count())->toBe(1)
        ->and($uts->fresh())
        ->nama->toBe('UTS Direvisi')
        ->kode->toBe('UTS-1');
});

it('total bobot dihitung dari satu baris per kode, bukan dijumlah per kelas', function () {
    $utsA = KomponenPenilaian::query()->create([
        'mk_id' => $this->mk->id,
        'semester_id' => $this->semester->id,
        'evaluasi_id' => $this->evaluasi->id,
        'kode' => 'UTS',
        'nama' => 'UTS',
        'bobot' => 69,
    ]);

    $this->actingAs($this->korma);
    MkTerpilih::set($this->mk->id);

    Livewire::test(EditKomponenPenilaian::class, ['record' => $utsA->getRouteKey()])
        ->assertSee('Total bobot komponen pada mata kuliah dan semester ini: 69.00% dari 100% (kurang 31.00%).', escape: false);
});

it('mengimpor satu baris asesmen menghasilkan satu komponen yang dipakai bersama semua kelas mk (regresi 4x duplikat)', function () {
    $this->actingAs($this->korma);
    MkTerpilih::set($this->mk->id);

    Livewire::test(ListKomponenPenilaians::class)
        ->callAction('bulkImport', data: [
            'import_mk_id' => $this->mk->id,
            'import_semester_id' => $this->semester->id,
            'rows' => 'Asesmen01|Kuis Konseptual|100|Quiz|',
        ])
        ->assertHasNoActionErrors();

    $komponens = KomponenPenilaian::query()->where('kode', 'Asesmen01')->get();

    expect($komponens)->toHaveCount(1)
        ->and($komponens->first()->mk_id)->toBe($this->mk->id)
        ->and($komponens->first()->semester_id)->toBe($this->semester->id);
});
