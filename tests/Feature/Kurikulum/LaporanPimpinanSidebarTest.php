<?php

use App\Models\User;
use App\Modules\AI\Filament\Pages\RequestAnalisis;
use App\Modules\AI\Filament\Resources\AnalisisAiResource;
use App\Modules\Auth\Support\ActiveRole;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Filament\Pages\AnalisisCplMahasiswa;
use App\Modules\Kurikulum\Filament\Pages\AnalisisMkProdi;
use App\Modules\Kurikulum\Filament\Pages\DaftarKurikulumPimpinan;
use App\Modules\Kurikulum\Filament\Pages\GrafikCpl;
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
    $this->timkur = User::query()->where('username', 'timkur')->firstOrFail();

    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Laporan Pimpinan',
        'tahun' => 2026,
        'is_active' => true,
    ]);
});

it('sidebar pimpinan hanya menampilkan kurikulum dan tiga menu laporan (bukan Analisis MK / AI / Mahasiswa)', function () {
    $this->actingAs($this->kaprodi);
    ActiveRole::set('Pimpinan');
    KurikulumTerpilih::set($this->kurikulum->id);

    expect(DaftarKurikulumPimpinan::shouldRegisterNavigation())->toBeTrue()
        ->and(HasilAnalisisCpl::shouldRegisterNavigation())->toBeTrue()
        ->and(GrafikCpl::shouldRegisterNavigation())->toBeTrue()
        ->and(AnalisisCplMahasiswa::shouldRegisterNavigation())->toBeTrue()
        ->and(AnalisisMkProdi::shouldRegisterNavigation())->toBeFalse()
        ->and(RequestAnalisis::shouldRegisterNavigation())->toBeFalse()
        ->and(AnalisisAiResource::shouldRegisterNavigation())->toBeFalse()
        ->and(MahasiswaResource::shouldRegisterNavigation())->toBeFalse();

    expect(DaftarKurikulumPimpinan::getNavigationLabel())->toBe('Kurikulum')
        ->and(HasilAnalisisCpl::getNavigationLabel())->toBe('Hasil Analisis CPL')
        ->and(GrafikCpl::getNavigationLabel())->toBe('Grafik CPL')
        ->and(AnalisisCplMahasiswa::getNavigationLabel())->toBe('Analisis per Mahasiswa');
});

it('tim kurikulum tetap melihat Analisis MK dan tidak melihat menu laporan pimpinan', function () {
    $this->actingAs($this->timkur);
    ActiveRole::set('Tim Kurikulum');
    KurikulumTerpilih::set($this->kurikulum->id);

    expect(AnalisisMkProdi::shouldRegisterNavigation())->toBeTrue()
        ->and(HasilAnalisisCpl::shouldRegisterNavigation())->toBeFalse()
        ->and(GrafikCpl::shouldRegisterNavigation())->toBeFalse()
        ->and(AnalisisCplMahasiswa::shouldRegisterNavigation())->toBeFalse();
});

it('halaman laporan pimpinan menampilkan banner kurikulum dan konten tab analisis', function () {
    $this->actingAs($this->kaprodi);
    ActiveRole::set('Pimpinan');
    KurikulumTerpilih::set($this->kurikulum->id);

    Livewire::test(HasilAnalisisCpl::class)
        ->assertSuccessful()
        ->assertSee('Kurikulum yang dikerjakan', escape: false)
        ->assertSeeHtml('data-silogy="banner-header-panel"')
        ->assertSee('Hasil Analisis CPL', escape: false)
        ->assertDontSee('Pemetaan Rencana Asesmen CPL', escape: false);

    Livewire::test(GrafikCpl::class)
        ->assertSuccessful()
        ->assertSeeHtml('data-silogy="banner-header-panel"')
        ->assertSee('Grafik CPL', escape: false);

    Livewire::test(AnalisisCplMahasiswa::class)
        ->assertSuccessful()
        ->assertSeeHtml('data-silogy="banner-header-panel"')
        ->assertSee('Analisis per Mahasiswa', escape: false);
});

it('kaprodi tetap bisa membuka URL Analisis MK Prodi (akses data), hanya nav yang disembunyikan', function () {
    $this->actingAs($this->kaprodi);
    ActiveRole::set('Pimpinan');
    KurikulumTerpilih::set($this->kurikulum->id);

    expect(AnalisisMkProdi::canAccess())->toBeTrue()
        ->and(AnalisisMkProdi::shouldRegisterNavigation())->toBeFalse();
});
