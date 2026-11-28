<?php

use App\Models\User;
use App\Modules\BoK\Filament\Resources\BokResource\Pages\CreateBok;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Filament\Resources\CplResource\Pages\CreateCpl;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\CPL\Models\CplProfilLulusan;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Models\ProfilLulusan;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Filament\Resources\MkResource\Pages\CreateMk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkCpmk;
use App\Modules\MK\Support\MkTerpilih;
use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource\Pages\CreateKomponenPenilaian;
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
    $this->semester = Semester::query()->where('status_aktif', true)->firstOrFail();
    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Pemetaan Saat Buat',
        'kode' => 'PSBKT',
        'tahun' => 2026,
        'is_active' => true,
    ]);
    KurikulumTerpilih::set($this->kurikulum->id);
});

it('cpl baru langsung terpetakan ke beberapa profil lulusan yang dipilih', function () {
    $this->actingAs(User::query()->where('username', 'timkur')->firstOrFail());
    $pl1 = ProfilLulusan::query()->create(['kurikulum_id' => $this->kurikulum->id, 'kode' => 'PL-1', 'nama' => 'Pendidik', 'deskripsi' => 'Pendidik.']);
    $pl2 = ProfilLulusan::query()->create(['kurikulum_id' => $this->kurikulum->id, 'kode' => 'PL-2', 'nama' => 'Peneliti', 'deskripsi' => 'Peneliti.']);
    ProfilLulusan::query()->create(['kurikulum_id' => $this->kurikulum->id, 'kode' => 'PL-3', 'nama' => 'Tak dipilih', 'deskripsi' => 'Lain.']);

    Livewire::test(CreateCpl::class)
        ->fillForm([
            'academic_unit_id' => $this->prodi->id,
            'kode' => 'CPL-P1',
            'deskripsi' => 'CPL dengan profil.',
            'profil_lulusan_ids' => [$pl1->id, $pl2->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $cpl = Cpl::query()->where('kode', 'CPL-P1')->firstOrFail();

    expect(CplProfilLulusan::query()->where('cpl_id', $cpl->id)->pluck('profil_lulusan_id')->sort()->values()->all())
        ->toEqual(collect([$pl1->id, $pl2->id])->sort()->values()->all());
});

it('cpl tanpa pilihan profil tetap dibuat tanpa pemetaan', function () {
    $this->actingAs(User::query()->where('username', 'timkur')->firstOrFail());

    Livewire::test(CreateCpl::class)
        ->fillForm(['academic_unit_id' => $this->prodi->id, 'kode' => 'CPL-P0', 'deskripsi' => 'Tanpa profil.'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(CplProfilLulusan::query()->count())->toBe(0);
});

it('bok baru langsung terpetakan ke beberapa cpl yang dipilih', function () {
    $this->actingAs(User::query()->where('username', 'timkur')->firstOrFail());
    $cpl1 = Cpl::factory()->forKurikulum($this->kurikulum)->create();
    $cpl2 = Cpl::factory()->forKurikulum($this->kurikulum)->create();
    Cpl::factory()->forKurikulum($this->kurikulum)->create();

    Livewire::test(CreateBok::class)
        ->fillForm([
            'academic_unit_id' => $this->prodi->id,
            'kode' => 'BOK-P1',
            'nama' => 'BoK dengan CPL',
            'cpl_ids' => [$cpl1->id, $cpl2->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $bok = Bok::query()->where('kode', 'BOK-P1')->firstOrFail();

    expect(CplBok::query()->where('bok_id', $bok->id)->pluck('cpl_id')->sort()->values()->all())
        ->toEqual(collect([$cpl1->id, $cpl2->id])->sort()->values()->all());
});

it('mk baru dipetakan ke pasangan cpl-bok terpilih dengan bobot dibagi rata hingga 100', function () {
    $this->actingAs(User::query()->where('username', 'timkur')->firstOrFail());
    $pasangan = collect(range(1, 3))->map(fn () => CplBok::query()->create([
        'cpl_id' => Cpl::factory()->forKurikulum($this->kurikulum)->create()->id,
        'bok_id' => Bok::factory()->forKurikulum($this->kurikulum)->create()->id,
    ]));

    Livewire::test(CreateMk::class)
        ->fillForm([
            'academic_unit_id' => $this->prodi->id,
            'nama' => 'MK Pemetaan',
            'sks_teori' => 2,
            'jenis' => 'wajib',
            'cpl_bok_ids' => $pasangan->pluck('id')->all(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $mk = Mk::query()->where('nama', 'MK Pemetaan')->firstOrFail();
    $bobot = CplMk::query()->where('mk_id', $mk->id)->pluck('bobot');

    expect($bobot)->toHaveCount(3)
        ->and(round((float) $bobot->sum(), 1))->toBe(100.0)
        ->and(CplMk::query()->where('mk_id', $mk->id)->pluck('cpl_bok_id')->sort()->values()->all())
        ->toEqual($pasangan->pluck('id')->sort()->values()->all());
});

it('asesmen baru langsung terpetakan ke beberapa sub-cpmk dengan bobot asesmen dibagi rata', function () {
    $korma = User::query()->where('username', 'korma')->firstOrFail();
    $mk = Mk::factory()->forKurikulum($this->kurikulum)->create([
        'academic_unit_id' => $this->prodi->id,
        'koordinator_mk_id' => $korma->id,
    ]);
    $cplMk = CplMk::query()->create([
        'cpl_bok_id' => CplBok::query()->create([
            'cpl_id' => Cpl::factory()->forKurikulum($this->kurikulum)->create()->id,
            'bok_id' => Bok::factory()->forKurikulum($this->kurikulum)->create()->id,
        ])->id,
        'mk_id' => $mk->id,
        'bobot' => 100,
    ]);
    $cpmk = cpmkUntukSemester($mk, $this->semester, ['kode' => 'CPMK01']);
    $mkCpmk = MkCpmk::query()->create(['cpl_mk_id' => $cplMk->id, 'cpmk_id' => $cpmk->id, 'bobot' => 100]);
    $sub1 = subcpmkUntukSemester($mkCpmk, $this->semester, ['kode' => 'SUB1']);
    $sub2 = subcpmkUntukSemester($mkCpmk, $this->semester, ['kode' => 'SUB2']);

    $this->actingAs($korma);
    MkTerpilih::set($mk->id);

    Livewire::test(CreateKomponenPenilaian::class)
        ->fillForm([
            'evaluasi_id' => Evaluasi::query()->where('kode', 'uts')->firstOrFail()->id,
            'kode' => 'UTS',
            'nama' => 'UTS Teori',
            'bobot' => 40,
            'subcpmk_ids' => [$sub1->id, $sub2->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $komponen = KomponenPenilaian::query()->where('kode', 'UTS')->firstOrFail();
    $pivot = SubcpmkKomponenPenilaian::query()->where('komponen_penilaian_id', $komponen->id)->get();

    expect($pivot)->toHaveCount(2)
        ->and($pivot->pluck('subcpmk_id')->sort()->values()->all())->toEqual(collect([$sub1->id, $sub2->id])->sort()->values()->all())
        ->and($pivot->pluck('bobot')->map(fn ($b) => (float) $b)->all())->toBe([20.0, 20.0]);
});
