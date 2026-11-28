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
use App\Modules\MK\Filament\Resources\CpmkResource\Pages\EditCpmk;
use App\Modules\MK\Filament\Resources\CpmkResource\Pages\ListCpmks;
use App\Modules\MK\Filament\Resources\SubcpmkResource;
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

    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Korma Resource',
        'tahun' => 2026,
        'is_active' => true,
    ]);
    KurikulumTerpilih::set($this->kurikulum->id);

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
});

it('cpmk satuan dengan CPL terpilih langsung terpetakan sehingga muncul di pilihan Sub-CPMK', function () {
    $this->actingAs($this->korma);
    MkTerpilih::set($this->mk->id);
    $kodeCpl = $this->cplMk->cplBok->cpl->kode;

    Livewire::test(CreateCpmk::class)
        ->fillForm([
            'mk_id' => $this->mk->id,
            'kode' => 'CPMK-MAP',
            'deskripsi' => 'Mahasiswa mampu memetakan CPMK ke CPL.',
            'kode_cpl_terpetakan' => [$kodeCpl],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $cpmk = Cpmk::query()->where('kode', 'CPMK-MAP')->firstOrFail();

    expect(MkCpmk::query()->where('cpmk_id', $cpmk->id)->where('cpl_mk_id', $this->cplMk->id)->exists())->toBeTrue()
        ->and(SubcpmkResource::mkCpmkOptions())->not->toBeEmpty();
});

it('cpmk bisa dipetakan ke beberapa CPL saat dibuat dan disinkronkan saat diedit', function () {
    $this->actingAs($this->korma);
    MkTerpilih::set($this->mk->id);

    $cpl2 = Cpl::factory()->forAcademicUnit($this->prodi)->create();
    $bok2 = Bok::factory()->forAcademicUnit($this->prodi)->create();
    $cplMk2 = CplMk::query()->create([
        'cpl_bok_id' => CplBok::query()->create(['cpl_id' => $cpl2->id, 'bok_id' => $bok2->id, 'bobot' => 100])->id,
        'mk_id' => $this->mk->id,
        'bobot' => 100,
    ]);
    $kode1 = $this->cplMk->cplBok->cpl->kode;
    $kode2 = $cpl2->kode;

    Livewire::test(CreateCpmk::class)
        ->fillForm([
            'mk_id' => $this->mk->id,
            'kode' => 'CPMK-MULTI',
            'deskripsi' => 'Berinteraksi dengan dua CPL.',
            'kode_cpl_terpetakan' => [$kode1, $kode2],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $cpmk = Cpmk::query()->where('kode', 'CPMK-MULTI')->firstOrFail();
    expect(MkCpmk::query()->where('cpmk_id', $cpmk->id)->pluck('cpl_mk_id')->sort()->values()->all())
        ->toEqual(collect([$this->cplMk->id, $cplMk2->id])->sort()->values()->all());

    // Edit: tampil terisi, lalu lepas CPL kedua.
    Livewire::test(EditCpmk::class, ['record' => $cpmk->getRouteKey()])
        ->assertFormSet(['kode_cpl_terpetakan' => [$kode1, $kode2]])
        ->fillForm(['kode_cpl_terpetakan' => [$kode1]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(MkCpmk::query()->where('cpmk_id', $cpmk->id)->pluck('cpl_mk_id')->all())->toBe([$this->cplMk->id]);

    // CPL yang dipakai Sub-CPMK terkunci: tak bisa dilepas lewat form.
    $mkCpmk = MkCpmk::query()->where('cpmk_id', $cpmk->id)->firstOrFail();
    $sub = subcpmkUntukSemester($mkCpmk, $this->semester, ['kode' => 'SUB-KUNCI']);

    Livewire::test(EditCpmk::class, ['record' => $cpmk->getRouteKey()])
        ->set('data.kode_cpl_terpetakan', [])
        ->assertFormSet(['kode_cpl_terpetakan' => [$kode1]])
        ->call('save');

    expect(MkCpmk::query()->where('cpmk_id', $cpmk->id)->count())->toBe(1);

    // Tanpa Sub-CPMK yang memakainya, CPL bisa dilepas kembali.
    $sub->delete();

    Livewire::test(EditCpmk::class, ['record' => $cpmk->getRouteKey()])
        ->fillForm(['kode_cpl_terpetakan' => []])
        ->call('save');

    expect(MkCpmk::query()->where('cpmk_id', $cpmk->id)->count())->toBe(0);
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

it('tidak lagi menggunakan pagination pada tabel cpmk', function () {
    $this->actingAs($this->korma);
    MkTerpilih::set($this->mk->id);

    $test = Livewire::test(ListCpmks::class)->loadTable();

    expect($test->instance()->getTable()->isPaginated())->toBeFalse();
});
