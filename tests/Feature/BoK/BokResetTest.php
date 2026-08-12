<?php

use App\Models\User;
use App\Modules\BoK\Filament\Resources\BokResource\Pages\ListBoks;
use App\Modules\BoK\Models\Bok;
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
        'nama' => 'Kurikulum Uji Reset BoK',
        'kode' => Str::upper(Str::random(5)),
        'tahun' => 2026,
        'is_active' => true,
    ]);

    $this->actingAs($this->timkur);
    KurikulumTerpilih::set($this->kurikulum->id);
});

it('tombol buat tidak lagi ada, impor massal selalu tampil', function () {
    Livewire::test(ListBoks::class)
        ->assertActionDoesNotExist('create')
        ->assertActionExists('bulkImport');
});

it('tombol reset aktif saat belum ada bok yang dipetakan ke cpl', function () {
    Bok::factory()->forKurikulum($this->kurikulum)->create();

    Livewire::test(ListBoks::class)
        ->assertActionEnabled('resetData');
});

it('tombol reset nonaktif saat bok sudah dipetakan ke cpl', function () {
    $bok = Bok::factory()->forKurikulum($this->kurikulum)->create();
    $cpl = Cpl::factory()->forKurikulum($this->kurikulum)->create();
    CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id]);

    Livewire::test(ListBoks::class)
        ->assertActionDisabled('resetData');
});

it('reset menghapus seluruh bok kurikulum ini tanpa menyentuh kurikulum lain', function () {
    $bok = Bok::factory()->forKurikulum($this->kurikulum)->create();

    $kurikulumLain = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Lain',
        'kode' => Str::upper(Str::random(5)),
        'tahun' => 2025,
        'is_active' => false,
    ]);
    $bokLain = Bok::factory()->forKurikulum($kurikulumLain)->create();

    Livewire::test(ListBoks::class)
        ->callAction('resetData');

    $this->assertDatabaseMissing('bok', ['id' => $bok->id]);
    $this->assertDatabaseHas('bok', ['id' => $bokLain->id]);
});
