<?php

use App\Models\User;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplProfilLulusan;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Filament\Resources\ProfilLulusanResource\Pages\ListProfilLulusans;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Models\ProfilLulusan;
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
        'nama' => 'Kurikulum Uji Reset Profil',
        'kode' => Str::upper(Str::random(5)),
        'tahun' => 2026,
        'is_active' => true,
    ]);

    $this->actingAs($this->timkur);
    KurikulumTerpilih::set($this->kurikulum->id);
});

it('tombol buat tidak lagi ada, impor massal selalu tampil tanpa syarat sudah ada data', function () {
    Livewire::test(ListProfilLulusans::class)
        ->assertActionDoesNotExist('create')
        ->assertActionExists('bulkImport');
});

it('tombol reset aktif saat belum ada profil lulusan yang dipetakan ke cpl', function () {
    ProfilLulusan::query()->create([
        'kurikulum_id' => $this->kurikulum->id,
        'kode' => 'PL01',
        'nama' => 'Profil Lulusan Uji',
        'deskripsi' => 'Deskripsi.',
    ]);

    Livewire::test(ListProfilLulusans::class)
        ->assertActionEnabled('resetData');
});

it('tombol reset nonaktif saat profil lulusan sudah dipetakan ke cpl', function () {
    $profil = ProfilLulusan::query()->create([
        'kurikulum_id' => $this->kurikulum->id,
        'kode' => 'PL01',
        'nama' => 'Profil Lulusan Terpakai',
        'deskripsi' => 'Deskripsi.',
    ]);
    $cpl = Cpl::factory()->forKurikulum($this->kurikulum)->create();
    CplProfilLulusan::query()->create(['cpl_id' => $cpl->id, 'profil_lulusan_id' => $profil->id]);

    Livewire::test(ListProfilLulusans::class)
        ->assertActionDisabled('resetData');
});

it('reset menghapus seluruh profil lulusan kurikulum ini tanpa menyentuh kurikulum lain', function () {
    $profil = ProfilLulusan::query()->create([
        'kurikulum_id' => $this->kurikulum->id,
        'kode' => 'PL01',
        'nama' => 'Profil A',
        'deskripsi' => 'Deskripsi.',
    ]);

    $kurikulumLain = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Lain',
        'kode' => Str::upper(Str::random(5)),
        'tahun' => 2025,
        'is_active' => false,
    ]);
    $profilLain = ProfilLulusan::query()->create([
        'kurikulum_id' => $kurikulumLain->id,
        'kode' => 'PL01',
        'nama' => 'Profil Kurikulum Lain',
        'deskripsi' => 'Deskripsi.',
    ]);

    Livewire::test(ListProfilLulusans::class)
        ->callAction('resetData');

    $this->assertDatabaseMissing('profil_lulusan', ['id' => $profil->id]);
    $this->assertDatabaseHas('profil_lulusan', ['id' => $profilLain->id]);
});
