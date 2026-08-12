<?php

use App\Models\User;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Filament\Resources\CplResource\Pages\ListCpls;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);

    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->timkur = User::query()->where('username', 'timkur')->firstOrFail();

    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Uji Reset CPL',
        'kode' => Str::upper(Str::random(5)),
        'tahun' => 2026,
        'is_active' => true,
    ]);

    $this->actingAs($this->timkur);
    KurikulumTerpilih::set($this->kurikulum->id);
});

it('tombol buat tidak lagi ada, impor massal selalu tampil', function () {
    Livewire::test(ListCpls::class)
        ->assertActionDoesNotExist('create')
        ->assertActionExists('bulkImport');
});

it('tombol reset aktif saat belum ada cpl yang dipetakan ke bok', function () {
    Cpl::factory()->forKurikulum($this->kurikulum)->create();

    Livewire::test(ListCpls::class)
        ->assertActionEnabled('resetData');
});

it('tombol reset nonaktif saat cpl sudah dipetakan ke bok', function () {
    $cpl = Cpl::factory()->forKurikulum($this->kurikulum)->create();
    $bok = Bok::factory()->forKurikulum($this->kurikulum)->create();
    CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id]);

    Livewire::test(ListCpls::class)
        ->assertActionDisabled('resetData');
});

it('reset menghapus seluruh cpl kurikulum ini tanpa menyentuh kurikulum lain', function () {
    $cpl = Cpl::factory()->forKurikulum($this->kurikulum)->create();

    $kurikulumLain = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Lain',
        'kode' => Str::upper(Str::random(5)),
        'tahun' => 2025,
        'is_active' => false,
    ]);
    $cplLain = Cpl::factory()->forKurikulum($kurikulumLain)->create();

    Livewire::test(ListCpls::class)
        ->callAction('resetData');

    $this->assertDatabaseMissing('cpl', ['id' => $cpl->id]);
    $this->assertDatabaseHas('cpl', ['id' => $cplLain->id]);
});
