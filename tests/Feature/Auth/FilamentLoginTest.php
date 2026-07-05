<?php

use App\Filament\Pages\Auth\Login;
use App\Models\User;
use App\Notifications\ResetPassword as ResetPasswordNotification;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
});

it('login dengan username atau email akun demo berhasil', function () {
    Livewire::test(Login::class)
        ->fillForm([
            'login' => 'superadmin',
            'password' => 'siliwangi',
        ])
        ->call('authenticate')
        ->assertRedirect(route('filament.admin.pages.dashboard'))
        ->assertHasNoFormErrors();

    expect(auth()->check())->toBeTrue()
        ->and(auth()->user()?->username)->toBe('superadmin');
});

it('adminprodi melihat menu kelas mk setelah login', function () {
    Livewire::test(Login::class)
        ->fillForm([
            'login' => 'adminprodi',
            'password' => 'siliwangi',
        ])
        ->call('authenticate')
        ->assertRedirect(route('filament.admin.pages.dashboard'))
        ->assertHasNoFormErrors();

    $this->get(route('filament.admin.pages.dashboard'))
        ->assertSuccessful()
        ->assertSee('Kelas MK', escape: false);

    $this->get(route('filament.admin.resources.kelas-mks.index'))
        ->assertSuccessful();
});

it('mengirim notifikasi reset password dalam Bahasa Indonesia', function () {
    Notification::fake();

    Livewire::test(RequestPasswordReset::class)
        ->fillForm([
            'email' => 'superadmin@silogy.test',
        ])
        ->call('request')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'superadmin@silogy.test')->first();

    Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user): bool {
        $mail = $notification->toMail($user);

        return str_contains($mail->subject, 'Atur Ulang Kata Sandi')
            && str_contains(implode("\n", $mail->introLines), 'permintaan atur ulang kata sandi');
    });
});
