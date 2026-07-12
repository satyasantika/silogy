<?php

use App\Models\User;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Filament\Resources\CpmkResource\Pages\CreateCpmk;
use App\Modules\MK\Filament\Resources\SubcpmkResource\Pages\CreateSubcpmk;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkCpmk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\MK\Support\MkTerpilih;
use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource;
use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource\Pages\CreateKomponenPenilaian;
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
    $this->dosen = User::where('username', 'dosen')->first();
    $this->semester = Semester::query()->where('status_aktif', true)->first();

    $this->mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id]);
    $this->mkUnit = MkUnit::factory()->forMk($this->mk)->forAcademicUnit($this->prodi)->create([
        'kode' => 'IF101',
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

    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Korma Resource',
        'tahun' => 2026,
        'is_active' => true,
    ]);
    KurikulumTerpilih::set($this->kurikulum->id);
});

it('korma dapat membuat cpmk subcpmk dan komponen penilaian', function () {
    $this->actingAs($this->korma);
    MkTerpilih::set($this->mk->id);

    Livewire::test(CreateCpmk::class)
        ->fillForm([
            'mk_id' => $this->mk->id,
            'kode' => 'CPMK-01',
            'deskripsi' => 'Mahasiswa mampu menjelaskan konsep dasar.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $cpmk = Cpmk::query()->where('kode', 'CPMK-01')->firstOrFail();

    $mkCpmk = MkCpmk::query()->create([
        'cpl_mk_id' => $this->cplMk->id,
        'cpmk_id' => $cpmk->id,
        'bobot' => 100,
    ]);

    Livewire::test(CreateSubcpmk::class)
        ->fillForm([
            'mk_cpmk_id' => $mkCpmk->id,
            'semester_id' => $this->semester->id,
            'kode' => 'SUB-01',
            'deskripsi' => 'Sub capaian pembelajaran.',
            'bobot' => 100,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $evaluasi = Evaluasi::query()->where('kode', 'uts')->firstOrFail();

    Livewire::test(CreateKomponenPenilaian::class)
        ->fillForm([
            'evaluasi_id' => $evaluasi->id,
            'kode' => 'UTS',
            'nama' => 'UTS Teori',
            'bobot' => 100,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Cpmk::query()->count())->toBe(1)
        ->and(Subcpmk::query()->count())->toBe(1)
        ->and(KomponenPenilaian::query()->count())->toBe(1);
});

it('dosen pengampu tidak melihat menu komponen penilaian', function () {
    $this->actingAs($this->dosen);

    expect(KomponenPenilaianResource::shouldRegisterNavigation())->toBeFalse();
});

it('korma melihat menu komponen penilaian setelah mk dipilih', function () {
    $this->actingAs($this->korma);
    MkTerpilih::set($this->mk->id);

    expect(KomponenPenilaianResource::shouldRegisterNavigation())->toBeTrue();
});
