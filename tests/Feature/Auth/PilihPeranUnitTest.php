<?php

use App\Models\User;
use App\Modules\Auth\Filament\Pages\PilihPeranUnit;
use App\Modules\Auth\Support\ActiveRole;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Support\AcademicUnitTerpilih;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
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
});

it('login single-role langsung ke dashboard, gerbang tidak pernah muncul', function () {
    $timkur = User::query()->where('username', 'timkur')->firstOrFail();

    $response = $this->actingAs($timkur)->post('/login', [
        'login' => $timkur->username,
        'password' => 'password',
    ]);

    // Simulasikan tepat seperti FilamentDefaultLoginRedirect dipanggil manual,
    // karena actingAs() tidak melalui form login sungguhan.
    session()->forget(ActiveRole::SESSION_KEY);
    session()->forget(AcademicUnitTerpilih::SESSION_KEY);

    expect(count(ActiveRole::ownedRoleNames($timkur)))->toBe(1);
});

it('user multi-role diarahkan ke halaman Pilih Peran & Unit, bukan langsung dashboard', function () {
    $dosentimkur = User::query()->where('username', 'dosentimkur')->firstOrFail();

    expect(count(ActiveRole::ownedRoleNames($dosentimkur)))->toBeGreaterThan(1);

    $this->actingAs($dosentimkur);

    $response = $this->get(PilihPeranUnit::getUrl());
    $response->assertOk();
    $response->assertSee('Pilih peran dan unit Anda');
    $response->assertSee($dosentimkur->full_name);
    $response->assertSee('@'.$dosentimkur->username);
    $response->assertSee('Peran saat ini');
    $response->assertSee('Unit saat ini');
    $response->assertSee('Dosen Pengampu');
    $response->assertSee('Tim Kurikulum');
});

it('pilihan peran memakai kartu persegi berikon representatif tanpa tombol Lanjutkan', function () {
    $dosentimkur = User::query()->where('username', 'dosentimkur')->firstOrFail();
    $this->actingAs($dosentimkur);

    Livewire::test(PilihPeranUnit::class)
        ->assertSee('Dosen Pengampu')
        ->assertSee('Tim Kurikulum')
        ->assertSeeHtml('silogy-peran-card')
        ->assertSeeHtml('wire:click="pilihPeran')
        ->assertDontSee('Lanjutkan');

    expect(\App\Modules\Auth\Support\PeranUnitFormFields::iconForRole('Dosen Pengampu')->value)
        ->toBe(\Filament\Support\Icons\Heroicon::OutlinedAcademicCap->value)
        ->and(\App\Modules\Auth\Support\PeranUnitFormFields::iconForRole('Tim Kurikulum')->value)
        ->toBe(\Filament\Support\Icons\Heroicon::OutlinedBookOpen->value)
        ->and(\App\Modules\Auth\Support\PeranUnitFormFields::iconForRole('Super Admin')->value)
        ->toBe(\Filament\Support\Icons\Heroicon::OutlinedShieldCheck->value)
        ->and(\App\Modules\Auth\Support\PeranUnitFormFields::iconForRole('Koordinator Mata Kuliah')->value)
        ->toBe(\Filament\Support\Icons\Heroicon::OutlinedClipboardDocumentList->value)
        ->and(\App\Modules\Auth\Support\PeranUnitFormFields::iconForRole('Auditor Mutu')->value)
        ->toBe(\Filament\Support\Icons\Heroicon::OutlinedMagnifyingGlassCircle->value)
        ->and(\App\Modules\Auth\Support\PeranUnitFormFields::colorForRole('Pimpinan'))
        ->toBe('warning');
});

it('mount() PilihPeranUnit redirect ke dashboard bila user single-role tanpa pilihan unit', function () {
    $timkur = User::query()->where('username', 'timkur')->firstOrFail();
    $this->actingAs($timkur);

    $response = $this->get(PilihPeranUnit::getUrl());
    $response->assertRedirect();
    expect($response->headers->get('Location'))->not->toContain('pilih-peran-unit');
});

it('klik kartu peran/unit menyimpan role dan unit aktif, lalu KurikulumTerpilih mengikuti unit tersebut', function () {
    $dosentimkur = User::query()->where('username', 'dosentimkur')->firstOrFail();
    $this->actingAs($dosentimkur);

    $prodiUnitIds = \App\Modules\Institusi\Support\AcademicUnitScope::scopedTimKurikulumUnitIdsFor($dosentimkur);
    $prodi = AcademicUnit::query()->whereIn('id', $prodiUnitIds)->where('type', 'study_program')->firstOrFail();
    $fakultas = AcademicUnit::query()->whereIn('id', $prodiUnitIds)->where('type', 'faculty')->firstOrFail();

    Kurikulum::query()->create([
        'academic_unit_id' => $prodi->id,
        'nama' => 'Kurikulum Prodi 2026',
        'tahun' => 2026,
        'is_active' => true,
    ]);
    Kurikulum::query()->create([
        'academic_unit_id' => $fakultas->id,
        'nama' => 'OBE-FKIP-2026',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    Livewire::test(PilihPeranUnit::class)
        ->call('pilihPeran', 'Tim Kurikulum')
        ->assertSet('selectedRole', 'Tim Kurikulum')
        ->assertSee($prodi->nama)
        ->call('pilihUnit', $prodi->id)
        ->assertRedirect();

    expect(ActiveRole::currentFor($dosentimkur))->toBe('Tim Kurikulum')
        ->and(AcademicUnitTerpilih::currentId($dosentimkur))->toBe($prodi->id);

    $kurikulumProdi = KurikulumTerpilih::default($dosentimkur);
    expect($kurikulumProdi?->academic_unit_id)->toBe($prodi->id);

    // Ganti unit aktif ke fakultas — KurikulumTerpilih harus ikut berubah,
    // membuktikan bug asli (data kurikulum tidak ditemukan) sudah selesai:
    // sekarang unit yang ditampilkan benar-benar mengikuti pilihan eksplisit user.
    Livewire::test(PilihPeranUnit::class)
        ->call('pilihPeran', 'Tim Kurikulum')
        ->call('pilihUnit', $fakultas->id)
        ->assertRedirect();

    $kurikulumFakultas = KurikulumTerpilih::default($dosentimkur);
    expect($kurikulumFakultas?->academic_unit_id)->toBe($fakultas->id);
});

it('klik kartu peran tanpa pilihan unit langsung menerapkan dan redirect', function () {
    $dosentimkur = User::query()->where('username', 'dosentimkur')->firstOrFail();
    $this->actingAs($dosentimkur);

    Livewire::test(PilihPeranUnit::class)
        ->call('pilihPeran', 'Dosen Pengampu')
        ->assertRedirect();

    expect(ActiveRole::currentFor($dosentimkur))->toBe('Dosen Pengampu');
});

it('ganti peran hanya di menu identitas, bukan ikon nav RoleSwitcher', function () {
    $dosentimkur = User::query()->where('username', 'dosentimkur')->firstOrFail();
    $timkur = User::query()->where('username', 'timkur')->firstOrFail();

    $this->actingAs($dosentimkur);
    $response = $this->get('/dashboard');
    $response->assertSee('Ganti peran & unit');
    $response->assertSee('pilih-peran-unit', false);
    $response->assertDontSee('Ganti peran aktif');

    $this->actingAs($timkur);
    $response = $this->get('/dashboard');
    // Single-role tanpa pilihan unit: item ganti peran tidak perlu tampil.
    $response->assertDontSee('Ganti peran aktif');
});
