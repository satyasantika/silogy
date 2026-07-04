<?php

use App\Models\User;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\CPL\Models\CplProfilLulusan;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Models\AcademicUnitUser;
use App\Modules\Kurikulum\Filament\Resources\KurikulumResource\Pages\CreateKurikulum;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Models\ProfilIndikator;
use App\Modules\Kurikulum\Models\ProfilLulusan;
use App\Modules\Kurikulum\Policies\KurikulumPolicy;
use App\Modules\Kurikulum\States\BokState;
use App\Modules\Kurikulum\States\CplState;
use App\Modules\Kurikulum\States\MkState;
use App\Modules\Kurikulum\States\ProfilLulusanState;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);

    $this->prodiA = AcademicUnit::query()
        ->where('type', 'study_program')
        ->where('kode_pddikti', '84202')
        ->firstOrFail();

    $this->fakultas = AcademicUnit::query()
        ->where('type', 'faculty')
        ->firstOrFail();

    $this->timkur = User::query()->where('username', 'timkur')->firstOrFail();
    $this->dosen = User::query()->where('username', 'dosen')->firstOrFail();
});

function lanjutkanStateKurikulum(Kurikulum $kurikulum, string $targetStateClass): void
{
    expect($kurikulum->state->canTransitionTo($targetStateClass))->toBeTrue();

    $kurikulum->state->transitionTo($targetStateClass);
    $kurikulum->refresh();
}

