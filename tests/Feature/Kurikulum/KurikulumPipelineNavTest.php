<?php

use App\Models\User;
use App\Modules\BoK\Filament\Resources\BokResource\Pages\ListBoks;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Filament\Resources\CplResource\Pages\ListCpls;
use App\Modules\CPL\Models\Cpl;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Filament\Resources\ProfilLulusanResource\Pages\ListProfilLulusans;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Models\ProfilLulusan;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Filament\Resources\MkResource\Pages\ListMks;
use App\Modules\MK\Filament\Resources\MkUnitResource\Pages\ListMkUnits;
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

    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->fakultas = AcademicUnit::query()->where('type', 'faculty')->firstOrFail();

    $this->kurikulumProdi = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Prodi Pipeline',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    $this->kurikulumFak = Kurikulum::query()->create([
        'academic_unit_id' => $this->fakultas->id,
        'nama' => 'Kurikulum Fakultas Pipeline',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    $this->timkur = User::query()->where('username', 'timkur')->firstOrFail();
    $this->timkurfak = User::query()->where('username', 'timkurfak')->firstOrFail();
});

function buatProfilLulusan(Kurikulum $kurikulum): ProfilLulusan
{
    return ProfilLulusan::query()->create([
        'kurikulum_id' => $kurikulum->id,
        'kode' => 'PL-01',
        'nama' => 'Profil A',
        'deskripsi' => 'Deskripsi profil',
        'urutan' => 1,
    ]);
}

it('prodi: profil lulusan kosong tidak menampilkan next, back mengarah ke daftar kurikulum', function () {
    $this->actingAs($this->timkur);
    KurikulumTerpilih::set($this->kurikulumProdi->id);

    Livewire::test(ListProfilLulusans::class)
        ->assertSee('« Daftar Kurikulum', escape: false)
        ->assertDontSee('CPL »', escape: false);
});

it('prodi: profil lulusan terisi menampilkan next ke cpl, dan cpl back ke profil lulusan', function () {
    buatProfilLulusan($this->kurikulumProdi);

    $this->actingAs($this->timkur);
    KurikulumTerpilih::set($this->kurikulumProdi->id);

    Livewire::test(ListProfilLulusans::class)
        ->assertSee('CPL »', escape: false);

    Livewire::test(ListCpls::class)
        ->assertSee('« Profil Lulusan', escape: false);
});

it('prodi: cpl kosong tidak menampilkan next ke bok meski profil lulusan sudah ada', function () {
    buatProfilLulusan($this->kurikulumProdi);

    $this->actingAs($this->timkur);
    KurikulumTerpilih::set($this->kurikulumProdi->id);

    Livewire::test(ListCpls::class)
        ->assertSee('« Profil Lulusan', escape: false)
        ->assertDontSee('BoK »', escape: false);
});

it('prodi: next tersedia bertahap sampai penawaran mk, dan penawaran mk tidak punya next', function () {
    buatProfilLulusan($this->kurikulumProdi);
    Cpl::factory()->forKurikulum($this->kurikulumProdi)->create();
    Bok::factory()->forKurikulum($this->kurikulumProdi)->create();
    $mk = Mk::factory()->forKurikulum($this->kurikulumProdi)->create();
    MkUnit::factory()->forMk($mk)->forKurikulum($this->kurikulumProdi)->create(['is_active' => true]);

    $this->actingAs($this->timkur);
    KurikulumTerpilih::set($this->kurikulumProdi->id);

    Livewire::test(ListCpls::class)->assertSee('BoK »', escape: false);
    Livewire::test(ListBoks::class)->assertSee('MK »', escape: false);
    Livewire::test(ListMks::class)->assertSee('Penawaran MK »', escape: false);
    Livewire::test(ListMkUnits::class)
        ->assertSee('« MK', escape: false)
        ->assertDontSee('Penawaran MK »', escape: false);
});

it('fakultas: cpl adalah langkah pertama (back ke daftar kurikulum), mk adalah langkah terakhir (tanpa next ke penawaran mk)', function () {
    Cpl::factory()->forKurikulum($this->kurikulumFak)->create();
    Bok::factory()->forKurikulum($this->kurikulumFak)->create();
    Mk::factory()->forKurikulum($this->kurikulumFak)->create();

    $this->actingAs($this->timkurfak);
    KurikulumTerpilih::set($this->kurikulumFak->id);

    Livewire::test(ListCpls::class)
        ->assertSee('« Daftar Kurikulum', escape: false)
        ->assertDontSee('« Profil Lulusan', escape: false)
        ->assertSee('BoK »', escape: false);

    Livewire::test(ListMks::class)
        ->assertSee('« BoK', escape: false)
        ->assertDontSee('Penawaran MK »', escape: false);
});

it('cpl fakultas: tombol next ke bok langsung muncul setelah import massal, tanpa reload', function () {
    $this->actingAs($this->timkurfak);
    KurikulumTerpilih::set($this->kurikulumFak->id);

    $halaman = Livewire::test(ListCpls::class)
        ->assertDontSee('BoK »', escape: false);

    $halaman->callAction('bulkImport', [
        'rows' => 'CPL-IMPOR-01|Deskripsi CPL hasil impor massal|kognitif',
        'mode_duplikat' => 'lewati',
    ]);

    $halaman->assertSee('BoK »', escape: false);
});

it('prodi: tombol interaksi & pelaporan hanya muncul setelah penawaran mk terisi, berisi seluruh link yang berlaku', function () {
    buatProfilLulusan($this->kurikulumProdi);
    Cpl::factory()->forKurikulum($this->kurikulumProdi)->create();
    Bok::factory()->forKurikulum($this->kurikulumProdi)->create();
    $mk = Mk::factory()->forKurikulum($this->kurikulumProdi)->create();

    $this->actingAs($this->timkur);
    KurikulumTerpilih::set($this->kurikulumProdi->id);

    Livewire::test(ListMkUnits::class)
        ->assertDontSee('Interaksi & Pelaporan »');

    MkUnit::factory()->forMk($mk)->forKurikulum($this->kurikulumProdi)->create(['is_active' => true]);

    Livewire::test(ListMkUnits::class)
        ->assertSee('Interaksi & Pelaporan »')
        ->assertSee('Interaksi Profil × CPL', escape: false)
        ->assertSee('Interaksi CPL × BoK', escape: false)
        ->assertSee('Interaksi CPL × MK', escape: false)
        ->assertSee('Pelaporan Analisis MK', escape: false);
});

it('fakultas: tombol interaksi & pelaporan muncul di halaman mk tanpa link Profil x CPL (khusus prodi)', function () {
    Cpl::factory()->forKurikulum($this->kurikulumFak)->create();
    Bok::factory()->forKurikulum($this->kurikulumFak)->create();
    Mk::factory()->forKurikulum($this->kurikulumFak)->create();

    $this->actingAs($this->timkurfak);
    KurikulumTerpilih::set($this->kurikulumFak->id);

    Livewire::test(ListMks::class)
        ->assertSee('Interaksi & Pelaporan »')
        ->assertDontSee('Interaksi Profil × CPL', escape: false)
        ->assertSee('Interaksi CPL × BoK', escape: false)
        ->assertSee('Interaksi CPL × MK', escape: false)
        ->assertSee('Pelaporan Analisis MK', escape: false);
});
