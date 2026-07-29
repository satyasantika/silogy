<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Filament\Resources\MkResource;
use App\Modules\MK\Filament\Resources\MkResource\Pages\ListMks;
use App\Modules\MK\Models\Mk;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Filament\Tables\Columns\SelectColumn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);

    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->timkur = User::query()->where('username', 'timkur')->firstOrFail();
    $this->korma = User::query()->where('username', 'korma')->firstOrFail();
    $this->dosen = User::query()->where('username', 'dosen')->firstOrFail();

    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum MK Inline',
        'tahun' => 2026,
        'is_active' => true,
    ]);
});

it('opsi koordinator hanya dosen pengampu dengan format nama (kode)', function () {
    $this->dosen->load('academicUnits');

    $opsi = MkResource::koordinatorMkOptions();

    expect(MkResource::labelKoordinatorMk($this->dosen))
        ->toBe("{$this->dosen->full_name} ({$this->prodi->code})")
        ->and($opsi[$this->dosen->id] ?? null)->toBe("{$this->dosen->full_name} ({$this->prodi->code})")
        ->and(array_key_exists($this->korma->id, $opsi))->toBeFalse()
        ->and($this->dosen->hasRole('Dosen Pengampu'))->toBeTrue()
        ->and($this->korma->hasRole('Dosen Pengampu'))->toBeFalse();
});

it('tabel mk dapat menetapkan koordinator lewat select inline tanpa halaman edit', function () {
    $this->actingAs($this->timkur);
    KurikulumTerpilih::set($this->kurikulum->id);

    $mk = Mk::factory()->forKurikulum($this->kurikulum)->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'MK Koordinator Inline',
        'koordinator_mk_id' => null,
    ]);

    $page = Livewire::test(ListMks::class)
        ->assertCanSeeTableRecords([$mk])
        ->assertTableColumnExists('koordinator_mk_id');

    $column = $page->instance()->getTable()->getColumn('koordinator_mk_id');

    expect($column)->toBeInstanceOf(SelectColumn::class)
        ->and($column->getOptions()[$this->dosen->id] ?? null)
        ->toBe("{$this->dosen->full_name} ({$this->prodi->code})")
        ->and($this->dosen->fresh()->hasRole('Koordinator Mata Kuliah'))->toBeFalse();

    $page->call('updateTableColumnState', 'koordinator_mk_id', $mk->getKey(), $this->dosen->id)
        ->assertHasNoErrors();

    expect($mk->fresh()->koordinator_mk_id)->toBe($this->dosen->id)
        ->and($this->dosen->fresh()->hasRole('Koordinator Mata Kuliah'))->toBeTrue()
        ->and($this->dosen->fresh()->hasRole('Dosen Pengampu'))->toBeTrue();
});

it('select inline koordinator dapat dikosongkan kembali', function () {
    $this->actingAs($this->timkur);
    KurikulumTerpilih::set($this->kurikulum->id);

    $mk = Mk::factory()->forKurikulum($this->kurikulum)->create([
        'academic_unit_id' => $this->prodi->id,
        'koordinator_mk_id' => $this->dosen->id,
    ]);

    expect($this->dosen->fresh()->hasRole('Koordinator Mata Kuliah'))->toBeTrue();

    Livewire::test(ListMks::class)
        ->call('updateTableColumnState', 'koordinator_mk_id', $mk->getKey(), null)
        ->assertHasNoErrors();

    expect($mk->fresh()->koordinator_mk_id)->toBeNull()
        ->and($this->dosen->fresh()->hasRole('Koordinator Mata Kuliah'))->toBeFalse();
});
