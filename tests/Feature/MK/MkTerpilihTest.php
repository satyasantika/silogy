<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Filament\Resources\CpmkResource;
use App\Modules\MK\Filament\Resources\CpmkResource\Pages\ListCpmks;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Support\MkTerpilih;
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
        'nama' => 'Kurikulum MK Terpilih',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    KurikulumTerpilih::set($this->kurikulum->id);
    $this->actingAs($this->korma);
});

it('navigasi mk menyimpan mk terpilih dan mengarah ke halaman cpmk', function () {
    $mk = Mk::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'koordinator_mk_id' => $this->korma->id,
    ]);
    MkUnit::factory()->forMk($mk)->forAcademicUnit($this->prodi)->create(['kode' => 'NAV101']);

    $response = $this->get(route('silogy.mk-navigasi', [
        'mk' => $mk->id,
        'menu' => 'cpmk',
    ]));

    $response->assertRedirect(CpmkResource::getUrl('index'));
    expect(MkTerpilih::currentId())->toBe($mk->id);
});

it('navigasi badge mk berhasil meski tanpa penawaran aktif pada kurikulum terpilih', function () {
    $mk = Mk::factory()->forKurikulum($this->kurikulum)->create([
        'academic_unit_id' => $this->prodi->id,
        'koordinator_mk_id' => $this->korma->id,
    ]);

    // Tanpa mk_units — sebelumnya controller meng-404 karena gate penawaran.
    foreach (['cpmk', 'subcpmk', 'asesmen', 'mahasiswa'] as $menu) {
        $this->get(route('silogy.mk-navigasi', [
            'mk' => $mk->id,
            'menu' => $menu,
        ]))->assertRedirect();
    }

    expect(MkTerpilih::currentId())->toBe($mk->id)
        ->and(KurikulumTerpilih::currentId())->toBe($this->kurikulum->id);
});

it('menu cpmk tersembunyi bila mk belum dipilih', function () {
    Mk::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'koordinator_mk_id' => $this->korma->id,
    ]);

    expect(CpmkResource::shouldRegisterNavigation())->toBeFalse();
});

it('menu cpmk tampil setelah mk dipilih', function () {
    $mk = Mk::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'koordinator_mk_id' => $this->korma->id,
    ]);
    MkUnit::factory()->forMk($mk)->forAcademicUnit($this->prodi)->create(['kode' => 'NAV102']);

    MkTerpilih::set($mk->id);

    expect(CpmkResource::shouldRegisterNavigation())->toBeTrue();
});

it('mk terpilih tetap valid meski penawaran mk tidak aktif', function () {
    $mk = Mk::factory()->forKurikulum($this->kurikulum)->create([
        'academic_unit_id' => $this->prodi->id,
        'koordinator_mk_id' => $this->korma->id,
    ]);
    MkUnit::factory()->forMk($mk)->forKurikulum($this->kurikulum)->create([
        'kode' => 'NAV103',
        'is_active' => true,
    ]);

    MkTerpilih::set($mk->id);

    MkUnit::query()->where('mk_id', $mk->id)->update(['is_active' => false]);

    expect(MkTerpilih::currentId())->toBe($mk->id);
});

it('banner mk yang dikerjakan menempatkan nama mata kuliah sebagai fokus utama', function () {
    $mk = Mk::factory()->forKurikulum($this->kurikulum)->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Aplikasi Komputer Matematika',
        'koordinator_mk_id' => $this->korma->id,
        'sks_teori' => 2,
        'sks_praktik' => 1,
        'sks_lapangan' => 0,
        'sks' => 3,
    ]);
    MkUnit::factory()->forMk($mk)->forKurikulum($this->kurikulum)->create([
        'kode' => 'KP21514004',
        'is_active' => true,
    ]);

    $this->actingAs($this->korma);
    MkTerpilih::set($mk->id);

    $html = MkTerpilih::bannerHtml('Catatan pelengkap uji')->toHtml();

    expect($html)
        ->toContain('data-silogy="banner-header-panel"')
        ->toContain('Mata kuliah yang dikerjakan')
        ->toContain('Aplikasi Komputer Matematika (KP21514004)')
        ->toContain('3 SKS')
        ->toContain('Ganti')
        ->toContain($this->kurikulum->nama)
        ->toContain('Program Studi')
        ->toContain('Catatan pelengkap uji')
        ->not->toContain('Kurikulum yang dikerjakan');
});

it('cpmk hanya menampilkan data mk terpilih', function () {
    $mkTerpilih = Mk::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'MK Terpilih',
        'koordinator_mk_id' => $this->korma->id,
    ]);
    MkUnit::factory()->forMk($mkTerpilih)->forAcademicUnit($this->prodi)->create(['kode' => 'SEL101']);

    $mkLain = Mk::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'MK Lain',
        'koordinator_mk_id' => $this->korma->id,
    ]);
    MkUnit::factory()->forMk($mkLain)->forAcademicUnit($this->prodi)->create(['kode' => 'SEL102']);

    Cpmk::query()->create([
        'mk_id' => $mkTerpilih->id,
        'kode' => 'CPMK-SEL',
        'deskripsi' => 'Hanya MK terpilih.',
    ]);
    Cpmk::query()->create([
        'mk_id' => $mkLain->id,
        'kode' => 'CPMK-LAIN',
        'deskripsi' => 'MK lain.',
    ]);

    MkTerpilih::set($mkTerpilih->id);

    Livewire::test(ListCpmks::class)
        ->loadTable()
        ->assertSeeHtml('data-silogy="banner-mk-header-panel"')
        ->assertDontSeeHtml('data-silogy="banner-header-panel"')
        ->assertDontSee('Urutkan menurut', escape: false)
        ->assertDontSeeHtml('data-silogy="kode-keterangan-trigger"')
        ->assertSee('CPMK-SEL', escape: false)
        ->assertDontSee('CPMK-LAIN', escape: false);
});
