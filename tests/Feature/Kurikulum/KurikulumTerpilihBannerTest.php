<?php

use App\Models\User;
use App\Modules\BoK\Filament\Resources\BokResource\Pages\ListBoks;
use App\Modules\CPL\Filament\Resources\CplResource\Pages\ListCpls;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Filament\Pages\AnalisisMkProdi;
use App\Modules\Kurikulum\Filament\Pages\CplBokMatrix;
use App\Modules\Kurikulum\Filament\Pages\CplMkMatrix;
use App\Modules\Kurikulum\Filament\Pages\ProfilCplMatrix;
use App\Modules\Kurikulum\Filament\Resources\ProfilLulusanResource\Pages\ListProfilLulusans;
use App\Modules\Kurikulum\Livewire\KurikulumTerpilihBanner;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Filament\Resources\MkResource\Pages\ListMks;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
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
        'nama' => 'Kurikulum Banner Modal',
        'tahun' => 2026,
        'is_active' => true,
    ]);
});

it('banner kurikulum membuka modal ganti dan menyimpan pilihan', function () {
    $timkur = User::where('username', 'timkur')->firstOrFail();
    $this->actingAs($timkur);
    KurikulumTerpilih::set($this->kurikulum->id);

    $kurikulumLain = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Banner Alternatif',
        'tahun' => 2027,
        'is_active' => false,
    ]);

    $banner = Livewire::test(KurikulumTerpilihBanner::class)
        ->assertSee('Kurikulum yang dikerjakan')
        ->assertSee('Kurikulum Banner Modal')
        ->assertSee('Ganti')
        ->assertSeeHtml('silogy-kurikulum-banner-ganti');

    // Ikon dirender sebagai SVG inline (tanpa nama heroicon di HTML), jadi
    // keberadaannya diperiksa lewat definisi action.
    expect($banner->instance()->gantiAction()->getIcon())->toBe(Heroicon::ArrowsRightLeft);

    $banner->callAction('ganti', data: [
        'kurikulum_id' => $kurikulumLain->id,
    ]);

    expect(KurikulumTerpilih::currentId())->toBe($kurikulumLain->id);
});

it('banner menampilkan catatan konteks di bawah garis pemisah', function () {
    $timkur = User::where('username', 'timkur')->firstOrFail();
    $this->actingAs($timkur);
    KurikulumTerpilih::set($this->kurikulum->id);

    $catatan = 'Profil lulusan yang tampil mengikuti kurikulum prodi ini.';

    Livewire::test(KurikulumTerpilihBanner::class, ['catatan' => $catatan])
        ->assertSee('Kurikulum yang dikerjakan')
        ->assertSee($catatan);
});

it('halaman daftar profil cpl bok mk menampilkan banner identitas beserta keterangannya', function () {
    $timkur = User::where('username', 'timkur')->firstOrFail();
    $this->actingAs($timkur);
    KurikulumTerpilih::set($this->kurikulum->id);

    Livewire::test(ListProfilLulusans::class)
        ->assertSee('Kurikulum yang dikerjakan')
        ->assertSee('Profil lulusan yang tampil, ditambahkan, maupun diimpor mengikuti kurikulum prodi ini.');

    Livewire::test(ListCpls::class)
        ->assertSee('Kurikulum yang dikerjakan')
        ->assertSeeHtml('data-silogy="banner-kurikulum-header-panel"')
        ->assertDontSee('CPL yang tampil, ditambahkan, maupun diimpor mengikuti kurikulum ini.', escape: false)
        ->assertDontSee('Urutkan menurut', escape: false)
        ->assertDontSeeHtml('data-silogy="banner-header-panel"');

    Livewire::test(ListBoks::class)
        ->assertSee('Kurikulum yang dikerjakan')
        ->assertSeeHtml('data-silogy="banner-kurikulum-header-panel"')
        ->assertDontSee('Bahan kajian (BoK) yang tampil, ditambahkan, maupun diimpor mengikuti kurikulum ini.', escape: false)
        ->assertDontSee('Urutkan menurut', escape: false)
        ->assertDontSeeHtml('data-silogy="banner-header-panel"');

    Livewire::test(ListMks::class)
        ->assertSee('Kurikulum yang dikerjakan')
        ->assertSeeHtml('data-silogy="banner-kurikulum-header-panel"')
        ->assertDontSee('Mata kuliah yang tampil, ditambahkan, maupun diimpor mengikuti kurikulum ini.', escape: false)
        ->assertDontSee('Urutkan menurut', escape: false)
        ->assertDontSeeHtml('data-silogy="banner-header-panel"');
});

it('halaman interaksi dan analisis mk prodi menampilkan banner beserta keterangan khas', function () {
    $timkur = User::where('username', 'timkur')->firstOrFail();
    $this->actingAs($timkur);
    KurikulumTerpilih::set($this->kurikulum->id);

    Livewire::test(ProfilCplMatrix::class)
        ->assertSee('Kurikulum yang dikerjakan')
        ->assertSee('Pemetaan profil lulusan ke CPL yang dicentang di bawah tersimpan pada kurikulum ini.');

    Livewire::test(CplBokMatrix::class)
        ->assertSeeHtml('data-silogy="banner-header-panel"')
        ->assertSee('Kurikulum yang dikerjakan')
        ->assertSee('Pemetaan CPL ke bahan kajian (BoK) yang dicentang di bawah tersimpan pada kurikulum ini.');

    Livewire::test(CplMkMatrix::class)
        ->assertSeeHtml('data-silogy="banner-header-panel"')
        ->assertSee('Kurikulum yang dikerjakan')
        ->assertSee('Bobot kontribusi MK terhadap CPL (via BoK) yang diisi di bawah tersimpan pada kurikulum ini.');

    Livewire::test(AnalisisMkProdi::class)
        ->assertSeeHtml('data-silogy="banner-header-panel"')
        ->assertSee('Kurikulum yang dikerjakan')
        ->assertSee('Pemetaan Rencana Asesmen CPL', escape: false)
        ->assertSee('Hasil Analisis Asesmen CPL per Mahasiswa', escape: false)
        ->assertDontSee('Seluruh pemetaan, hasil asesmen, dan grafik CPL di bawah dihitung dari kurikulum prodi ini.', escape: false)
        ->assertDontSee('gabungan (rollup)', escape: false);
});
