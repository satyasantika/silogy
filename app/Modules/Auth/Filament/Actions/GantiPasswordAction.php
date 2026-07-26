<?php

namespace App\Modules\Auth\Filament\Actions;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rules\Password;

/**
 * Modal ganti kata sandi — dipakai di footer sidebar, user-menu navbar,
 * dan halaman profil.
 */
class GantiPasswordAction
{
    public static function make(string $name = 'gantiPassword'): Action
    {
        return Action::make($name)
            ->label('Ganti kata sandi')
            ->icon(Heroicon::Key)
            ->modalHeading('Ganti kata sandi')
            ->modalSubmitActionLabel('Simpan')
            ->schema([
                TextInput::make('currentPassword')
                    ->label('Kata sandi saat ini')
                    ->password()
                    ->revealable(filament()->arePasswordsRevealable())
                    ->currentPassword(guard: Filament::getAuthGuard())
                    ->required()
                    ->dehydrated(false),
                TextInput::make('password')
                    ->label('Kata sandi baru')
                    ->password()
                    ->revealable(filament()->arePasswordsRevealable())
                    ->rule(Password::default())
                    ->required()
                    ->same('passwordConfirmation'),
                TextInput::make('passwordConfirmation')
                    ->label('Konfirmasi kata sandi baru')
                    ->password()
                    ->revealable(filament()->arePasswordsRevealable())
                    ->required()
                    ->dehydrated(false),
            ])
            ->action(function (array $data): void {
                $user = auth()->user();

                if (! $user instanceof User) {
                    return;
                }

                // Cast 'hashed' pada model User menangani hashing.
                $user->forceFill(['password' => $data['password']])->save();

                if (request()->hasSession()) {
                    request()->session()->put([
                        'password_hash_'.Filament::getAuthGuard() => $user->getAuthPassword(),
                    ]);
                }

                Notification::make()->title('Kata sandi diperbarui')->success()->send();
            });
    }
}
