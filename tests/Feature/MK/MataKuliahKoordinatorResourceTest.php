<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Filament\Resources\MataKuliahKoordinatorResource;
use App\Modules\MK\Filament\Resources\MataKuliahKoordinatorResource\Pages\ListMataKuliahKoordinators;
use App\Modules\MK\Filament\Resources\MkUnitResource;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Services\MataKuliahKoordinatorService;
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
    $this->korma = User::query()->where('username', 'korma')->firstOrFail();
    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Korma MK',
        'tahun' => 2026,
        'is_active' => true,
    ]);
});

it('koordinator mk melihat menu mata kuliah di kategori mata kuliah bukan penawaran mk', function () {
    Mk::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'koordinator_mk_id' => $this->korma->id,
    ]);

    $this->actingAs($this->korma);

    expect(MataKuliahKoordinatorResource::shouldRegisterNavigation())->toBeTrue()
        ->and(MkUnitResource::shouldRegisterNavigation())->toBeFalse();
});

it('halaman mata kuliah koordinator menampilkan card mk pada kurikulum terpilih', function () {
    $mkKorma = Mk::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Algoritma Koordinator',
        'koordinator_mk_id' => $this->korma->id,
    ]);
    MkUnit::factory()->forMk($mkKorma)->forAcademicUnit($this->prodi)->create(['kode' => 'KOR701']);

    $mkLain = Mk::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'MK Orang Lain',
    ]);
    MkUnit::factory()->forMk($mkLain)->forAcademicUnit($this->prodi)->create(['kode' => 'KOR702']);

    Cpmk::query()->create([
        'mk_id' => $mkKorma->id,
        'kode' => 'CPMK-01',
        'deskripsi' => 'Mahasiswa mampu merancang algoritma.',
    ]);

    KurikulumTerpilih::set($this->kurikulum->id);
    $this->actingAs($this->korma);

    Livewire::test(ListMataKuliahKoordinators::class)
        ->loadTable()
        ->assertSee('Algoritma Koordinator', escape: false)
        ->assertSee('KOR701', escape: false)
        ->assertSee('CPMK · Ada', escape: false)
        ->assertSee('Sub-CPMK · Belum', escape: false)
        ->assertSee('Asesmen · Belum', escape: false)
        ->assertDontSee('CPL↔CPMK', escape: false)
        ->assertDontSee('MK Orang Lain', escape: false);
});

it('service ketersediaan penilaian mendeteksi cpmk subcpmk dan asesmen', function () {
    $mk = Mk::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'koordinator_mk_id' => $this->korma->id,
    ]);

    Cpmk::query()->create([
        'mk_id' => $mk->id,
        'kode' => 'CPMK-X',
        'deskripsi' => 'Uji ketersediaan.',
    ]);

    $ketersediaan = MataKuliahKoordinatorService::ketersediaanPenilaian($mk, $this->korma);

    expect($ketersediaan['cpmk'])->toBeTrue()
        ->and($ketersediaan['subcpmk'])->toBeFalse()
        ->and($ketersediaan['asesmen'])->toBeFalse();
});
