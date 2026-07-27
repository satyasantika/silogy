<?php

use App\Models\User;
use App\Modules\CPL\Filament\Resources\CplResource\Pages\CreateCpl;
use App\Modules\CPL\Filament\Resources\CplResource\Pages\EditCpl;
use App\Modules\CPL\Models\Cpl;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Models\Kurikulum;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);

    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();

    Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Domain Multiselect',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    $this->actingAs(User::query()->where('username', 'timkur')->firstOrFail());
});

it('domain dapat dipilih lebih dari satu saat membuat cpl', function () {
    Livewire::test(CreateCpl::class)
        ->fillForm([
            'academic_unit_id' => $this->prodi->id,
            'kode' => 'CPL-MULTI-01',
            'deskripsi' => 'Mampu memahami dan bersikap ilmiah',
            'domain' => ['kognitif', 'afektif'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Cpl::query()->where('kode', 'CPL-MULTI-01')->firstOrFail()->domain)
        ->toBe(['kognitif', 'afektif']);
});

it('domain yang tersimpan tampil kembali dan dapat diubah saat edit cpl', function () {
    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create(['domain' => ['kognitif']]);

    Livewire::test(EditCpl::class, ['record' => $cpl->getKey()])
        ->assertFormSet(['domain' => ['kognitif']])
        ->fillForm(['domain' => ['afektif', 'psikomotorik']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($cpl->fresh()->domain)->toBe(['afektif', 'psikomotorik']);
});