it('runs full curriculum workflow for prodi', function () {
    Livewire::actingAs($this->timkur)
        ->test(CreateKurikulum::class)
        ->fillForm([
            'academic_unit_id' => $this->prodiA->id,
            'nama' => 'Kurikulum Prodi A 2025',
            'kode' => 'KUR-2025-A',
            'tahun' => 2025,
            'target_capaian_lulusan' => 75,
            'deskripsi' => 'Kurikulum uji Sprint 2',
            'is_active' => false,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $kurikulum = Kurikulum::query()
        ->where('nama', 'Kurikulum Prodi A 2025')
        ->firstOrFail();

    expect($kurikulum->state->getValue())->toBe('draft');

    $profil = ProfilLulusan::query()->create([
        'kurikulum_id' => $kurikulum->id,
        'kode' => 'PL-01',
        'nama' => 'Software Engineer',
        'deskripsi' => 'Lulusan mampu merancang perangkat lunak',
        'urutan' => 1,
    ]);

    ProfilIndikator::query()->create([
        'profil_id' => $profil->id,
        'nama' => 'Menguasai rekayasa perangkat lunak',
        'deskripsi' => 'Indikator PL-01',
    ]);

    lanjutkanStateKurikulum($kurikulum, ProfilLulusanState::class);
    expect($kurikulum->state->getValue())->toBe('profil_lulusan');

    $cpl1 = Cpl::query()->create([
        'academic_unit_id' => $this->prodiA->id,
        'kode' => 'CPL-01',
        'deskripsi' => 'CPL pertama',
        'domain' => 'kognitif',
    ]);
    $cpl2 = Cpl::query()->create([
        'academic_unit_id' => $this->prodiA->id,
        'kode' => 'CPL-02',
        'deskripsi' => 'CPL kedua',
        'domain' => 'afektif',
    ]);

    CplProfilLulusan::query()->create([
        'cpl_id' => $cpl1->id,
        'profil_lulusan_id' => $profil->id,
    ]);
    CplProfilLulusan::query()->create([
        'cpl_id' => $cpl2->id,
        'profil_lulusan_id' => $profil->id,
    ]);

    lanjutkanStateKurikulum($kurikulum, CplState::class);
    expect($kurikulum->state->getValue())->toBe('cpl');

    $bok1 = Bok::factory()->forAcademicUnit($this->prodiA)->create(['kode' => 'BOK-01', 'nama' => 'BoK Algoritma']);
    $bok2 = Bok::factory()->forAcademicUnit($this->prodiA)->create(['kode' => 'BOK-02', 'nama' => 'BoK Basis Data']);
    $bok3 = Bok::factory()->forAcademicUnit($this->prodiA)->create(['kode' => 'BOK-03', 'nama' => 'BoK Rekayasa']);

    $cplBok1 = CplBok::query()->create(['cpl_id' => $cpl1->id, 'bok_id' => $bok1->id, 'bobot' => 40]);
    CplBok::query()->create(['cpl_id' => $cpl1->id, 'bok_id' => $bok2->id, 'bobot' => 60]);
    CplBok::query()->create(['cpl_id' => $cpl2->id, 'bok_id' => $bok3->id, 'bobot' => 100]);

    lanjutkanStateKurikulum($kurikulum, BokState::class);
    expect($kurikulum->state->getValue())->toBe('bok');

    $mks = collect();
    foreach (range(1, 4) as $i) {
        $mks->push(Mk::factory()->forAcademicUnit($this->prodiA)->create([
            'nama' => "Mata Kuliah {$i}",
            'sks_teori' => 2,
            'sks_praktik' => 1,
            'sks_lapangan' => 0,
            'sks' => 3,
        ]));
    }

    foreach ($mks as $index => $mk) {
        MkUnit::factory()->forMk($mk)->create([
            'academic_unit_id' => $this->prodiA->id,
            'kode' => 'IF10'.($index + 1),
            'semester_ke' => $index + 1,
            'is_active' => true,
        ]);
    }

    CplMk::query()->create([
        'cpl_bok_id' => $cplBok1->id,
        'mk_id' => $mks->first()->id,
        'bobot' => 100,
    ]);

    lanjutkanStateKurikulum($kurikulum, MkState::class);
    expect($kurikulum->fresh()->state->getValue())->toBe('mk');
});

it('runs workflow for faculty skipping profil lulusan', function () {
    // Seeder menyediakan akun tim kurikulum fakultas terpisah.
    $timkurFak = User::query()->where('username', 'timkurfak')->firstOrFail();

    expect(
        AcademicUnitUser::query()
            ->where('user_id', $timkurFak->id)
            ->where('academic_unit_id', $this->fakultas->id)
            ->where('status_tim_kurikulum', true)
            ->exists()
    )->toBeTrue();

    Livewire::actingAs($timkurFak)
        ->test(CreateKurikulum::class)
        ->fillForm([
            'academic_unit_id' => $this->fakultas->id,
            'nama' => 'Kurikulum Fakultas 2025',
            'kode' => 'KUR-FT-2025',
            'tahun' => 2025,
            'target_capaian_lulusan' => 75,
            'is_active' => false,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $kurikulum = Kurikulum::query()
        ->where('nama', 'Kurikulum Fakultas 2025')
        ->firstOrFail();

    expect($kurikulum->state->getValue())->toBe('draft')
        ->and($kurikulum->academicUnit->isFaculty())->toBeTrue();

    lanjutkanStateKurikulum($kurikulum, CplState::class);

    expect($kurikulum->fresh()->state->getValue())->toBe('cpl');
});

it('blocks non tim kurikulum from managing kurikulum', function () {
    Livewire::actingAs($this->timkur)
        ->test(CreateKurikulum::class)
        ->fillForm([
            'academic_unit_id' => $this->prodiA->id,
            'nama' => 'Kurikulum Terlindungi',
            'tahun' => 2025,
            'target_capaian_lulusan' => 75,
            'is_active' => false,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $kurikulum = Kurikulum::query()
        ->where('nama', 'Kurikulum Terlindungi')
        ->firstOrFail();

    $policy = new KurikulumPolicy;

    expect($policy->update($this->dosen, $kurikulum))->toBeFalse();

    expect(fn () => Gate::forUser($this->dosen)->authorize('update', $kurikulum))
        ->toThrow(AuthorizationException::class);

    expect($kurikulum->fresh()->nama)->toBe('Kurikulum Terlindungi');
});
