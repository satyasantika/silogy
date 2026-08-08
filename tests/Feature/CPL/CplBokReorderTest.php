<?php

use App\Models\User;
use App\Modules\BoK\Filament\Resources\BokResource\Pages\ListBoks;
use App\Modules\BoK\Models\Bok;
use App\Modules\BoK\Models\BokKodeOverride;
use App\Modules\CPL\Filament\Resources\CplResource\Pages\ListCpls;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplKodeOverride;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Models\MkUnit;
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

    $this->univ = AcademicUnit::query()->where('type', 'university')->firstOrFail();
    $this->fak = AcademicUnit::query()->where('type', 'faculty')->firstOrFail();
    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->actingAs(User::query()->where('username', 'timkur')->firstOrFail());
});

it('reorder cpl milik sendiri tidak mempengaruhi kurikulum lain', function () {
    $kurikulumA = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum A',
        'tahun' => 2026,
        'is_active' => true,
    ]);
    $c1 = Cpl::factory()->forKurikulum($kurikulumA)->create(['urutan' => 1]);
    $c2 = Cpl::factory()->forKurikulum($kurikulumA)->create(['urutan' => 2]);

    KurikulumTerpilih::set($kurikulumA->id);

    Livewire::test(ListCpls::class)->call('reorderTable', [$c2->id, $c1->id]);

    expect($c1->fresh()->urutan)->toBe(2)
        ->and($c2->fresh()->urutan)->toBe(1);
});

it('reorder bok milik sendiri tidak mempengaruhi kurikulum lain', function () {
    $kurikulumA = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum A',
        'tahun' => 2026,
        'is_active' => true,
    ]);
    $b1 = Bok::factory()->forKurikulum($kurikulumA)->create(['urutan' => 1]);
    $b2 = Bok::factory()->forKurikulum($kurikulumA)->create(['urutan' => 2]);

    KurikulumTerpilih::set($kurikulumA->id);

    Livewire::test(ListBoks::class)->call('reorderTable', [$b2->id, $b1->id]);

    expect($b1->fresh()->urutan)->toBe(2)
        ->and($b2->fresh()->urutan)->toBe(1);
});

it('reorder cpl adaptasi tidak mengubah kode/urutan milik unit pemilik, tersimpan sebagai override', function () {
    $adaptasi = siapkanAdaptasiCplBokUniv($this);
    $kurikulumProdi = KurikulumTerpilih::current();
    $kodeAsli = $adaptasi['cpl']->kode;

    $cplProdi = Cpl::factory()->forKurikulum($kurikulumProdi)->create(['urutan' => 1]);

    Livewire::test(ListCpls::class)
        ->call('reorderTable', [$adaptasi['cpl']->id, $cplProdi->id]);

    expect($adaptasi['cpl']->fresh()->kode)->toBe($kodeAsli)
        ->and($adaptasi['cpl']->fresh()->urutan)->toBeNull()
        ->and(CplKodeOverride::query()
            ->where('academic_unit_id', $this->prodi->id)
            ->where('cpl_id', $adaptasi['cpl']->id)
            ->value('urutan'))->toBe(1)
        ->and($cplProdi->fresh()->urutan)->toBe(2);
});

it('reorder bok adaptasi tidak mengubah kode/urutan milik unit pemilik, tersimpan sebagai override', function () {
    $adaptasi = siapkanAdaptasiCplBokUniv($this);
    $kurikulumProdi = KurikulumTerpilih::current();
    $kodeAsli = $adaptasi['bok']->kode;

    $bokProdi = Bok::factory()->forKurikulum($kurikulumProdi)->create(['urutan' => 1]);

    Livewire::test(ListBoks::class)
        ->call('reorderTable', [$adaptasi['bok']->id, $bokProdi->id]);

    expect($adaptasi['bok']->fresh()->kode)->toBe($kodeAsli)
        ->and($adaptasi['bok']->fresh()->urutan)->toBeNull()
        ->and(BokKodeOverride::query()
            ->where('academic_unit_id', $this->prodi->id)
            ->where('bok_id', $adaptasi['bok']->id)
            ->value('urutan'))->toBe(1)
        ->and($bokProdi->fresh()->urutan)->toBe(2);
});

it('urutan adaptasi prodi a tidak mempengaruhi urutan prodi b untuk cpl yang sama', function () {
    $adaptasi = siapkanAdaptasiCplBokUniv($this);
    $kurikulumA = KurikulumTerpilih::current();

    $prodiB = AcademicUnit::factory()->studyProgram($this->fak)->create([
        'nama' => 'Program Studi B',
        'code' => '2152',
        'kode_pddikti' => '84203',
    ]);
    $kurikulumB = Kurikulum::query()->create([
        'academic_unit_id' => $prodiB->id,
        'nama' => 'Kurikulum B',
        'tahun' => 2026,
        'is_active' => true,
    ]);
    MkUnit::factory()->forAcademicUnit($prodiB)->forMk($adaptasi['mk'])->create(['is_active' => true]);

    $overrideB = CplKodeOverride::query()->create([
        'academic_unit_id' => $prodiB->id,
        'cpl_id' => $adaptasi['cpl']->id,
        'kode' => $adaptasi['cpl']->kode,
        'urutan' => 5,
    ]);

    $cplProdiA = Cpl::factory()->forKurikulum($kurikulumA)->create(['urutan' => 1]);

    Livewire::test(ListCpls::class)
        ->call('reorderTable', [$adaptasi['cpl']->id, $cplProdiA->id]);

    expect($overrideB->fresh()->urutan)->toBe(5)
        ->and($kurikulumB->id)->not->toBe($kurikulumA->id);
});
