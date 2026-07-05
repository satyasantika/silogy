<?php

use App\Models\User;
use App\Modules\BoK\Filament\Resources\BokResource\Pages\EditBok;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Filament\Resources\CplResource\Pages\EditCpl;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\Institusi\Models\AcademicUnit;
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
    $this->actingAs(User::query()->where('username', 'timkur')->firstOrFail());
});

it('tombol hapus tampil di edit cpl bila belum diinteraksikan', function () {
    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create();

    Livewire::test(EditCpl::class, ['record' => $cpl->getKey()])
        ->assertActionVisible('delete');
});

it('tombol hapus tersembunyi di edit cpl bila sudah dipetakan ke bok', function () {
    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create();
    $bok = Bok::factory()->forAcademicUnit($this->prodi)->create();

    CplBok::query()->create([
        'cpl_id' => $cpl->id,
        'bok_id' => $bok->id,
    ]);

    Livewire::test(EditCpl::class, ['record' => $cpl->getKey()])
        ->assertActionHidden('delete');
});

it('tombol hapus tampil di edit bok bila belum diinteraksikan', function () {
    $bok = Bok::factory()->forAcademicUnit($this->prodi)->create();

    Livewire::test(EditBok::class, ['record' => $bok->getKey()])
        ->assertActionVisible('delete');
});

it('tombol hapus tersembunyi di edit bok bila sudah dipetakan ke cpl', function () {
    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create();
    $bok = Bok::factory()->forAcademicUnit($this->prodi)->create();

    CplBok::query()->create([
        'cpl_id' => $cpl->id,
        'bok_id' => $bok->id,
    ]);

    Livewire::test(EditBok::class, ['record' => $bok->getKey()])
        ->assertActionHidden('delete');
});
