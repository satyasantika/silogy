<?php

namespace App\Modules\Auth\Filament\Resources\UserResource\Pages;

use App\Models\Role;
use App\Models\User;
use App\Modules\Auth\Filament\Resources\UserResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('bulkImport')
                ->label('Impor massal')
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->color('gray')
                ->visible(fn (): bool => auth()->user()?->can('create', User::class) ?? false)
                ->modalHeading('Impor pengguna massal')
                ->modalDescription('Tempel data dari spreadsheet atau teks, satu pengguna per baris.')
                ->modalSubmitActionLabel('Impor')
                ->form([
                    Textarea::make('rows')
                        ->label('Data pengguna')
                        ->required()
                        ->rows(10)
                        ->placeholder("Budi Santoso|budisantoso|RahasiaKuat123|budi@kampus.ac.id|Dosen Pengampu\nSiti Aminah|sitiaminah|RahasiaKuat456|siti@kampus.ac.id|Kaprodi, Dosen Pengampu")
                        ->helperText('Format per baris: name|username|password|email|role. Lebih dari satu role dipisahkan koma.'),
                ])
                ->action(function (array $data): void {
                    $this->importUsers($data['rows']);
                }),
            CreateAction::make(),
        ];
    }

    protected function importUsers(string $rows): void
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($rows)) ?: [];
        $allowSuperAdmin = auth()->user()?->hasRole('Super Admin') ?? false;

        $errors = [];
        $prepared = [];
        $seenUsernames = [];
        $seenEmails = [];

        foreach ($lines as $index => $line) {
            $lineNumber = $index + 1;
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $line));

            if (count($parts) !== 5) {
                $errors[] = "Baris {$lineNumber}: harus 5 kolom (name|username|password|email|role).";

                continue;
            }

            [$name, $username, $password, $email, $roleInput] = $parts;

            if (in_array('', [$name, $username, $password, $email, $roleInput], true)) {
                $errors[] = "Baris {$lineNumber}: semua kolom wajib diisi.";

                continue;
            }

            if (! preg_match('/^[A-Za-z0-9_-]+$/', $username)) {
                $errors[] = "Baris {$lineNumber}: username hanya boleh huruf, angka, strip, dan garis bawah.";

                continue;
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Baris {$lineNumber}: email tidak valid.";

                continue;
            }

            $usernameKey = mb_strtolower($username);
            $emailKey = mb_strtolower($email);

            if (isset($seenUsernames[$usernameKey]) || isset($seenEmails[$emailKey])) {
                $errors[] = "Baris {$lineNumber}: username atau email duplikat di dalam data yang ditempel.";

                continue;
            }

            $seenUsernames[$usernameKey] = true;
            $seenEmails[$emailKey] = true;

            if (User::query()->where('username', $username)->orWhere('email', $email)->exists()) {
                $errors[] = "Baris {$lineNumber}: username atau email sudah terdaftar.";

                continue;
            }

            $roleNames = collect(explode(',', $roleInput))
                ->map(fn (string $role): string => trim($role))
                ->filter()
                ->values();

            $roles = Role::query()->whereIn('name', $roleNames)->get();
            $missing = $roleNames->diff($roles->pluck('name'));

            if ($missing->isNotEmpty()) {
                $errors[] = "Baris {$lineNumber}: role tidak ditemukan: {$missing->join(', ')}.";

                continue;
            }

            if (! $allowSuperAdmin && $roles->contains('name', 'Super Admin')) {
                $errors[] = "Baris {$lineNumber}: hanya Super Admin yang dapat memberikan role Super Admin.";

                continue;
            }

            $prepared[] = [
                'full_name' => $name,
                'username' => $username,
                'password' => $password,
                'email' => $email,
                'roles' => $roles,
            ];
        }

        if ($errors !== []) {
            Notification::make()
                ->title('Impor dibatalkan, perbaiki data berikut')
                ->body(implode("\n", array_slice($errors, 0, 10)).(count($errors) > 10 ? "\n…" : ''))
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        if ($prepared === []) {
            Notification::make()
                ->title('Tidak ada baris data untuk diimpor')
                ->warning()
                ->send();

            return;
        }

        DB::transaction(function () use ($prepared): void {
            foreach ($prepared as $row) {
                /** @var Collection<int, Role> $roles */
                $roles = $row['roles'];

                $user = User::create([
                    'full_name' => $row['full_name'],
                    'username' => $row['username'],
                    'password' => $row['password'],
                    'email' => $row['email'],
                ]);

                $user->forceFill(['email_verified_at' => now()])->save();
                $user->syncRoles($roles);
            }
        });

        Notification::make()
            ->title(count($prepared).' pengguna berhasil diimpor')
            ->success()
            ->send();
    }
}
