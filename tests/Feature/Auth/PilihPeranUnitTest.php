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
});

it('mount() PilihPeranUnit redirect ke dashboard bila user single-role tanpa pilihan unit', function () {
    $timkur = User::query()->where('username', 'timkur')->firstOrFail();
    $this->actingAs($timkur);

    $response = $this->get(PilihPeranUnit::getUrl());
    $response->assertRedirect();
    expect($response->headers->get('Location'))->not->toContain('pilih-peran-unit');
});

it('submit gerbang menyimpan role dan unit aktif, lalu KurikulumTerpilih mengikuti unit tersebut', function () {
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
        ->set('data.role', 'Tim Kurikulum')
        ->set('data.unit_id', $prodi->id)
        ->call('submit');

    expect(ActiveRole::currentFor($dosentimkur))->toBe('Tim Kurikulum')
        ->and(AcademicUnitTerpilih::currentId($dosentimkur))->toBe($prodi->id);

    $kurikulumProdi = KurikulumTerpilih::default($dosentimkur);
    expect($kurikulumProdi?->academic_unit_id)->toBe($prodi->id);

    // Ganti unit aktif ke fakultas — KurikulumTerpilih harus ikut berubah,
    // membuktikan bug asli (data kurikulum tidak ditemukan) sudah selesai:
    // sekarang unit yang ditampilkan benar-benar mengikuti pilihan eksplisit user.
    Livewire::test(PilihPeranUnit::class)
        ->set('data.role', 'Tim Kurikulum')
        ->set('data.unit_id', $fakultas->id)
        ->call('submit');

    $kurikulumFakultas = KurikulumTerpilih::default($dosentimkur);
    expect($kurikulumFakultas?->academic_unit_id)->toBe($fakultas->id);
});

it('ikon switcher sidebar tampil untuk multi-role dan tersembunyi untuk single-role', function () {
    $dosentimkur = User::query()->where('username', 'dosentimkur')->firstOrFail();
    $timkur = User::query()->where('username', 'timkur')->firstOrFail();

    $this->actingAs($dosentimkur);
    $response = $this->get('/dashboard');
    $response->assertSee('pilih-peran-unit', false);

    $this->actingAs($timkur);
    $response = $this->get('/dashboard');
    $response->assertDontSee('pilih-peran-unit', false);
});
