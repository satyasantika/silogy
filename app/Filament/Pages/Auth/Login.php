<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    public function getView(): string
    {
        return 'filament.pages.auth.login';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getLoginFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
            ]);
    }

    protected function getLoginFormComponent(): Component
    {
        return TextInput::make('login')
            ->label('Email, Username, NIDN, NIP, atau NUPTK')
            ->required()
            ->autocomplete('username')
            ->autofocus();
    }

    /**
     * Login menerima email, username, NIDN, NIP, atau NUPTK. Karena email
     * dijamin unique + not-null (identitas wajib satu-satunya), pencarian
     * lintas kolom di sini diterjemahkan kembali ke kredensial email supaya
     * Auth::attempt() tetap satu jalur seperti sebelumnya. Bila tidak ada
     * user yang cocok, input mentah diteruskan apa adanya agar tetap gagal
     * dengan pesan generik yang sama (tidak membocorkan identitas mana yang
     * terdaftar).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[\SensitiveParameter] array $data): array
    {
        $login = trim((string) $data['login']);

        $user = User::query()
            ->where('email', $login)
            ->orWhere('username', $login)
            ->orWhere('nidn', $login)
            ->orWhere('nip', $login)
            ->orWhere('nuptk', $login)
            ->first();

        return [
            'email' => $user?->email ?? $login,
            'password' => $data['password'],
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.login' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }
}
