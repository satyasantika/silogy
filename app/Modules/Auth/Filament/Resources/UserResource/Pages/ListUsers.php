<?php

namespace App\Modules\Auth\Filament\Resources\UserResource\Pages;

use App\Models\Role;
use App\Models\User;
use App\Modules\Auth\Filament\Resources\UserResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

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
                ->modalWidth(Width::SixExtraLarge)
                ->modalSubmitAction(false)
                ->schema([
                    Wizard::make([
                        Step::make('Tempel data')
                            ->icon(Heroicon::OutlinedClipboard)
                            ->schema([
                                Textarea::make('rows')
                                    ->label('Data pengguna')
                                    ->required()
                                    ->rows(10)
                                    ->live()
                                    ->placeholder("Budi Santoso\tbudisantoso\tRahasiaKuat123\tbudi@kampus.ac.id\tDosen Pengampu")
                                    ->helperText('Satu pengguna per baris, kolom: name, username, password, email, role. Pemisah kolom: tab (hasil copy dari Excel) atau karakter |. Lebih dari satu role dipisahkan koma.'),
                            ]),
                        Step::make('Preview & konfirmasi')
                            ->icon(Heroicon::OutlinedEye)
                            ->schema([
                                Placeholder::make('preview')
                                    ->hiddenLabel()
                                    ->content(fn (Get $get): HtmlString => static::renderImportPreview((string) $get('rows'))),
                                Radio::make('mode_duplikat')
                                    ->label('Tindakan untuk data duplikat')
                                    ->options([
                                        'lewati' => 'Batal diinputkan (lewati duplikat)',
                                        'timpa' => 'Timpa data lama (perbarui nama, password, email, dan role)',
                                    ])
                                    ->default('lewati')
                                    ->required(),
                            ]),
                    ])
                        ->submitAction(new HtmlString(Blade::render(
                            '<x-filament::button type="submit" icon="heroicon-m-arrow-down-tray">Impor sekarang</x-filament::button>'
                        ))),
                ])
                ->action(function (array $data): void {
                    $this->importUsers((string) $data['rows'], (string) ($data['mode_duplikat'] ?? 'lewati'));
                }),
            CreateAction::make(),
        ];
    }

    /**
     * Parse baris tempelan (tab dari Excel atau pipe) menjadi baris terstruktur
     * berstatus: baru | duplikat | invalid.
     *
     * @return list<array{
     *     line: int, name: string, username: string, password: string, email: string,
     *     role_input: string, roles: list<string>,
     *     status: string, keterangan: string, existing_id: ?string
     * }>
     */
    public static function parseImportRows(string $raw): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($raw)) ?: [];
        $allowSuperAdmin = auth()->user()?->hasRole('Super Admin') ?? false;
        $roleNamesTersedia = Role::query()->pluck('name');

        $rows = [];
        $seenUsernames = [];
        $seenEmails = [];

        foreach ($lines as $index => $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $separator = str_contains($line, "\t") ? "\t" : '|';
            $parts = array_map('trim', explode($separator, $line));

            $row = [
                'line' => $index + 1,
                'name' => $parts[0] ?? '',
                'username' => $parts[1] ?? '',
                'password' => $parts[2] ?? '',
                'email' => $parts[3] ?? '',
                'role_input' => $parts[4] ?? '',
                'roles' => [],
                'status' => 'baru',
                'keterangan' => '',
                'existing_id' => null,
            ];

            $invalid = function (string $alasan) use (&$rows, $row): void {
                $row['status'] = 'invalid';
                $row['keterangan'] = $alasan;
                $rows[] = $row;
            };

            if (count($parts) !== 5) {
                $invalid('Harus 5 kolom (name, username, password, email, role).');

                continue;
            }

            if (in_array('', [$row['name'], $row['username'], $row['password'], $row['email'], $row['role_input']], true)) {
                $invalid('Semua kolom wajib diisi.');

                continue;
            }

            if (! preg_match('/^[A-Za-z0-9_-]+$/', $row['username'])) {
                $invalid('Username hanya boleh huruf, angka, strip, dan garis bawah.');

                continue;
            }

            if (! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                $invalid('Email tidak valid.');

                continue;
            }

            $usernameKey = mb_strtolower($row['username']);
            $emailKey = mb_strtolower($row['email']);

            if (isset($seenUsernames[$usernameKey]) || isset($seenEmails[$emailKey])) {
                $invalid('Duplikat dengan baris lain di data yang ditempel.');

                continue;
            }

            $seenUsernames[$usernameKey] = true;
            $seenEmails[$emailKey] = true;

            $roleNames = collect(explode(',', $row['role_input']))
                ->map(fn (string $role): string => trim($role))
                ->filter()
                ->values();

            $missing = $roleNames->diff($roleNamesTersedia);

            if ($missing->isNotEmpty()) {
                $invalid('Role tidak ditemukan: '.$missing->join(', ').'.');

                continue;
            }

            if (! $allowSuperAdmin && $roleNames->contains('Super Admin')) {
                $invalid('Hanya Super Admin yang dapat memberikan role Super Admin.');

                continue;
            }

            $row['roles'] = $roleNames->all();

            $byUsername = User::query()->where('username', $row['username'])->first();
            $byEmail = User::query()->where('email', $row['email'])->first();

            if ($byUsername && $byEmail && ! $byUsername->is($byEmail)) {
                $invalid('Username dan email menunjuk dua pengguna berbeda yang sudah terdaftar.');

                continue;
            }

            $existing = $byUsername ?? $byEmail;

            if ($existing) {
                $row['status'] = 'duplikat';
                $row['keterangan'] = $byUsername
                    ? 'Username sudah terdaftar.'
                    : 'Email sudah terdaftar.';
                $row['existing_id'] = $existing->id;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    public static function renderImportPreview(string $raw): HtmlString
    {
        $rows = static::parseImportRows($raw);

        if ($rows === []) {
            return new HtmlString('<p class="text-sm">Belum ada data yang dapat dibaca. Kembali ke langkah sebelumnya dan tempel data terlebih dahulu.</p>');
        }

        $jumlah = ['baru' => 0, 'duplikat' => 0, 'invalid' => 0];
        $body = '';

        foreach ($rows as $row) {
            $jumlah[$row['status']]++;

            [$badge, $warna] = match ($row['status']) {
                'baru' => ['Baru', '#16a34a'],
                'duplikat' => ['Duplikat', '#d97706'],
                default => ['Invalid', '#dc2626'],
            };

            $body .= '<tr style="border-top:1px solid rgba(128,128,128,.25);">'
                .'<td style="padding:4px 8px;white-space:nowrap;">'.$row['line'].'</td>'
                .'<td style="padding:4px 8px;">'.e($row['name']).'</td>'
                .'<td style="padding:4px 8px;">'.e($row['username']).'</td>'
                .'<td style="padding:4px 8px;">'.e($row['email']).'</td>'
                .'<td style="padding:4px 8px;">'.e($row['role_input']).'</td>'
                .'<td style="padding:4px 8px;white-space:nowrap;"><span style="font-weight:600;color:'.$warna.';">'.$badge.'</span>'
                .($row['keterangan'] !== '' ? '<br><span style="font-size:11px;opacity:.8;">'.e($row['keterangan']).'</span>' : '')
                .'</td></tr>';
        }

        $ringkasan = sprintf(
            '<p class="text-sm" style="margin-bottom:8px;"><strong>%d baris terbaca:</strong> '
            .'<span style="color:#16a34a;font-weight:600;">%d baru</span> · '
            .'<span style="color:#d97706;font-weight:600;">%d duplikat</span> · '
            .'<span style="color:#dc2626;font-weight:600;">%d invalid</span>. '
            .'Baris invalid tidak akan diimpor; nasib baris duplikat mengikuti pilihan di bawah.</p>',
            count($rows),
            $jumlah['baru'],
            $jumlah['duplikat'],
            $jumlah['invalid'],
        );

        $tabel = '<div style="overflow-x:auto;max-height:320px;overflow-y:auto;">'
            .'<table style="width:100%;font-size:12px;border-collapse:collapse;">'
            .'<thead><tr style="text-align:left;">'
            .'<th style="padding:4px 8px;">Baris</th><th style="padding:4px 8px;">Nama</th>'
            .'<th style="padding:4px 8px;">Username</th><th style="padding:4px 8px;">Email</th>'
            .'<th style="padding:4px 8px;">Role</th><th style="padding:4px 8px;">Status</th>'
            .'</tr></thead><tbody>'.$body.'</tbody></table></div>';

        return new HtmlString($ringkasan.$tabel);
    }

    protected function importUsers(string $raw, string $modeDuplikat): void
    {
        $rows = static::parseImportRows($raw);

        $dibuat = 0;
        $diperbarui = 0;
        $dilewati = 0;
        $gagal = [];

        DB::transaction(function () use ($rows, $modeDuplikat, &$dibuat, &$diperbarui, &$dilewati, &$gagal): void {
            foreach ($rows as $row) {
                if ($row['status'] === 'invalid') {
                    $gagal[] = "Baris {$row['line']}: {$row['keterangan']}";

                    continue;
                }

                if ($row['status'] === 'duplikat') {
                    if ($modeDuplikat !== 'timpa') {
                        $dilewati++;

                        continue;
                    }

                    $user = User::query()->find($row['existing_id']);

                    if (! $user) {
                        $gagal[] = "Baris {$row['line']}: pengguna lama tidak ditemukan.";

                        continue;
                    }

                    $user->update([
                        'full_name' => $row['name'],
                        'username' => $row['username'],
                        'email' => $row['email'],
                        'password' => $row['password'],
                    ]);
                    $user->syncRoles($row['roles']);
                    $diperbarui++;

                    continue;
                }

                $user = User::create([
                    'full_name' => $row['name'],
                    'username' => $row['username'],
                    'password' => $row['password'],
                    'email' => $row['email'],
                ]);

                $user->forceFill(['email_verified_at' => now()])->save();
                $user->syncRoles($row['roles']);
                $dibuat++;
            }
        });

        $ringkasan = sprintf(
            'Berhasil dibuat: %d · Diperbarui (timpa): %d · Dilewati (duplikat): %d · Gagal: %d',
            $dibuat,
            $diperbarui,
            $dilewati,
            count($gagal),
        );

        $detailGagal = $gagal === []
            ? ''
            : "\n".implode("\n", array_slice($gagal, 0, 8)).(count($gagal) > 8 ? "\n…" : '');

        $notification = Notification::make()
            ->title('Impor pengguna selesai')
            ->body($ringkasan.$detailGagal);

        if ($dibuat + $diperbarui > 0 && $gagal === []) {
            $notification->success();
        } elseif ($dibuat + $diperbarui > 0) {
            $notification->warning()->persistent();
        } else {
            $notification->danger()->persistent();
        }

        $notification->send();
    }
}
