<?php

use App\Models\User;
use App\Modules\Auth\Support\ActiveRole;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Filament\Resources\KurikulumResource;
use App\Modules\Kurikulum\Filament\Widgets\KurikulumTerpilihWidget;
use App\Modules\Kurikulum\Models\Kurikulum;
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
        'nama' => 'Kurikulum Widget Uji',
        'tahun' => 2026,
        'is_active' => true,
    ]);
});

it('admin prodi (tanpa penugasan tim kurikulum) melihat kartu kurikulum tunggal dengan tombol kelola kelas', function () {
    $adminProdi = User::where('username', 'adminprodi')->firstOrFail();
    $this->actingAs($adminProdi);

    Livewire::test(KurikulumTerpilihWidget::class)
        ->assertSee('Kurikulum Widget Uji', escape: false)
        ->assertSee('Kelola Kelas MK', escape: false)
        ->assertDontSee('Kelola kurikulum', escape: false);
});

it('tim kurikulum sungguhan tetap melihat dropdown pilih-kurikulum dan tombol kelola kurikulum', function () {
    $timKurikulum = User::where('username', 'timkur')->firstOrFail();
    $this->actingAs($timKurikulum);

    Livewire::test(KurikulumTerpilihWidget::class)
        ->assertSee('Kelola kurikulum', escape: false)
        ->assertDontSee('Kelola Kelas MK', escape: false)
        ->assertSeeHtml(KurikulumResource::getUrl('index'));
});

it('dropdown dashboard tim kurikulum hanya menampilkan kurikulum aktif', function () {
    $timKurikulum = User::where('username', 'timkur')->firstOrFail();
    $this->actingAs($timKurikulum);

    $kurikulumNonAktif = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Draft Nonaktif',
        'tahun' => 2025,
        'is_active' => false,
    ]);

    KurikulumTerpilih::set($kurikulumNonAktif->id);

    $component = Livewire::test(KurikulumTerpilihWidget::class);

    expect(KurikulumTerpilih::options(hanyaAktif: true))
        ->toHaveKey($this->kurikulum->id)
        ->not->toHaveKey($kurikulumNonAktif->id)
        ->and(KurikulumTerpilih::currentId())->toBe($this->kurikulum->id);

    $component
        ->assertSee('Kurikulum Widget Uji', escape: false)
        ->assertDontSee('Kurikulum Draft Nonaktif', escape: false);
});

it('banner kurikulum terpilih merender komponen livewire modal ganti', function () {
    $adminProdi = User::where('username', 'adminprodi')->firstOrFail();
    $this->actingAs($adminProdi);
    KurikulumTerpilih::set($this->kurikulum->id);

    $html = KurikulumTerpilih::bannerHtml()->toHtml();

    expect($html)->not->toContain(KurikulumResource::getUrl('index'))
        ->and(
            str_contains($html, 'wire:') || str_contains($html, 'Kurikulum terpilih'),
        )->toBeTrue();
});

it('widget kurikulum hanya tampil saat role aktif Tim Kurikulum/Admin', function () {
    $dosenTimkur = User::where('username', 'dosentimkur')->firstOrFail();
    $this->actingAs($dosenTimkur);

    ActiveRole::set('Dosen Pengampu');
    expect(KurikulumTerpilihWidget::canView())->toBeFalse();

    ActiveRole::set('Tim Kurikulum');
    expect(KurikulumTerpilihWidget::canView())->toBeTrue();
});
