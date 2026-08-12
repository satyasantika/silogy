<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Filament\Resources\MkResource\Pages\ListMks;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
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
        'nama' => 'Kurikulum Uji Reset MK',
        'kode' => Str::upper(Str::random(5)),
        'tahun' => 2026,
        'is_active' => true,
    ]);

    $this->actingAs($this->timkur);
    KurikulumTerpilih::set($this->kurikulum->id);
});

it('tombol buat tidak lagi ada, impor massal selalu tampil', function () {
    Livewire::test(ListMks::class)
        ->assertActionDoesNotExist('create')
        ->assertActionExists('bulkImport');
});

it('tombol reset aktif saat belum ada mk yang punya penawaran', function () {
    Mk::factory()->forKurikulum($this->kurikulum)->create();

    Livewire::test(ListMks::class)
        ->assertActionEnabled('resetData');
});

it('tombol reset nonaktif saat mk sudah punya penawaran', function () {
    $mk = Mk::factory()->forKurikulum($this->kurikulum)->create();
    MkUnit::factory()->forMk($mk)->forKurikulum($this->kurikulum)->create();

    Livewire::test(ListMks::class)
        ->assertActionDisabled('resetData');
});

it('reset menghapus seluruh mk kurikulum ini tanpa menyentuh kurikulum lain', function () {
    $mk = Mk::factory()->forKurikulum($this->kurikulum)->create();

    $kurikulumLain = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Lain',
        'kode' => Str::upper(Str::random(5)),
        'tahun' => 2025,
        'is_active' => false,
    ]);
    $mkLain = Mk::factory()->forKurikulum($kurikulumLain)->create();

    Livewire::test(ListMks::class)
        ->callAction('resetData');

    $this->assertDatabaseMissing('mk', ['id' => $mk->id]);
    $this->assertDatabaseHas('mk', ['id' => $mkLain->id]);
});
