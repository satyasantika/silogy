<?php

use App\Models\User;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Filament\Pages\CplMkMatrix;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
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
    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Normalisasi Semua',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    KurikulumTerpilih::set($this->kurikulum->id);
    $this->actingAs(User::query()->where('username', 'timkur')->firstOrFail());
});

it('tombol normalisasi semua menjadikan total setiap mk di kurikulum tepat 100', function () {
    $cplBokA = CplBok::query()->create([
        'cpl_id' => Cpl::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'CPL-A'])->id,
        'bok_id' => Bok::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'BOK-A'])->id,
    ]);
    $cplBokB = CplBok::query()->create([
        'cpl_id' => Cpl::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'CPL-B'])->id,
        'bok_id' => Bok::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'BOK-B'])->id,
    ]);

    $mk1 = Mk::factory()->forKurikulum($this->kurikulum)->create(['nama' => 'MK Satu']);
    $mk2 = Mk::factory()->forKurikulum($this->kurikulum)->create(['nama' => 'MK Dua']);
    $kurikulumLain = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Lama',
        'tahun' => 2020,
        'is_active' => false,
    ]);
    $mkLuar = Mk::factory()->forKurikulum($kurikulumLain)->create(['nama' => 'MK Kurikulum Lain']);

    CplMk::query()->create(['cpl_bok_id' => $cplBokA->id, 'mk_id' => $mk1->id, 'bobot' => 10]);
    CplMk::query()->create(['cpl_bok_id' => $cplBokB->id, 'mk_id' => $mk1->id, 'bobot' => 30]);
    CplMk::query()->create(['cpl_bok_id' => $cplBokA->id, 'mk_id' => $mk2->id, 'bobot' => 20]);
    $luar = CplMk::query()->create(['cpl_bok_id' => $cplBokA->id, 'mk_id' => $mkLuar->id, 'bobot' => 20]);

    Livewire::test(CplMkMatrix::class)
        ->assertActionVisible('normalisasiSemuaCplMk')
        ->callAction('normalisasiSemuaCplMk', ['desimal' => 0])
        ->assertHasNoFormErrors()
        ->assertNotified('2 MK berhasil dinormalisasi');

    expect((float) CplMk::query()->where('mk_id', $mk1->id)->sum('bobot'))->toBe(100.0)
        ->and((float) CplMk::query()->where('mk_id', $mk2->id)->sum('bobot'))->toBe(100.0)
        ->and((float) $luar->fresh()->bobot)->toBe(20.0);
});
