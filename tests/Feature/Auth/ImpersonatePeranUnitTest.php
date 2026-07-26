<?php

use App\Models\User;
use App\Modules\Auth\Filament\Pages\PilihPeranUnit;
use App\Modules\Auth\Livewire\PeranUnitMenu;
use App\Modules\Auth\Support\ActiveRole;
use App\Modules\Institusi\Filament\Resources\AcademicUnitResource;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Support\AcademicUnitTerpilih;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lab404\Impersonate\Services\ImpersonateManager;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
});

it('impersonate user single-role langsung ke dashboard tanpa gerbang', function () {
    $superAdmin = User::query()->where('username', 'superadmin')->firstOrFail();
    $timkur = User::query()->where('username', 'timkur')->firstOrFail();

    expect(count(ActiveRole::ownedRoleNames($timkur)))->toBe(1);

    $this->actingAs($superAdmin);

    app(ImpersonateManager::class)->take($superAdmin, $timkur);

    $url = \App\Modules\Auth\Support\PeranUnitFormFields::redirectUrlAfterImpersonateStart();

    expect($url)->not->toContain('pilih-peran-unit')
        ->and(auth()->user()->id)->toBe($timkur->id);
});

it('impersonate user multi-role diarahkan ke gerbang Pilih Peran & Unit', function () {
    $superAdmin = User::query()->where('username', 'superadmin')->firstOrFail();
    $dosentimkur = User::query()->where('username', 'dosentimkur')->firstOrFail();

    expect(count(ActiveRole::ownedRoleNames($dosentimkur)))->toBeGreaterThan(1);

    $this->actingAs($superAdmin);

    // Admin sempat pilih peran/unit sebelum impersonate — harus dibersihkan.
    session()->put(ActiveRole::SESSION_KEY, 'Pimpinan');

    app(ImpersonateManager::class)->take($superAdmin, $dosentimkur);

    $url = \App\Modules\Auth\Support\PeranUnitFormFields::redirectUrlAfterImpersonateStart();

    expect($url)->toContain('pilih-peran-unit')
        ->and(session()->has(ActiveRole::SESSION_KEY))->toBeFalse();
});

it('footer sidebar memuat PeranUnitMenu dan user-menu bawaan Filament tetap ada di topbar', function () {
    $timkur = User::query()->where('username', 'timkur')->firstOrFail();
    $this->actingAs($timkur);

    $response = $this->get('/dashboard');
    $response->assertOk();
    $response->assertSee('silogy.peran-unit-menu', false);
    $response->assertSee('fi-user-menu', false);
    $response->assertSee('silogy-sidebar-user', false);
});

it('PeranUnitMenu menampilkan menu identitas, ganti peran, dan aksi keluar', function () {
    $dosentimkur = User::query()->where('username', 'dosentimkur')->firstOrFail();
    $this->actingAs($dosentimkur);

    Livewire::test(PeranUnitMenu::class)
        ->assertSee('Profil')
        ->assertSee('Ganti kata sandi')
        ->assertSee('Ganti peran & unit')
        ->assertActionExists('keluar')
        ->assertActionExists('gantiPassword');
});

it('menu identitas PeranUnitMenu menautkan ganti peran ke halaman gerbang', function () {
    $dosentimkur = User::query()->where('username', 'dosentimkur')->firstOrFail();
    $this->actingAs($dosentimkur);

    Livewire::test(PeranUnitMenu::class)
        ->assertSee('Ganti peran & unit')
        ->assertSee(PilihPeranUnit::getUrl(), false)
        ->assertActionDoesNotExist('gantiPeranUnit');
});

it('modal keluar menampilkan konfirmasi ringkas tanpa ganti peran', function () {
    $dosentimkur = User::query()->where('username', 'dosentimkur')->firstOrFail();
    $this->actingAs($dosentimkur);

    expect(count(ActiveRole::ownedRoleNames($dosentimkur)))->toBeGreaterThan(1);

    Livewire::test(PeranUnitMenu::class)
        ->mountAction('keluar')
        ->assertSee('Yakin akan keluar aplikasi?')
        ->assertSee('Kembali')
        ->assertSee('Beranda')
        ->assertSee('Ya, keluar')
        ->assertActionDoesNotExist(['keluar', 'gantiPeranDariKeluar']);
});

