<?php

use App\Models\User;
use App\Modules\BoK\Filament\Resources\BokResource\Pages\EditBok;
use App\Modules\BoK\Models\Bok;
use App\Modules\BoK\Models\BokKodeOverride;
use App\Modules\CPL\Filament\Resources\CplResource\Pages\EditCpl;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplKodeOverride;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Models\Mk;
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
    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->actingAs(User::query()->where('username', 'timkur')->firstOrFail());
});

it('tombol hapus tampil di edit cpl bila belum diinteraksikan', function () {
    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create();

    Livewire::test(EditCpl::class, ['record' => $cpl->getKey()])
        ->assertActionVisible('delete');
});

it('tombol hapus tersembunyi di edit cpl bila sudah dipetakan ke bok', function () {
    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create();
    $bok = Bok::factory()->forAcademicUnit($this->prodi)->create();

    CplBok::query()->create([
        'cpl_id' => $cpl->id,
        'bok_id' => $bok->id,
    ]);

    Livewire::test(EditCpl::class, ['record' => $cpl->getKey()])
        ->assertActionHidden('delete');
});

it('tombol hapus tampil di edit bok bila belum diinteraksikan', function () {
    $bok = Bok::factory()->forAcademicUnit($this->prodi)->create();

    Livewire::test(EditBok::class, ['record' => $bok->getKey()])
        ->assertActionVisible('delete');
});

it('tombol hapus tersembunyi di edit bok bila sudah dipetakan ke cpl', function () {
    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create();
    $bok = Bok::factory()->forAcademicUnit($this->prodi)->create();

    CplBok::query()->create([
        'cpl_id' => $cpl->id,
        'bok_id' => $bok->id,
    ]);

    Livewire::test(EditBok::class, ['record' => $bok->getKey()])
        ->assertActionHidden('delete');
});

/**
 * @return array{mk: Mk, cpl: Cpl, bok: Bok}
 */
function siapkanAdaptasiCplBokUniv(object $context): array
{
    $kurikulumProdi = Kurikulum::query()->create([
        'academic_unit_id' => $context->prodi->id,
        'nama' => 'Kurikulum Uji Adaptasi CPL/BoK',
        'tahun' => 2026,
        'is_active' => true,
    ]);
    KurikulumTerpilih::set($kurikulumProdi->id);

    $mkUniv = Mk::factory()->forAcademicUnit($context->univ)->create();
    $cplUniv = Cpl::factory()->forAcademicUnit($context->univ)->create();
    $bokUniv = Bok::factory()->forAcademicUnit($context->univ)->create();
    $cplBokUniv = CplBok::query()->create(['cpl_id' => $cplUniv->id, 'bok_id' => $bokUniv->id]);
    CplMk::query()->create(['cpl_bok_id' => $cplBokUniv->id, 'mk_id' => $mkUniv->id, 'bobot' => 60]);

    MkUnit::factory()->forAcademicUnit($context->prodi)->forMk($mkUniv)->create(['is_active' => true]);

    return ['mk' => $mkUniv, 'cpl' => $cplUniv, 'bok' => $bokUniv];
}

it('edit cpl adaptasi: field asli terkunci, kode alias tersimpan tanpa mengubah kode asli, tombol hapus tersembunyi', function () {
    $adaptasi = siapkanAdaptasiCplBokUniv($this);

    Livewire::test(EditCpl::class, ['record' => $adaptasi['cpl']->getKey()])
        ->assertFormFieldIsDisabled('kode')
        ->assertFormFieldIsDisabled('deskripsi')
        ->assertFormFieldIsDisabled('domain')
        ->assertFormFieldExists('kode_override')
        ->assertActionHidden('delete')
        ->fillForm(['kode_override' => 'CPL-PRODI-1'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($adaptasi['cpl']->fresh()->kode)->toBe($adaptasi['cpl']->kode)
        ->and(CplKodeOverride::query()
            ->where('academic_unit_id', $this->prodi->id)
            ->where('cpl_id', $adaptasi['cpl']->id)
            ->value('kode'))->toBe('CPL-PRODI-1');
});

it('edit bok adaptasi: field asli terkunci, kode alias tersimpan tanpa mengubah kode asli, tombol hapus tersembunyi', function () {
    $adaptasi = siapkanAdaptasiCplBokUniv($this);
    $kodeAsli = $adaptasi['bok']->kode;

    Livewire::test(EditBok::class, ['record' => $adaptasi['bok']->getKey()])
        ->assertFormFieldIsDisabled('kode')
        ->assertFormFieldIsDisabled('nama')
        ->assertFormFieldExists('kode_override')
        ->assertActionHidden('delete')
        ->fillForm(['kode_override' => 'BOK-PRODI-1'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($adaptasi['bok']->fresh()->kode)->toBe($kodeAsli)
        ->and(BokKodeOverride::query()
            ->where('academic_unit_id', $this->prodi->id)
            ->where('bok_id', $adaptasi['bok']->id)
            ->value('kode'))->toBe('BOK-PRODI-1');
});

it('cpl asing yang tidak teradaptasi sama sekali tidak bisa ditemukan lewat halaman edit', function () {
    $kurikulumProdi = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Uji Tanpa Adaptasi',
        'tahun' => 2026,
        'is_active' => true,
    ]);
    KurikulumTerpilih::set($kurikulumProdi->id);

    $cplUnivTanpaAdaptasi = Cpl::factory()->forAcademicUnit($this->univ)->create();

    Livewire::test(EditCpl::class, ['record' => $cplUnivTanpaAdaptasi->getKey()]);
})->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
