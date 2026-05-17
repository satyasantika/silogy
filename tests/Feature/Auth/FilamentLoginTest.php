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

it('login dengan username superadmin berhasil', function () {
    Livewire::test(Login::class)
        ->fillForm([
            'login' => 'superadmin',
            'password' => 'Silogy2026!',
        ])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    expect(auth()->check())->toBeTrue()
        ->and(auth()->user()?->username)->toBe('superadmin');
});

it('login dengan email superadmin berhasil', function () {
    Livewire::test(Login::class)
        ->fillForm([
            'login' => 'superadmin@silogy.test',
            'password' => 'Silogy2026!',
        ])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    expect(auth()->user()?->email)->toBe('superadmin@silogy.test');
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
