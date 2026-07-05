<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Filament\Resources\ProfilLulusanResource\Pages\EditProfilLulusan;
use App\Modules\Kurikulum\Filament\Resources\ProfilLulusanResource\Pages\ListProfilLulusans;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Models\ProfilIndikator;
use App\Modules\Kurikulum\Models\ProfilLulusan;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
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

    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Indikator Uji',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    $this->profil = ProfilLulusan::query()->create([
        'kurikulum_id' => $this->kurikulum->id,
        'kode' => 'PL-IND',
        'nama' => 'Profil uji indikator',
        'deskripsi' => '<p>Deskripsi profil</p>',
        'urutan' => 1,
    ]);

    $this->actingAs(User::query()->where('username', 'timkur')->firstOrFail());
    KurikulumTerpilih::set($this->kurikulum->id);
});

it('impor indikator massal dari halaman edit profil lulusan', function () {
    Livewire::test(EditProfilLulusan::class, ['record' => $this->profil->id])
        ->callAction('bulkImport', [
            'rows' => "Mampu berpikir kritis\nMampu berkomunikasi",
            'mode_duplikat' => 'lewati',
        ]);

    $indikators = ProfilIndikator::query()
        ->where('profil_id', $this->profil->id)
        ->orderBy('urutan')
        ->get();

    expect($indikators)->toHaveCount(2)
        ->and($indikators[0]->nama)->toBe('Mampu berpikir kritis')
        ->and($indikators[0]->deskripsi)->toBeNull()
        ->and($indikators[0]->urutan)->toBe(1)
        ->and($indikators[1]->nama)->toBe('Mampu berkomunikasi')
        ->and($indikators[1]->urutan)->toBe(2);
});

it('impor indikator menolak duplikat nama pada profil yang sama', function () {
    ProfilIndikator::query()->create([
        'profil_id' => $this->profil->id,
        'nama' => 'Indikator sudah ada',
        'urutan' => 1,
    ]);

    Livewire::test(EditProfilLulusan::class, ['record' => $this->profil->id])
        ->callAction('bulkImport', [
            'rows' => 'Indikator sudah ada',
            'mode_duplikat' => 'lewati',
        ]);

    expect(ProfilIndikator::query()->where('profil_id', $this->profil->id)->count())->toBe(1);
});

it('form edit profil lulusan menyimpan urutan indikator setelah diubah', function () {
    $indikatorA = ProfilIndikator::query()->create([
        'profil_id' => $this->profil->id,
        'nama' => 'Indikator A',
        'urutan' => 1,
    ]);

    $indikatorB = ProfilIndikator::query()->create([
        'profil_id' => $this->profil->id,
        'nama' => 'Indikator B',
        'urutan' => 2,
    ]);

    Livewire::test(EditProfilLulusan::class, ['record' => $this->profil->id])
        ->set('data.indikators', [
            "record-{$indikatorB->id}" => [
                'nama' => 'Indikator B',
            ],
            "record-{$indikatorA->id}" => [
                'nama' => 'Indikator A',
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(ProfilIndikator::query()->find($indikatorB->id)?->urutan)->toBe(1)
        ->and(ProfilIndikator::query()->find($indikatorA->id)?->urutan)->toBe(2);
});

it('daftar profil lulusan menampilkan indikator pada setiap card', function () {
    ProfilIndikator::query()->create([
        'profil_id' => $this->profil->id,
        'nama' => 'Indikator pertama',
        'urutan' => 1,
    ]);

    ProfilIndikator::query()->create([
        'profil_id' => $this->profil->id,
        'nama' => 'Indikator kedua',
        'urutan' => 2,
    ]);

    Livewire::test(ListProfilLulusans::class)
        ->loadTable()
        ->assertSee('Profil uji indikator')
        ->assertSee('1. Indikator pertama')
        ->assertSee('2. Indikator kedua');
});
