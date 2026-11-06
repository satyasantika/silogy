<?php

use App\Models\User;
use App\Modules\CPL\Filament\Resources\CplResource\Pages\ListCpls;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Filament\Resources\ProfilLulusanResource\Pages\CreateProfilLulusan;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Models\ProfilLulusan;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
 * Impor massal dan Tambah manual tersedia berdampingan: data boleh masuk
 * sekaligus dari spreadsheet atau satu per satu lewat formulir.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);

    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();

    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Uji Input Manual',
        'kode' => Str::upper(Str::random(5)),
        'tahun' => 2026,
        'is_active' => true,
    ]);
});

it('tim kurikulum dapat membuat profil lulusan manual ke kurikulum terpilih', function () {
    $this->actingAs(User::query()->where('username', 'timkur')->firstOrFail());
    KurikulumTerpilih::set($this->kurikulum->id);

    Livewire::test(CreateProfilLulusan::class)
        ->assertFormSet(['kurikulum_id' => $this->kurikulum->id])
        ->fillForm([
            'kode' => 'PL-MAN-01',
            'nama' => 'Profil Manual',
            'deskripsi' => 'Pendidik matematika yang reflektif',
            'indikators' => [['nama' => 'Merancang pembelajaran bermakna']],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(ProfilLulusan::query()->where('kode', 'PL-MAN-01')->firstOrFail()->kurikulum_id)
        ->toBe($this->kurikulum->id);
});

it('daftar cpl merender tombol tambah bulat setara ukuran tombol impor massal', function () {
    $this->actingAs(User::query()->where('username', 'timkur')->firstOrFail());
    KurikulumTerpilih::set($this->kurikulum->id);

    $html = Livewire::test(ListCpls::class)->html();

    preg_match('/<(?:a|button)[^>]*silogy-tombol-bulat[^>]*>/', $html, $tambah);
    preg_match('/<button[^>]*wire:click="mountAction\(\'bulkImport\'[^>]*>/', $html, $impor);

    expect($tambah)->not->toBeEmpty()
        ->and($tambah[0])->toContain('aria-label="Tambah"')
        ->and($impor)->not->toBeEmpty();

    $ukuran = fn (string $tag): ?string => preg_match('/fi-size-\w+/', $tag, $m) ? $m[0] : null;

    expect($ukuran($tambah[0]))->toBe($ukuran($impor[0]));
});
