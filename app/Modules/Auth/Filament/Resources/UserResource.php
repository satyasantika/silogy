<?php

namespace App\Modules\Auth\Filament\Resources;

use App\Models\Permission;
use App\Models\User;
use App\Modules\Auth\Filament\Resources\UserResource\Pages\CreateUser;
use App\Modules\Auth\Filament\Resources\UserResource\Pages\EditUser;
use App\Modules\Auth\Filament\Resources\UserResource\Pages\ListUsers;
use App\Modules\Auth\Support\DomainPermissionLabels;
use App\Modules\Auth\Support\PeranUnitFormFields;
use App\Modules\Institusi\Filament\Resources\AcademicUnitResource;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Notifications\ResetPassword as ResetPasswordNotification;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\HtmlString;
use STS\FilamentImpersonate\Actions\Impersonate;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'pengguna';

    protected static ?string $pluralModelLabel = 'pengguna';

    protected static ?string $recordTitleAttribute = 'full_name';

    protected static ?string $slug = 'users';

    // Super Admin melihat menu ini datar (tanpa grup); role lain tetap
    // di bawah grup Autentikasi seperti biasa.
    public static function getNavigationGroup(): ?string
    {
        return auth()->user()?->hasRole('Super Admin') ? null : 'Autentikasi';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitas')
                    ->schema([
                        TextInput::make('prefix')
                            ->label('Gelar depan')
                            ->maxLength(30),
                        TextInput::make('full_name')
                            ->label('Nama lengkap')
                            ->required()
                            ->maxLength(150),
                        TextInput::make('suffix')
                            ->label('Gelar belakang')
                            ->maxLength(50),
                        Select::make('jenis_kelamin')
                            ->label('Jenis kelamin')
                            ->options([
                                'L' => 'Laki-laki',
                                'P' => 'Perempuan',
                            ]),
                        TextInput::make('nidn')
                            ->label('NIDN')
                            ->maxLength(20)
                            ->unique(ignoreRecord: true),
                        TextInput::make('nip')
                            ->label('NIP')
                            ->maxLength(20)
                            ->unique(ignoreRecord: true),
                        TextInput::make('nuptk')
                            ->label('NUPTK')
                            ->maxLength(20)
                            ->unique(ignoreRecord: true),
                        TextInput::make('nomor_wa')
                            ->label('Nomor WhatsApp')
                            ->tel()
                            ->maxLength(20)
                            ->placeholder('628xxxxxxxxxx'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Akun')
                    ->description('(email dan password wajib, username opsional)')
                    ->schema([
                        TextInput::make('username')
                            ->label('Username')
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->alphaDash(),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->maxLength(150)
                            ->unique(ignoreRecord: true),
                        TextInput::make('password')
                            ->label('Kata sandi')
                            ->password()
                            ->revealable()
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->helperText(fn (string $operation): ?string => $operation === 'edit'
                                ? 'Kosongkan jika tidak ingin mengubah kata sandi.'
                                : null)
                            ->hintAction(
                                static::makeResetPasswordAction()
                                    ->visible(fn (string $operation, ?User $record): bool => $operation === 'edit' && $record !== null)
                            ),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed()
                    ->columnSpanFull(),

                Section::make('Role')
                    ->description('Permission')
                    ->schema([
                        Select::make('roles')
                            ->label('Role')
                            ->relationship(
                                'roles',
                                'name',
                                modifyQueryUsing: fn (Builder $query): Builder => auth()->user()?->hasRole('Super Admin')
                                    ? $query
                                    : $query->where('name', '!=', 'Super Admin'),
                            )
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->required(),
                        Select::make('permissions')
                            ->label('Permission langsung')
                            ->helperText('Permission tambahan di luar role. Hanya Super Admin yang dapat mengatur.')
                            ->relationship('permissions', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->getOptionLabelFromRecordUsing(
                                fn (Permission $record): string => DomainPermissionLabels::label($record->name)
                            )
                            ->dehydrated(fn (): bool => auth()->user()?->can('kelola_permission') ?? false)
                            ->visible(fn (): bool => auth()->user()?->can('kelola_permission') ?? false),
                    ])
                    ->columnSpanFull(),

                Section::make('Penugasan Unit')
                    ->schema([
                        Repeater::make('academicUnitUsers')
                            ->label('Penugasan unit akademik')
                            ->relationship()
                            ->schema([
                                Select::make('academic_unit_id')
                                    ->label('Unit akademik')
                                    ->options(fn (Select $component): array => static::opsiUnitPenugasan(
                                        static::unitIdTerpilihDiRepeater($component),
                                        $component->getState(),
                                    ))
                                    ->searchable()
                                    ->required()
                                    ->distinct()
                                    ->live()
                                    ->placeholder('Pilih unit yang belum ditugaskan')
                                    ->helperText(fn (Select $component): ?string => static::opsiUnitPenugasan(
                                        static::unitIdTerpilihDiRepeater($component),
                                        $component->getState(),
                                    ) === [] && blank($component->getState())
                                        ? 'Semua unit akademik sudah ditugaskan pada kartu lain.'
                                        : null)
                                    ->columnSpanFull(),
                                Toggle::make('status_pimpinan')
                                    ->label('Pimpinan unit')
                                    ->inline(false)
                                    ->live(),
                                Toggle::make('status_tim_kurikulum')
                                    ->label('Tim kurikulum')
                                    ->inline(false)
                                    ->live(),
                                TextInput::make('jabatan')
                                    ->label('Jabatan')
                                    ->maxLength(100)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->collapsible()
                            ->collapsed(fn (?Schema $item): bool => static::penugasanUnitHarusDilipat($item))
                            ->itemLabel(fn (array $state): HtmlString => static::labelPenugasanUnit($state))
                            ->addActionLabel('Tambah penugasan unit')
                            ->addable(fn (Repeater $component): bool => static::masihBisaTambahPenugasan($component))
                            ->addAction(function (Action $action): Action {
                                return $action->after(function (Repeater $component): void {
                                    // Pulihkan aturan per-kartu: yang sudah tersimpan tetap terlipat,
                                    // kartu penugasan baru tetap terbuka setelah tombol tambah.
                                    $component->collapsed(
                                        fn (?Schema $item): bool => static::penugasanUnitHarusDilipat($item),
                                        shouldMakeComponentCollapsible: false,
                                    );
                                });
                            })
                            ->deleteAction(fn (Action $action): Action => $action
                                ->requiresConfirmation()
                                ->modalHeading('Hapus penugasan unit?')
                                ->modalDescription(function (array $arguments, Repeater $component): string {
                                    $item = $component->getRawState()[$arguments['item'] ?? ''] ?? [];
                                    $unitId = is_array($item) ? ($item['academic_unit_id'] ?? null) : null;
                                    $nama = filled($unitId)
                                        ? (AcademicUnit::query()->find($unitId)?->nama_lengkap ?? 'unit ini')
                                        : 'penugasan baru ini';

                                    return "Penugasan {$nama} akan dihapus dari pengguna ini.";
                                })
                                ->modalSubmitActionLabel('Ya, hapus')
                                ->modalCancelActionLabel('Batal'))
                            ->defaultItems(0),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Opsi Select unit pada satu kartu penugasan. Unit yang sudah dipilih
     * di kartu lain dikecualikan; unit kartu ini tetap ada agar label Select utuh.
     *
     * @param  iterable<int, mixed>  $unitIdTerpilih
     * @return array<string, string>
     */
    public static function opsiUnitPenugasan(iterable $unitIdTerpilih, mixed $unitIdKartuIni = null): array
    {
        $kecualikan = collect($unitIdTerpilih)
            ->filter()
            ->map(fn (mixed $id): string => (string) $id)
            ->reject(fn (string $id): bool => filled($unitIdKartuIni) && $id === (string) $unitIdKartuIni)
            ->unique()
            ->values()
            ->all();

        return AcademicUnit::query()
            ->orderBy('nama')
            ->when($kecualikan !== [], fn (Builder $query): Builder => $query->whereNotIn('id', $kecualikan))
            ->get()
            ->mapWithKeys(fn (AcademicUnit $unit): array => [
                $unit->id => AcademicUnitResource::formatUnitOptionLabel($unit),
            ])
            ->all();
    }

    /**
     * @return Collection<int, string>
     */
    public static function unitIdTerpilihDiRepeater(Select $component): Collection
    {
        $repeater = $component->getParentRepeater();

        if (! $repeater instanceof Repeater) {
            return collect();
        }

        return collect($repeater->getRawState() ?? [])
            ->pluck('academic_unit_id')
            ->filter()
            ->map(fn (mixed $id): string => (string) $id)
            ->values();
    }

    public static function penugasanUnitHarusDilipat(?Schema $item): bool
    {
        if (! $item instanceof Schema) {
            return true;
        }

        $record = $item->getRecord();

        return $record instanceof Model && $record->exists;
    }

    public static function masihBisaTambahPenugasan(Repeater $component): bool
    {
        $items = collect($component->getRawState() ?? []);
        $terisi = $items->pluck('academic_unit_id')->filter()->unique()->count();
        $kosong = $items->filter(fn (mixed $item): bool => is_array($item) && blank($item['academic_unit_id'] ?? null))->count();

        return ($terisi + $kosong) < AcademicUnit::query()->count();
    }

    /**
     * Label accordion penugasan: nama unit + badge Pimpinan / Tim kurikulum.
     *
     * @param  array<string, mixed>  $state
     */
    public static function labelPenugasanUnit(array $state): HtmlString
    {
        $unitId = $state['academic_unit_id'] ?? null;

        if (blank($unitId)) {
            return new HtmlString('<span style="opacity:.65;">Penugasan baru</span>');
        }

        $nama = AcademicUnit::query()->find($unitId)?->nama_lengkap ?? 'Unit akademik';
        $html = '<span>'.e($nama).'</span>';

        if (filter_var($state['status_pimpinan'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $html .= '<span style="display:inline-flex;align-items:center;margin-inline-start:8px;padding:2px 8px;border-radius:999px;font-size:10px;font-weight:600;letter-spacing:.02em;background:rgba(37,99,235,.12);color:#1d4ed8;">Pimpinan</span>';
        }

        if (filter_var($state['status_tim_kurikulum'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $html .= '<span style="display:inline-flex;align-items:center;margin-inline-start:6px;padding:2px 8px;border-radius:999px;font-size:10px;font-weight:600;letter-spacing:.02em;background:rgba(0,112,0,.14);color:#0b3914;">Tim kurikulum</span>';
        }

        return new HtmlString($html);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query->with(['roles', 'academicUnitUsers'])
            )
            ->columns([
                TextColumn::make('full_name')
                    ->label('Nama lengkap')
                    ->searchable(['full_name', 'username', 'email'])
                    ->sortable()
                    ->description(fn (User $record): ?string => $record->username),

                TextColumn::make('username')
                    ->label('Username')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),

                TextColumn::make('nip')
                    ->label('NIP')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),

                TextColumn::make('nuptk')
                    ->label('NUPTK')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->badge()
                    ->searchable(),

                TextColumn::make('roles_list')
                    ->label('Role')
                    ->state(fn (User $record): string => $record->roles->pluck('name')->join(', ') ?: '—'),

                TextColumn::make('academic_unit_users_count')
                    ->label('Jumlah unit')
                    ->counts('academicUnitUsers')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('email_verified_at')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? 'Aktif' : 'Nonaktif')
                    ->color(fn (?string $state): string => $state ? 'success' : 'danger'),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->label('Role')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload(),
                SelectFilter::make('unit_type')
                    ->label('Tipe unit penugasan')
                    ->options(AcademicUnitResource::typeOptions())
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['value'] ?? null)) {
                            return $query;
                        }

                        return $query->whereHas(
                            'academicUnitUsers.academicUnit',
                            fn (Builder $unitQuery): Builder => $unitQuery->where('type', $data['value'])
                        );
                    }),
            ])
            ->recordActions([
                Impersonate::make()
                    ->iconButton()
                    ->tooltip('Peniruan')
                    // Closure (bukan string statis) supaya bisa membersihkan sisa
                    // peran/unit sesi admin sebelumnya dan mengarahkan ke gerbang
                    // Pilih Peran & Unit bila target multi-role — lihat
                    // PeranUnitFormFields::redirectUrlAfterImpersonateStart().
                    // '/dashboard' saja tidak cukup: Livewire::redirect() mengirim
                    // string ini apa adanya ke browser (tidak lewat UrlGenerator),
                    // jadi di deployment subpath (mis. /demo-silogy) hasilnya
                    // menuju ke akar domain, bukan /demo-silogy/dashboard — sudah
                    // ditangani lewat route() di dalam helper tersebut.
                    ->redirectTo(fn (): string => PeranUnitFormFields::redirectUrlAfterImpersonateStart())
                    // Eksplisit (bukan andalkan fallback referer bawaan package)
                    // supaya "tinggalkan impersonate" (PilihPeranUnit/KeluarAction,
                    // baca session 'impersonate.back_to') pasti kembali ke baris
                    // tabel + filter ini, bukan bergantung header Referer browser.
                    ->backTo(fn (): string => url()->full()),
                static::makeResetPasswordAction()
                    ->iconButton()
                    ->tooltip('Reset password'),
                Action::make('toggleStatus')
                    ->label(fn (User $record): string => $record->email_verified_at ? 'Nonaktifkan' : 'Aktifkan')
                    ->icon(fn (User $record) => $record->email_verified_at
                        ? Heroicon::OutlinedNoSymbol
                        : Heroicon::OutlinedCheckCircle)
                    ->color(fn (User $record): string => $record->email_verified_at ? 'danger' : 'success')
                    ->iconButton()
                    ->tooltip(fn (User $record): string => $record->email_verified_at ? 'Nonaktifkan' : 'Aktifkan')
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        $aktifkan = $record->email_verified_at === null;

                        $record->forceFill([
                            'email_verified_at' => $aktifkan ? now() : null,
                        ])->save();

                        Notification::make()
                            ->title($aktifkan ? 'Pengguna diaktifkan' : 'Pengguna dinonaktifkan')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('ubahStatus')
                        ->label('Ubah status')
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->requiresConfirmation()
                        ->modalHeading('Ubah status pengguna terpilih?')
                        ->schema([
                            Select::make('status')
                                ->label('Status')
                                ->options([
                                    'aktif' => 'Aktif',
                                    'nonaktif' => 'Nonaktif',
                                ])
                                ->default('aktif')
                                ->required(),
                        ])
                        ->authorizeIndividualRecords('update')
                        ->action(function (Collection $records, array $data): void {
                            $aktifkan = $data['status'] === 'aktif';

                            $records->each(fn (User $record) => $record->forceFill([
                                'email_verified_at' => $aktifkan
                                    ? ($record->email_verified_at ?? now())
                                    : null,
                            ])->save());

                            Notification::make()
                                ->title($records->count().' pengguna '.($aktifkan ? 'diaktifkan' : 'dinonaktifkan'))
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords('delete'),
                ]),
            ]);
    }

    public static function makeResetPasswordAction(): Action
    {
        return Action::make('resetPassword')
            ->label('Reset password')
            ->icon(Heroicon::OutlinedEnvelope)
            ->requiresConfirmation()
            ->modalHeading('Kirim email reset password?')
            ->modalDescription('Pengguna akan menerima tautan atur ulang kata sandi di email terdaftar.')
            ->action(fn (User $record) => static::sendResetPasswordLink($record));
    }

    public static function sendResetPasswordLink(User $record): void
    {
        $status = Password::broker(Filament::getAuthPasswordBroker())->sendResetLink(
            ['email' => $record->email],
            function (User $user, string $token): void {
                $notification = app(ResetPasswordNotification::class, ['token' => $token]);
                $notification->url = Filament::getResetPasswordUrl($token, $user);
                $user->notify($notification);
            },
        );

        if ($status !== Password::RESET_LINK_SENT) {
            Notification::make()
                ->title('Gagal mengirim email reset password')
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Email reset password berhasil dikirim')
            ->success()
            ->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
