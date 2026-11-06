<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Filament\Resources\MkResource\Pages\EditMk;
use App\Modules\MK\Filament\Resources\MkUnitResource\Pages\EditMkUnit;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

/*
 * Tombol Simpan adalah <button type="submit"> tanpa atribut form=, jadi
 * hanya berfungsi bila dirender DI DALAM <form id="form">. Halaman edit
 * MK dan Penawaran MK menimpa content() dan pernah menaruh tombol di luar
 * form — tombolnya tampil tapi tidak menyimpan apa pun. Livewire
 * call('save') tidak menangkap ini karena melewati DOM; karena itu yang
 * diuji di sini struktur HTML-nya.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SemesterSeeder::class);

    $prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $prodi->id,
        'nama' => 'Kurikulum Uji Tombol Simpan',
        'tahun' => 2026,
        'is_active' => true,
    ]);
    KurikulumTerpilih::set($this->kurikulum->id);

    $this->mk = Mk::factory()->forKurikulum($this->kurikulum)->create();
    $this->actingAs(User::query()->where('username', 'timkur')->firstOrFail());
});

function tombolSimpanDiDalamForm(string $html): bool
{
    $awal = strpos($html, '<form');
    $akhir = strpos($html, '</form>');

    if (! preg_match('/<button\b[^>]*type="submit"[^>]*wire:target="save"[^>]*>/s', $html, $m, PREG_OFFSET_CAPTURE)) {
        return false;
    }

    [$tag, $posisi] = $m[0];

    return str_contains($tag, 'form="form"')
        || ($awal !== false && $akhir !== false && $posisi > $awal && $posisi < $akhir);
}

it('tombol simpan di edit penawaran mk terikat ke form', function () {
    $mkUnit = MkUnit::factory()->forMk($this->mk)->forKurikulum($this->kurikulum)->create();

    $html = Livewire::test(EditMkUnit::class, ['record' => $mkUnit->id])->html();

    expect(tombolSimpanDiDalamForm($html))->toBeTrue();
});

it('tombol simpan di edit mk terikat ke form', function () {
    $html = Livewire::test(EditMk::class, ['record' => $this->mk->id])->html();

    expect(tombolSimpanDiDalamForm($html))->toBeTrue();
});

it('simpan edit penawaran mk berhasil tanpa galat validasi', function () {
    $mkUnit = MkUnit::factory()->forMk($this->mk)->forKurikulum($this->kurikulum)->create(['is_active' => true]);

    Livewire::test(EditMkUnit::class, ['record' => $mkUnit->id])
        ->fillForm(['is_active' => false])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect($mkUnit->fresh()->is_active)->toBeFalse();
});
