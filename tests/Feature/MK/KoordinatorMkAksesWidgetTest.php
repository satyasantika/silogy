<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\MK\Filament\Widgets\KoordinatorMkAksesWidget;
use App\Modules\MK\Models\Mk;
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
    $this->korma = User::where('username', 'korma')->firstOrFail();
});

it('menampilkan rekap jumlah mk, kurikulum, dan unit yang dikoordinasikan', function () {
    Mk::factory()->create(['academic_unit_id' => $this->prodi->id, 'koordinator_mk_id' => $this->korma->id]);
    Mk::factory()->create(['academic_unit_id' => $this->prodi->id, 'koordinator_mk_id' => $this->korma->id]);

    Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Uji Dashboard',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    $this->actingAs($this->korma);

    Livewire::test(KoordinatorMkAksesWidget::class)
        ->assertSee('2 mata kuliah', escape: false)
        ->assertSee('1 kurikulum', escape: false)
        ->assertSee($this->prodi->nama, escape: false)
        ->assertSee('Mata Kuliah Koordinator', escape: false)
        ->assertSee('Kelola Kelas MK', escape: false);
});

it('menampilkan keterangan belum ada mk dikoordinasikan bila belum ada penugasan', function () {
    $this->actingAs($this->korma);

    Livewire::test(KoordinatorMkAksesWidget::class)
        ->assertSee('belum menjadi koordinator', escape: false);
});

it('widget hanya tampil untuk role koordinator mata kuliah', function () {
    $this->actingAs($this->korma);
    expect(KoordinatorMkAksesWidget::canView())->toBeTrue();

    $superadmin = User::where('username', 'superadmin')->firstOrFail();
    $this->actingAs($superadmin);
    expect(KoordinatorMkAksesWidget::canView())->toBeFalse();
});
