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
        'nama' => 'Kurikulum Kuota CPL-MK',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    KurikulumTerpilih::set($this->kurikulum->id);
    $this->actingAs(User::query()->where('username', 'timkur')->firstOrFail());
});

it('kuota penuh mengunci sel CPL kosong hingga bobot terisi diturunkan', function () {
    $mk = Mk::factory()->forKurikulum($this->kurikulum)->create(['nama' => 'MK Kuota Penuh']);

    $cplBokIsi = CplBok::query()->create([
        'cpl_id' => Cpl::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'CPL-ISI'])->id,
        'bok_id' => Bok::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'BOK-ISI'])->id,
    ]);
    $cplBokKosong = CplBok::query()->create([
        'cpl_id' => Cpl::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'CPL-KOSONG'])->id,
        'bok_id' => Bok::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'BOK-KOSONG'])->id,
    ]);

    $page = Livewire::test(CplMkMatrix::class)
        ->call('updateBobot', $mk->id, $cplBokIsi->id, '100');

    expect((float) CplMk::query()->where('mk_id', $mk->id)->sum('bobot'))->toBe(100.0);

    $html = $page->html();

    expect($html)
        ->toContain('bobot-input-'.$mk->id.'-'.$cplBokIsi->id.'-100')
        ->toContain('data-cplbok="'.$cplBokKosong->id.'"')
        ->toContain('data-terkunci="1"')
        ->not->toContain('Kuota 100% penuh');

    $page->call('updateBobot', $mk->id, $cplBokKosong->id, '20');

    expect(CplMk::query()->where('mk_id', $mk->id)->where('cpl_bok_id', $cplBokKosong->id)->exists())->toBeFalse()
        ->and((float) CplMk::query()->where('mk_id', $mk->id)->sum('bobot'))->toBe(100.0);

    $page->call('updateBobot', $mk->id, $cplBokIsi->id, '70');
    $htmlSetelah = $page->html();

    expect($htmlSetelah)
        ->toContain('bobot-input-'.$mk->id.'-'.$cplBokIsi->id.'-70')
        ->not->toContain('data-terkunci="1"');

    $page->call('updateBobot', $mk->id, $cplBokKosong->id, '20');

    expect((float) CplMk::query()->where('mk_id', $mk->id)->where('cpl_bok_id', $cplBokKosong->id)->value('bobot'))
        ->toBe(20.0)
        ->and((float) CplMk::query()->where('mk_id', $mk->id)->sum('bobot'))->toBe(90.0);
});

it('updateBobot memotong kelebihan agar total baris mk tidak melebihi 100', function () {
    $mk = Mk::factory()->forKurikulum($this->kurikulum)->create(['nama' => 'MK Cap 100']);

    $cplBokA = CplBok::query()->create([
        'cpl_id' => Cpl::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'CPL-A'])->id,
        'bok_id' => Bok::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'BOK-A'])->id,
    ]);
    $cplBokB = CplBok::query()->create([
        'cpl_id' => Cpl::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'CPL-B'])->id,
        'bok_id' => Bok::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'BOK-B'])->id,
    ]);

    Livewire::test(CplMkMatrix::class)
        ->call('updateBobot', $mk->id, $cplBokA->id, '70')
        ->call('updateBobot', $mk->id, $cplBokB->id, '50');

    expect((float) CplMk::query()->where('mk_id', $mk->id)->where('cpl_bok_id', $cplBokB->id)->value('bobot'))
        ->toBe(30.0)
        ->and((float) CplMk::query()->where('mk_id', $mk->id)->sum('bobot'))->toBe(100.0);
});

it('rekap 99,9 tidak success dan menampilkan normalisasi', function () {
    $mk = Mk::factory()->forKurikulum($this->kurikulum)->create(['nama' => 'MK Hampir Penuh']);

    $cplBokIsi = CplBok::query()->create([
        'cpl_id' => Cpl::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'CPL-99'])->id,
        'bok_id' => Bok::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'BOK-99'])->id,
    ]);
    $cplBokKosong = CplBok::query()->create([
        'cpl_id' => Cpl::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'CPL-SISA'])->id,
        'bok_id' => Bok::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'BOK-SISA'])->id,
    ]);

    $html = Livewire::test(CplMkMatrix::class)
        ->call('updateBobot', $mk->id, $cplBokIsi->id, '99.9')
        ->html();

    expect(CplMk::rekapPas(99.9))->toBeFalse()
        ->and($html)
        ->toContain('Σ 99,9%')
        ->toContain('background:#d97706')
        ->not->toContain('data-terkunci="1"')
        ->toContain('data-cplbok="'.$cplBokKosong->id.'"')
        ->toContain('Normalisasi')
        ->not->toMatch('/data-silogy="normalisasi-cpl-mk"[^>]*display:none/');
});

it('updateBobot membulatkan ke satu desimal', function () {
    $mk = Mk::factory()->forKurikulum($this->kurikulum)->create(['nama' => 'MK Satu Desimal']);

    $cplBok = CplBok::query()->create([
        'cpl_id' => Cpl::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'CPL-D1'])->id,
        'bok_id' => Bok::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'BOK-D1'])->id,
    ]);

    Livewire::test(CplMkMatrix::class)
        ->call('updateBobot', $mk->id, $cplBok->id, '33.33');

    expect((float) CplMk::query()->where('mk_id', $mk->id)->where('cpl_bok_id', $cplBok->id)->value('bobot'))
        ->toBe(33.3);
});

it('menaikkan cpl pertama 0,1 pada sebaran 33,30/33,33/33,34 mengubah rekap menjadi 100', function () {
    $mk = Mk::factory()->forKurikulum($this->kurikulum)->create(['nama' => 'Pendidikan Anti Korupsi Uji']);

    $cplBok1 = CplBok::query()->create([
        'cpl_id' => Cpl::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'CPL01'])->id,
        'bok_id' => Bok::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'BOK01'])->id,
    ]);
    $cplBok2 = CplBok::query()->create([
        'cpl_id' => Cpl::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'CPL02'])->id,
        'bok_id' => Bok::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'BOK02'])->id,
    ]);
    $cplBok3 = CplBok::query()->create([
        'cpl_id' => Cpl::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'CPL03'])->id,
        'bok_id' => Bok::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'BOK03'])->id,
    ]);

    CplMk::query()->create(['mk_id' => $mk->id, 'cpl_bok_id' => $cplBok1->id, 'bobot' => 33.30]);
    CplMk::query()->create(['mk_id' => $mk->id, 'cpl_bok_id' => $cplBok2->id, 'bobot' => 33.33]);
    CplMk::query()->create(['mk_id' => $mk->id, 'cpl_bok_id' => $cplBok3->id, 'bobot' => 33.34]);

    $htmlAwal = Livewire::test(CplMkMatrix::class)->html();
    expect($htmlAwal)
        ->toContain('Σ 99,9%')
        ->toContain('background:#d97706');

    $html = Livewire::test(CplMkMatrix::class)
        ->call('updateBobot', $mk->id, $cplBok1->id, '33.4')
        ->html();

    expect((float) CplMk::query()->where('mk_id', $mk->id)->where('cpl_bok_id', $cplBok1->id)->value('bobot'))
        ->toBe(33.4)
        ->and(CplMk::jumlahBobot(CplMk::query()->where('mk_id', $mk->id)->pluck('bobot')))->toBe(100.0)
        ->and($html)
        ->toContain('Σ 100%')
        ->toContain('background:#16a34a');
});