it('user tanpa role sama sekali melihat keterangan danger di menu pengguna', function () {
    $bare = User::factory()->create(['username' => 'user_tanpa_role_test']);
    $this->actingAs($bare);

    Livewire::test(PeranUnitMenu::class)
        ->assertSee('Belum ada peran/unit');
});

it('halaman error menampilkan tombol leave-impersonate hanya saat impersonate aktif', function () {
    $superAdmin = User::query()->where('username', 'superadmin')->firstOrFail();
    $timkur = User::query()->where('username', 'timkur')->firstOrFail();

    \Illuminate\Support\Facades\Route::get('/uji-forbidden-impersonate', fn () => abort(403));

    $this->actingAs($timkur)
        ->get('/uji-forbidden-impersonate')
        ->assertForbidden()
        ->assertDontSee('Tinggalkan impersonate');

    $this->actingAs($superAdmin);
    app(ImpersonateManager::class)->take($superAdmin, $timkur);

    $this->get('/uji-forbidden-impersonate')
        ->assertForbidden()
        ->assertSee('Tinggalkan impersonate');
});

it('modal ganti peran & unit dari WelcomeWidget (kartu Selamat Datang) berhasil menyimpan', function () {
    $dosentimkur = User::query()->where('username', 'dosentimkur')->firstOrFail();
    $this->actingAs($dosentimkur);

    $unitIds = \App\Modules\Institusi\Support\AcademicUnitScope::scopedTimKurikulumUnitIdsFor($dosentimkur);
    $fakultas = AcademicUnit::query()->whereIn('id', $unitIds)->where('type', 'faculty')->firstOrFail();

    Livewire::test(\App\Filament\Widgets\WelcomeWidget::class)
        ->mountAction('gantiPeranUnit')
        ->setActionData(['role' => 'Tim Kurikulum', 'unit_id' => $fakultas->id])
        ->callMountedAction();

    expect(AcademicUnitTerpilih::currentId($dosentimkur))->toBe($fakultas->id);
});

it('outline animasi impersonate tampil hanya saat impersonate aktif, banner teks lama hilang', function () {
    $superAdmin = User::query()->where('username', 'superadmin')->firstOrFail();
    $timkur = User::query()->where('username', 'timkur')->firstOrFail();

    $this->actingAs($timkur)
        ->get('/dashboard')
        ->assertDontSee('silogy-impersonate-outline', false)
        ->assertDontSee('Anda sedang berpura-pura menjadi');

    $this->actingAs($superAdmin);
    // Guard eksplisit 'web' — mencerminkan Impersonate::impersonate() asli yang
    // selalu memanggil take($from, $to, $this->getGuard()), bukan take() 2-argumen.
    app(ImpersonateManager::class)->take($superAdmin, $timkur, 'web');

    $this->get('/dashboard')
        ->assertSee('silogy-impersonate-outline', false)
        ->assertDontSee('Anda sedang berpura-pura menjadi');
});

it('tombol Impersonate sungguhan di daftar pengguna mengarah ke gerbang untuk target multi-role', function () {
    $superAdmin = User::query()->where('username', 'superadmin')->firstOrFail();
    $dosentimkur = User::query()->where('username', 'dosentimkur')->firstOrFail();

    $this->actingAs($superAdmin);

    Livewire::test(\App\Modules\Auth\Filament\Resources\UserResource\Pages\ListUsers::class)
        ->callTableAction('impersonate', $dosentimkur)
        ->assertRedirect();

    expect(auth()->user()->id)->toBe($dosentimkur->id);
});

it('tombol Impersonate sungguhan mengarah langsung ke dashboard untuk target single-role', function () {
    $superAdmin = User::query()->where('username', 'superadmin')->firstOrFail();
    $timkur = User::query()->where('username', 'timkur')->firstOrFail();

    $this->actingAs($superAdmin);

    Livewire::test(\App\Modules\Auth\Filament\Resources\UserResource\Pages\ListUsers::class)
        ->callTableAction('impersonate', $timkur)
        ->assertRedirect(route('filament.admin.pages.dashboard'));
});
