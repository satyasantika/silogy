<?php

namespace App\Modules\Auth\Filament\Resources\UserResource\Pages;

use App\Models\Role;
use App\Models\User;
use App\Modules\Auth\Filament\Resources\UserResource;
use App\Support\Filament\Concerns\HasImporMassal;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Collection;

class ListUsers extends ListRecords
{
    use HasImporMassal;

    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->makeImporMassalAction()
                ->visible(fn (): bool => auth()->user()?->can('create', User::class) ?? false),
            CreateAction::make(),
        ];
    }

    protected function importModalHeading(): string
    {
        return 'Impor pengguna massal';
    }

    protected function importColumns(): array
    {
        return [
            ['key' => 'name', 'label' => 'name', 'wajib' => true],
            ['key' => 'username', 'label' => 'username', 'wajib' => true],
            ['key' => 'password', 'label' => 'password', 'wajib' => true],
            ['key' => 'email', 'label' => 'email', 'wajib' => true],
            ['key' => 'role', 'label' => 'role', 'wajib' => true],
        ];
    }

    protected function importHelperNote(): string
    {
        return 'Lebih dari satu role dipisahkan titik koma.';
    }

    /**
     * @return list<string>
     */
    protected function importExampleRows(): array
    {
        return [
            "Budi Santoso\tbudis\tRahasiaKuat123\tbudi@silogy.test\tDosen Pengampu",
            "Siti Aminah\tsitiaminah\tRahasiaKuat456\tsiti@silogy.test\tTim Kurikulum;Dosen Pengampu",
        ];
    }

    protected function resolveImportRow(array $data, array $context): array
    {
        if (! preg_match('/^[A-Za-z0-9_-]+$/', $data['username'])) {
            return ['status' => 'invalid', 'keterangan' => 'Username hanya boleh huruf, angka, strip, dan garis bawah.'];
        }

        if (! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return ['status' => 'invalid', 'keterangan' => 'Email tidak valid.'];
        }

        $roleNames = $this->parseRoleNames($data['role']);
        $missing = $roleNames->diff(Role::query()->pluck('name'));

        if ($missing->isNotEmpty()) {
            return ['status' => 'invalid', 'keterangan' => 'Role tidak ditemukan: '.$missing->join(', ').'.'];
        }

        $allowSuperAdmin = auth()->user()?->hasRole('Super Admin') ?? false;

        if (! $allowSuperAdmin && $roleNames->contains('Super Admin')) {
            return ['status' => 'invalid', 'keterangan' => 'Hanya Super Admin yang dapat memberikan role Super Admin.'];
        }

        $byUsername = User::query()->where('username', $data['username'])->first();
        $byEmail = User::query()->where('email', $data['email'])->first();

        if ($byUsername && $byEmail && ! $byUsername->is($byEmail)) {
            return ['status' => 'invalid', 'keterangan' => 'Username dan email menunjuk dua pengguna berbeda yang sudah terdaftar.'];
        }

        $dedup = mb_strtolower($data['username']).'/'.mb_strtolower($data['email']);
        $existing = $byUsername ?? $byEmail;

        if ($existing) {
            return [
                'status' => 'duplikat',
                'keterangan' => $byUsername ? 'Username sudah terdaftar.' : 'Email sudah terdaftar.',
                'existing_id' => $existing->id,
                'dedup' => $dedup,
            ];
        }

        return ['status' => 'baru', 'keterangan' => '', 'dedup' => $dedup];
    }

    protected function createImportRow(array $data, array $context): void
    {
        $user = User::create([
            'full_name' => $data['name'],
            'username' => $data['username'],
            'password' => $data['password'],
            'email' => $data['email'],
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();
        $user->syncRoles($this->parseRoleNames($data['role'])->all());
    }

    /**
     * @param  array<string, string>  $data
     * @param  array<string, mixed>  $context
     */
    protected function updateImportRow(string $existingId, array $data, array $context): void
    {
        $user = User::query()->findOrFail($existingId);

        $user->update([
            'full_name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
        $user->syncRoles($this->parseRoleNames($data['role'])->all());
    }

    /**
     * @return Collection<int, non-falsy-string>
     */
    protected function parseRoleNames(string $roleInput): Collection
    {
        return collect(explode(';', $roleInput))
            ->map(fn (string $role): string => trim($role))
            ->filter()
            ->values();
    }
}
