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
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkCpmk;
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
        'nama' => 'Kurikulum Penjaga Hapus',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    KurikulumTerpilih::set($this->kurikulum->id);
    $this->actingAs(User::query()->where('username', 'timkur')->firstOrFail());
});

function selDenganCpmk(object $test): array
{
    $mk = Mk::factory()->forKurikulum($test->kurikulum)->create(['nama' => 'MK Penjaga']);
    $cplBok = CplBok::query()->create([
        'cpl_id' => Cpl::factory()->forAcademicUnit($test->prodi)->create(['kode' => 'CPL-PJG'])->id,
        'bok_id' => Bok::factory()->forAcademicUnit($test->prodi)->create(['kode' => 'BOK-PJG'])->id,
    ]);
    $cplMk = CplMk::query()->create(['mk_id' => $mk->id, 'cpl_bok_id' => $cplBok->id, 'bobot' => 50]);

    return [$mk, $cplBok, $cplMk];
}

it('menolak pengosongan sel yang masih punya MkCpmk', function () {
    [$mk, $cplBok, $cplMk] = selDenganCpmk($this);
    $cpmk = Cpmk::factory()->forMk($mk)->create();
    MkCpmk::query()->create(['cpl_mk_id' => $cplMk->id, 'cpmk_id' => $cpmk->id, 'bobot' => 100]);

    Livewire::test(CplMkMatrix::class)->call('updateBobot', $mk->id, $cplBok->id, '');

    expect(CplMk::query()->whereKey($cplMk->id)->exists())->toBeTrue()
        ->and(MkCpmk::query()->where('cpl_mk_id', $cplMk->id)->exists())->toBeTrue();
});

it('menolak input nol dan non-numerik pada sel yang masih punya MkCpmk', function () {
    [$mk, $cplBok, $cplMk] = selDenganCpmk($this);
    $cpmk = Cpmk::factory()->forMk($mk)->create();
    MkCpmk::query()->create(['cpl_mk_id' => $cplMk->id, 'cpmk_id' => $cpmk->id, 'bobot' => 100]);

    Livewire::test(CplMkMatrix::class)
        ->call('updateBobot', $mk->id, $cplBok->id, '0')
        ->call('updateBobot', $mk->id, $cplBok->id, 'abc');

    expect(CplMk::query()->whereKey($cplMk->id)->exists())->toBeTrue()
        ->and(MkCpmk::query()->where('cpl_mk_id', $cplMk->id)->exists())->toBeTrue();
});

it('tetap menghapus sel kosong tanpa MkCpmk', function () {
    [$mk, $cplBok, $cplMk] = selDenganCpmk($this);

    Livewire::test(CplMkMatrix::class)->call('updateBobot', $mk->id, $cplBok->id, '');

    expect(CplMk::query()->whereKey($cplMk->id)->exists())->toBeFalse();
});

it('merender snapshot nilai tersimpan dan penyelaras input setelah penolakan', function () {
    [$mk, $cplBok] = selDenganCpmk($this);

    Livewire::test(CplMkMatrix::class)
        ->assertSeeHtml('data-rekap-snapshot=')
        ->assertSeeHtml('selaraskanInput()')
        ->assertSeeHtml('value="50"');
});
