<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Livewire\KurikulumTerpilihBanner;
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
        'nama' => 'Kurikulum Banner Modal',
        'tahun' => 2026,
        'is_active' => true,
    ]);
});

it('banner kurikulum membuka modal ganti dan menyimpan pilihan', function () {
    $timkur = User::where('username', 'timkur')->firstOrFail();
    $this->actingAs($timkur);
    KurikulumTerpilih::set($this->kurikulum->id);

    $kurikulumLain = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Banner Alternatif',
        'tahun' => 2027,
        'is_active' => false,
    ]);

    Livewire::test(KurikulumTerpilihBanner::class)
        ->assertSee('Kurikulum Banner Modal')
        ->assertSee('Ganti')
        ->callAction('ganti', data: [
            'kurikulum_id' => $kurikulumLain->id,
        ]);

    expect(KurikulumTerpilih::currentId())->toBe($kurikulumLain->id);
});
