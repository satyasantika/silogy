<?php

use App\Filament\Pages\Dashboard;
use App\Models\User;
use App\Modules\Auth\Livewire\RoleSwitcher;
use App\Modules\Auth\Support\ActiveRole;
use App\Modules\Kurikulum\Filament\Widgets\KurikulumTerpilihWidget;
use App\Modules\Kurikulum\Policies\KurikulumPolicy;
use App\Modules\Penilaian\Filament\Widgets\RekapMkDosenWidget;
use App\Modules\Penilaian\Policies\InputNilaiPolicy;
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

function timkurSegar(): User
{
    return User::query()->where('username', 'dosentimkur')->firstOrFail();
}

it('dosentimkur dual role: tanpa role aktif semua kemampuan berlaku', function () {
    $timkur = timkurSegar();
    $this->actingAs($timkur);

    expect($timkur->hasRole('Tim Kurikulum'))->toBeTrue()
        ->and($timkur->hasRole('Dosen Pengampu'))->toBeTrue()
        ->and(app(KurikulumPolicy::class)->viewAny($timkur))->toBeTrue()
        ->and(app(InputNilaiPolicy::class)->access($timkur))->toBeTrue();
});

it('memilih role dosen menyembunyikan kemampuan tim kurikulum', function () {
    $this->actingAs(timkurSegar());

    Livewire::test(RoleSwitcher::class)
        ->set('activeRole', 'Dosen Pengampu')
        ->assertRedirect('/dashboard');

    $timkur = timkurSegar();

    expect(session(ActiveRole::SESSION_KEY))->toBe('Dosen Pengampu')
        ->and($timkur->hasRole('Dosen Pengampu'))->toBeTrue()
        ->and($timkur->hasRole('Tim Kurikulum'))->toBeFalse()
        ->and(app(KurikulumPolicy::class)->viewAny($timkur))->toBeFalse()
        ->and(app(InputNilaiPolicy::class)->access($timkur))->toBeTrue();
});

it('memilih role tim kurikulum menyembunyikan kemampuan dosen', function () {
    $this->actingAs(timkurSegar());

    Livewire::test(RoleSwitcher::class)
        ->set('activeRole', 'Tim Kurikulum');

    $timkur = timkurSegar();

    expect($timkur->hasRole('Tim Kurikulum'))->toBeTrue()
        ->and($timkur->hasRole('Dosen Pengampu'))->toBeFalse()
        ->and(app(KurikulumPolicy::class)->viewAny($timkur))->toBeTrue()
        ->and(app(InputNilaiPolicy::class)->access($timkur))->toBeFalse();
});

it('kembali ke semua peran memulihkan seluruh kemampuan', function () {
    $this->actingAs(timkurSegar());

    session()->put(ActiveRole::SESSION_KEY, 'Dosen Pengampu');

    Livewire::test(RoleSwitcher::class)
        ->set('activeRole', '');

    $timkur = timkurSegar();

    expect(session()->has(ActiveRole::SESSION_KEY))->toBeFalse()
        ->and($timkur->hasRole('Tim Kurikulum'))->toBeTrue()
        ->and($timkur->hasRole('Dosen Pengampu'))->toBeTrue();
});

it('role yang tidak dimiliki diabaikan', function () {
    $timkur = timkurSegar();
    $this->actingAs($timkur);

    session()->put(ActiveRole::SESSION_KEY, 'Super Admin');

    expect(ActiveRole::currentFor($timkur))->toBeNull()
        ->and(timkurSegar()->hasRole('Tim Kurikulum'))->toBeTrue()
        ->and(timkurSegar()->hasRole('Super Admin'))->toBeFalse();
});

it('filter role aktif tidak mempengaruhi pengecekan role user lain', function () {
    $this->actingAs(timkurSegar());
    session()->put(ActiveRole::SESSION_KEY, 'Dosen Pengampu');

    $superadmin = User::query()->where('username', 'superadmin')->firstOrFail();

    expect($superadmin->hasRole('Super Admin'))->toBeTrue();
});

it('switcher headless: tidak merender UI peran karena ganti peran lewat menu identitas', function () {
    $this->actingAs(timkurSegar());

    Livewire::test(RoleSwitcher::class)
        ->assertDontSee('Peran aktif')
        ->assertDontSee('Semua peran')
        ->assertSee('aria-hidden="true"', escape: false);

    $this->actingAs(User::query()->where('username', 'dosen')->firstOrFail());

    Livewire::test(RoleSwitcher::class)
        ->assertDontSee('Peran aktif');
});

it('user multi-role yang belum pernah memilih mendapat role aktif pertama secara lokal, tanpa dipersist', function () {
    $this->actingAs(timkurSegar());

    expect(session()->has(ActiveRole::SESSION_KEY))->toBeFalse();

    $test = Livewire::test(RoleSwitcher::class);

    // "Dosen Pengampu" lebih dulu secara alfabet dibanding "Tim Kurikulum"
    // (lihat ActiveRole::ownedRoleNames, diurutkan ->orderBy('roles.name')).
    // Nilai ini TIDAK dipersist ke sesi (lihat RoleSwitcher::mount()) — bila
    // dipersist, user yang baru diberi role kedua di tengah sesi (mis. Dosen
    // Pengampu diangkat jadi Koordinator Mata Kuliah) akan terkunci ke role
    // lamanya secara permanen pada page load berikutnya.
    expect($test->get('activeRole'))->toBe('Dosen Pengampu')
        ->and(session()->has(ActiveRole::SESSION_KEY))->toBeFalse();
});

it('card Selamat Datang menampilkan peran aktif user', function () {
    $timkur = timkurSegar();
    $this->actingAs($timkur);

    session()->put(ActiveRole::SESSION_KEY, 'Tim Kurikulum');

    Livewire::test(Dashboard::class)
        ->assertSee('Anda berperan sebagai')
        ->assertSee('Tim Kurikulum');
});

it('widget dashboard mengikuti role aktif, bukan kepemilikan role', function () {
    $this->actingAs(timkurSegar());

    ActiveRole::set('Dosen Pengampu');

    expect(RekapMkDosenWidget::canView())->toBeTrue()
        ->and(KurikulumTerpilihWidget::canView())->toBeFalse();

    ActiveRole::set('Tim Kurikulum');

    expect(RekapMkDosenWidget::canView())->toBeFalse()
        ->and(KurikulumTerpilihWidget::canView())->toBeTrue();
});
