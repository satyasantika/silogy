<?php

namespace App\Modules\Auth\Filament\Resources;

use App\Models\Permission;
use App\Models\User;
use App\Modules\Auth\Filament\Resources\UserResource\Pages\CreateUser;
use App\Modules\Auth\Filament\Resources\UserResource\Pages\EditUser;
use App\Modules\Auth\Filament\Resources\UserResource\Pages\ListUsers;
use App\Modules\Auth\Support\DomainPermissionLabels;
use App\Modules\Institusi\Filament\Resources\AcademicUnitResource;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Notifications\ResetPassword as ResetPasswordNotification;
use Filament\Actions\Action;
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
use Illuminate\Support\Facades\Password;
use STS\FilamentImpersonate\Actions\Impersonate;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = 'Autentikasi';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'pengguna';

    protected static ?string $pluralModelLabel = 'pengguna';

    protected static ?string $recordTitleAttribute = 'full_name';

    protected static ?string $slug = 'users';

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
                        TextInput::make('nomor_wa')
                            ->label('Nomor WhatsApp')
                            ->tel()
                            ->maxLength(20)
                            ->placeholder('628xxxxxxxxxx'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Akun')
                    ->schema([
                        TextInput::make('username')
                            ->label('Username')
                            ->required()
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
                    ->columnSpanFull(),

                Section::make('Role')
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
                    ])
                    ->columnSpanFull(),

                Section::make('Permission Langsung')
                    ->description('Permission tambahan di luar role. Hanya Super Admin yang dapat mengatur.')
                    ->schema([
                        Select::make('permissions')
                            ->label('Permission langsung')
                            ->relationship('permissions', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->getOptionLabelFromRecordUsing(
                                fn (Permission $record): string => DomainPermissionLabels::label($record->name)
                            )
                            ->dehydrated(fn (): bool => auth()->user()?->can('kelola_permission') ?? false),
                    ])
                    ->visible(fn (): bool => auth()->user()?->can('kelola_permission') ?? false)
                    ->columnSpanFull(),

                Section::make('Penugasan Unit')
                    ->schema([
                        Repeater::make('academicUnitUsers')
                            ->label('Penugasan unit akademik')
                            ->relationship()
                            ->schema([
                                Select::make('academic_unit_id')
                                    ->label('Unit akademik')
                                    ->options(fn (): array => AcademicUnit::query()
                                        ->orderBy('nama')
                                        ->get()
                                        ->mapWithKeys(fn (AcademicUnit $unit): array => [
                                            $unit->id => AcademicUnitResource::formatUnitOptionLabel($unit),
                                        ])
                                        ->all())
                                    ->searchable()
                                    ->required()
                                    ->distinct(),
                                Toggle::make('status_pimpinan')
                                    ->label('Pimpinan unit')
                                    ->inline(false),
                                Toggle::make('status_tim_kurikulum')
                                    ->label('Tim kurikulum')
                                    ->inline(false),
                                TextInput::make('jabatan')
                                    ->label('Jabatan')
                                    ->maxLength(100),
                            ])
                            ->columns(2)
                            ->collapsible()
                            ->itemLabel(
                                fn (array $state): ?string => isset($state['academic_unit_id'])
                                    ? AcademicUnit::find($state['academic_unit_id'])?->nama
                                    : null
                            )
                            ->addActionLabel('Tambah penugasan unit')
                            ->defaultItems(0),
                    ])
                    ->columnSpanFull(),
            ]);
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
                    ->description(fn (User $record): string => $record->username),

                TextColumn::make('username')
                    ->label('Username')
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
                    ->redirectTo('/dashboard'),
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

                        $record->update([
                            'email_verified_at' => $aktifkan ? now() : null,
                        ]);

                        Notification::make()
                            ->title($aktifkan ? 'Pengguna diaktifkan' : 'Pengguna dinonaktifkan')
                            ->success()
                            ->send();
                    }),
                EditAction::make()
                    ->iconButton()
                    ->tooltip('Ubah'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
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
