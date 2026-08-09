<?php

namespace App\Modules\Auth\Filament\Resources\UserResource\Pages;

use App\Models\Role;
use App\Models\User;
use App\Modules\Auth\Filament\Resources\UserResource;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Models\AcademicUnitUser;
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
            ['key' => 'email', 'label' => 'email', 'wajib' => true],
            ['key' => 'password', 'label' => 'password', 'wajib' => true],
            ['key' => 'role', 'label' => 'role', 'wajib' => true],
            ['key' => 'kode_prodi', 'label' => 'kode prodi', 'wajib' => false],
            ['key' => 'username', 'label' => 'username', 'wajib' => false],
            ['key' => 'nip', 'label' => 'nip', 'wajib' => false],
            ['key' => 'nidn', 'label' => 'nidn', 'wajib' => false],
            ['key' => 'nuptk', 'label' => 'nuptk', 'wajib' => false],
        ];
    }

    protected function importHelperNote(): string
    {
        return 'Lebih dari satu role dipisahkan titik koma. Kode prodi opsional, lebih dari satu dipisahkan koma — '
            .'bila diisi, pengguna direlasikan ke unit tsb; status Pimpinan/Tim Kurikulum pada relasi mengikuti role yang diberikan. '
            .'Username, NIP, NIDN, dan NUPTK opsional — kosongkan bila tidak ada, akan tersimpan NULL.';
    }

    /**
     * @return list<string>
     */
    protected function importExampleRows(): array
    {
        return [
            "Budi Santoso\tbudi@silogy.test\tRahasiaKuat123\tDosen Pengampu\t\tbudis\t\t\t",
            "Siti Aminah\tsiti@silogy.test\tRahasiaKuat456\tTim Kurikulum;Dosen Pengampu\t2151,2122,2121\tsitiaminah\t198501012010122001\t0012345678\t1234567890123456",
        ];
    }

    protected function resolveImportRow(array $data, array $context): array
    {
        if (! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return ['status' => 'invalid', 'keterangan' => 'Email tidak valid.'];
        }

        if ($data['username'] !== '' && ! preg_match('/^[A-Za-z0-9_-]+$/', $data['username'])) {
            return ['status' => 'invalid', 'keterangan' => 'Username hanya boleh huruf, angka, strip, dan garis bawah.'];
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

        $kodeProdi = $this->parseKodeProdi($data['kode_prodi'] ?? '');

        if ($kodeProdi->isNotEmpty()) {
            $ditemukan = AcademicUnit::query()
                ->whereIn('code', $kodeProdi)
                ->where('type', 'study_program')
                ->pluck('code');

            $kodeTidakDitemukan = $kodeProdi->diff($ditemukan);

            if ($kodeTidakDitemukan->isNotEmpty()) {
                return ['status' => 'invalid', 'keterangan' => 'Kode prodi tidak ditemukan: '.$kodeTidakDitemukan->join(', ').'.'];
            }
        }

        $byEmail = User::query()->where('email', $data['email'])->first();

        $konflik = $this->cariKonflikIdentitasImport($data, $byEmail);

        if ($konflik !== null) {
            return ['status' => 'invalid', 'keterangan' => $konflik];
        }

        $dedup = mb_strtolower($data['email']);

        if ($byEmail) {
            return [
                'status' => 'duplikat',
                'keterangan' => 'Email sudah terdaftar.',
                'existing_id' => $byEmail->id,
                'dedup' => $dedup,
            ];
        }

        return ['status' => 'baru', 'keterangan' => '', 'dedup' => $dedup];
    }

    /**
     * Cek konflik untuk tiap field opsional (username/nip/nidn/nuptk) yang
     * terisi di baris ini: bila sudah dipakai pengguna lain — selain
     * pengguna yang sudah dicocokkan lewat email pada baris update —
     * tandai baris sebagai invalid.
     *
     * @param  array<string, string>  $data
     */
    protected function cariKonflikIdentitasImport(array $data, ?User $byEmail): ?string
    {
        $fieldLabel = [
            'username' => 'Username',
            'nip' => 'NIP',
            'nidn' => 'NIDN',
            'nuptk' => 'NUPTK',
        ];

        foreach ($fieldLabel as $field => $label) {
            $value = $data[$field] ?? '';

            if ($value === '') {
                continue;
            }

            $pemilik = User::query()->where($field, $value)->first();

            if ($pemilik && (! $byEmail || ! $pemilik->is($byEmail))) {
                return "{$label} sudah dipakai pengguna lain.";
            }
        }

        return null;
    }

    protected function createImportRow(array $data, array $context): void
    {
        $user = User::create($this->importAttributesFromRow($data));

        $user->forceFill(['email_verified_at' => now()])->save();

        $roleNames = $this->parseRoleNames($data['role']);
        $user->syncRoles($roleNames->all());
        $this->relasikanProdi($user, $data, $roleNames);
    }

    /**
     * @param  array<string, string>  $data
     * @param  array<string, mixed>  $context
     */
    protected function updateImportRow(string $existingId, array $data, array $context): void
    {
        $user = User::query()->findOrFail($existingId);

        $user->update($this->importAttributesFromRow($data));

        $roleNames = $this->parseRoleNames($data['role']);
        $user->syncRoles($roleNames->all());
        $this->relasikanProdi($user, $data, $roleNames);
    }

    /**
     * Kolom opsional (username/nip/nidn/nuptk) yang kosong di baris tempelan
     * disimpan sebagai NULL, bukan string kosong.
     *
     * @param  array<string, string>  $data
     * @return array<string, ?string>
     */
    protected function importAttributesFromRow(array $data): array
    {
        return [
            'full_name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'username' => $data['username'] !== '' ? $data['username'] : null,
            'nip' => $data['nip'] !== '' ? $data['nip'] : null,
            'nidn' => $data['nidn'] !== '' ? $data['nidn'] : null,
            'nuptk' => $data['nuptk'] !== '' ? $data['nuptk'] : null,
        ];
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

    /**
     * @return Collection<int, non-falsy-string>
     */
    protected function parseKodeProdi(string $kodeProdiInput): Collection
    {
        return collect(explode(',', $kodeProdiInput))
            ->map(fn (string $kode): string => trim($kode))
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * Merelasikan pengguna ke unit prodi yang disebut kolom kode_prodi,
     * dengan status_pimpinan/status_tim_kurikulum mengikuti role baris
     * ini. Bersifat ADDITIVE (sesuai keputusan produk): hanya menyentuh
     * pivot untuk unit yang disebut di baris ini — relasi ke unit lain
     * yang sudah dimiliki pengguna sebelumnya (tidak disebut di sini)
     * dibiarkan apa adanya, tidak dihapus. Berbeda dari role (syncRoles
     * = full-replace) secara sengaja, karena satu baris impor biasanya
     * hanya mewakili sebagian penugasan unit seorang pengguna, bukan
     * daftar lengkapnya.
     *
     * @param  array<string, string>  $data
     * @param  Collection<int, non-falsy-string>  $roleNames
     */
    protected function relasikanProdi(User $user, array $data, Collection $roleNames): void
    {
        $kodeProdi = $this->parseKodeProdi($data['kode_prodi'] ?? '');

        if ($kodeProdi->isEmpty()) {
            return;
        }

        $unitIds = AcademicUnit::query()
            ->whereIn('code', $kodeProdi)
            ->where('type', 'study_program')
            ->pluck('id');

        foreach ($unitIds as $unitId) {
            AcademicUnitUser::query()->updateOrCreate(
                ['academic_unit_id' => $unitId, 'user_id' => $user->id],
                [
                    'status_pimpinan' => $roleNames->contains('Pimpinan'),
                    'status_tim_kurikulum' => $roleNames->contains('Tim Kurikulum'),
                ],
            );
        }
    }
}
