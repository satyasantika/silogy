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

it('halaman laporan pimpinan memakai judul singkat tanpa hierarki unit', function () {
    $this->actingAs($this->kaprodi);
    ActiveRole::set('Pimpinan');
    KurikulumTerpilih::set($this->kurikulum->id);

    expect(Livewire::test(HasilAnalisisCpl::class)->instance()->getTitle())->toBe('Hasil Analisis CPL')
        ->and(Livewire::test(GrafikCpl::class)->instance()->getTitle())->toBe('Grafik CPL')
        ->and(Livewire::test(AnalisisCplMahasiswa::class)->instance()->getTitle())->toBe('Analisis per Mahasiswa');
});

it('halaman laporan pimpinan menampilkan banner kurikulum dan konten tab analisis', function () {
    $this->actingAs($this->kaprodi);
    ActiveRole::set('Pimpinan');
    KurikulumTerpilih::set($this->kurikulum->id);

    Livewire::test(HasilAnalisisCpl::class)
        ->assertSuccessful()
        ->assertSee('Kurikulum yang dikerjakan', escape: false)
        ->assertSeeHtml('data-silogy="banner-header-panel"')
        ->assertSeeHtml('silogy-laporan-kurikulum-kpi--page')
        ->assertSee('Penilaian mata kuliah')
        ->assertSee('Penilaian mahasiswa')
        ->assertDontSee('Ringkasan analisis asesmen berdasarkan kurikulum terpilih', escape: false)
        ->assertDontSee('Pemetaan Rencana Asesmen CPL', escape: false);

    Livewire::test(GrafikCpl::class)
        ->assertSuccessful()
        ->assertSeeHtml('data-silogy="banner-header-panel"')
        ->assertSeeHtml('silogy-laporan-kurikulum-kpi--page')
        ->assertSee('Penilaian mata kuliah')
        ->assertSee('Pengisian nilai pada penawaran MK')
        ->assertDontSee('Pengisian nilai kontrak mata kuliah')
        ->assertSee('Grafik CPL', escape: false);

    Livewire::test(AnalisisCplMahasiswa::class)
        ->assertSuccessful()
        ->assertSee('Kurikulum yang dikerjakan', escape: false)
        ->assertSeeHtml('silogy-laporan-kurikulum-kpi--page')
        ->assertSee('Penilaian mahasiswa')
        ->assertSee('Pengisian nilai kontrak mata kuliah')
        ->assertDontSee('Pengisian nilai pada penawaran MK')
        ->assertDontSee('Mahasiswa yang mengontrak mata kuliah pada kurikulum yang dikerjakan', escape: false)
        ->assertDontSee('Nilai Angka', escape: false)
        ->assertDontSee('Nilai Huruf', escape: false)
        ->assertDontSee('Bobot Huruf', escape: false);
});

it('halaman hasil analisis pimpinan prodi menampilkan deskripsi CPL lengkap dan badge SKS MK', function () {
    $this->actingAs($this->kaprodi);
    ActiveRole::set('Pimpinan');
    KurikulumTerpilih::set($this->kurikulum->id);

    $deskripsiCpl = 'Mampu merancang solusi perangkat lunak berbasis data untuk konteks program studi.';
    $cpl = \App\Modules\CPL\Models\Cpl::factory()->forKurikulum($this->kurikulum)->create([
        'kode' => 'CPL-PIM-01',
        'deskripsi' => $deskripsiCpl,
    ]);
    $bok = \App\Modules\BoK\Models\Bok::factory()->forKurikulum($this->kurikulum)->create();
    $cplBok = \App\Modules\CPL\Models\CplBok::query()->create([
        'cpl_id' => $cpl->id,
        'bok_id' => $bok->id,
        'bobot' => 100,
    ]);
    $mk = \App\Modules\MK\Models\Mk::factory()->forKurikulum($this->kurikulum)->create([
        'nama' => 'Pemrograman Lanjut Pimpinan',
        'sks_teori' => 2,
        'sks_praktik' => 1,
        'sks_lapangan' => 0,
        'sks' => 3,
    ]);
    \App\Modules\MK\Models\MkUnit::factory()->forMk($mk)->forKurikulum($this->kurikulum)->create([
        'kode' => 'PIM3101',
        'is_active' => true,
    ]);
    \App\Modules\CPL\Models\CplMk::query()->create([
        'cpl_bok_id' => $cplBok->id,
        'mk_id' => $mk->id,
        'bobot' => 100,
    ]);

    Livewire::test(HasilAnalisisCpl::class)
        ->assertSuccessful()
        ->assertSee($deskripsiCpl, escape: false)
        ->assertSee('Pemrograman Lanjut Pimpinan (PIM3101)', escape: false)
        ->assertSee('3 SKS', escape: false)
        ->assertDontSee('-webkit-line-clamp:4', escape: false);
});
