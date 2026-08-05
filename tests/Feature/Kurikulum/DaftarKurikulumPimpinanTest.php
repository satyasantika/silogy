<?php

use App\Models\User;
use App\Modules\Auth\Support\ActiveRole;
use App\Modules\CPL\Models\Cpl;
use App\Modules\Institusi\Filament\Widgets\PimpinanKpiWidget;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Filament\Pages\DaftarKurikulumPimpinan;
use App\Modules\Kurikulum\Filament\Pages\HasilAnalisisCpl;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\Mahasiswa\Filament\Resources\MahasiswaResource;
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
    $this->kaprodi = User::query()->where('username', 'kaprodi')->firstOrFail();
    $this->dekan = User::query()->where('username', 'dekan')->firstOrFail();

    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Card Pimpinan',
        'tahun' => 2026,
        'is_active' => true,
        'kode' => 'KUR-PIMP-01',
    ]);
});

it('pimpinan melihat menu kurikulum dan tidak melihat menu mahasiswa', function () {
    $this->actingAs($this->kaprodi);
    ActiveRole::set('Pimpinan');

    expect(DaftarKurikulumPimpinan::shouldRegisterNavigation())->toBeTrue()
        ->and(MahasiswaResource::shouldRegisterNavigation())->toBeFalse();
});

it('KPI kepemimpinan mengarah ke daftar kurikulum pimpinan', function () {
    $this->actingAs($this->kaprodi);
    ActiveRole::set('Pimpinan');

    $url = DaftarKurikulumPimpinan::getUrl();

    Livewire::test(PimpinanKpiWidget::class)
        ->assertSuccessful()
        ->assertSee('Kurikulum Aktif')
        ->assertSeeHtml(e($url));
});

it('card kurikulum pimpinan punya aksi kerjakan dan badge laporan bukan profil/cpl/bok/mk', function () {
    $this->actingAs($this->kaprodi);
    ActiveRole::set('Pimpinan');

    Cpl::factory()->forKurikulum($this->kurikulum)->create(['kode' => 'CPL-PIMP']);

    KurikulumTerpilih::set($this->kurikulum->id);

    Livewire::test(DaftarKurikulumPimpinan::class)
        ->loadTable()
        ->assertSee('Sedang dikerjakan')
        ->assertSeeHtml('silogy-kurikulum-card-sedang-dikerjakan')
        ->assertSee('Hasil CPL · Ada', escape: false)
        ->assertSee('Grafik CPL · Ada', escape: false)
        ->assertSee('Per Mahasiswa · Ada', escape: false)
        ->assertDontSee('Profil ·', escape: false)
        ->assertDontSee('BoK ·', escape: false)
        ->assertSeeHtml('navigasi-kurikulum-pimpinan');

    $kurikulumLain = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Alternatif Pimpinan',
        'tahun' => 2027,
        'is_active' => false,
    ]);

    Livewire::test(DaftarKurikulumPimpinan::class)
        ->loadTable()
        ->callTableAction('kerjakan', $kurikulumLain);

    expect(KurikulumTerpilih::currentId())->toBe($kurikulumLain->id);
});

it('dekan melihat kurikulum prodi di bawah fakultas pada daftar pimpinan', function () {
    $this->actingAs($this->dekan);
    ActiveRole::set('Pimpinan');

    Livewire::test(DaftarKurikulumPimpinan::class)
        ->loadTable()
        ->assertCanSeeTableRecords([$this->kurikulum]);
});

it('badge laporan pimpinan mengunci kurikulum terpilih dan mengalihkan ke hasil analisis', function () {
    $this->actingAs($this->kaprodi);
    ActiveRole::set('Pimpinan');

    $response = $this->get(route('silogy.kurikulum-navigasi-pimpinan', [
        'kurikulum' => $this->kurikulum->id,
        'menu' => 'hasil',
    ]));

    $response->assertRedirect(HasilAnalisisCpl::getUrl());
    expect(KurikulumTerpilih::currentId())->toBe($this->kurikulum->id);
});
