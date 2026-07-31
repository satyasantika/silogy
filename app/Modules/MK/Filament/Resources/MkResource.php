<?php

namespace App\Modules\MK\Filament\Resources;

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\Kurikulum\Filament\Support\Concerns\HasKurikulumTerpilihFilter;
use App\Modules\Kurikulum\Filament\Support\Concerns\HasTimKurikulumUnitScope;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\MK\Filament\Resources\MkResource\Pages\CreateMk;
use App\Modules\MK\Filament\Resources\MkResource\Pages\EditMk;
use App\Modules\MK\Filament\Resources\MkResource\Pages\ListMks;
use App\Modules\MK\Models\Mk;
use App\Support\Filament\DelegasiMenu;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class MkResource extends Resource
{
    use HasKurikulumTerpilihFilter;
    use HasTimKurikulumUnitScope;

    protected static ?string $model = Mk::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|\UnitEnum|null $navigationGroup = 'Kurikulum';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'mata kuliah';

    protected static ?string $pluralModelLabel = 'mata kuliah';

    protected static ?string $recordTitleAttribute = 'nama';

    protected static ?string $slug = 'mks';

    protected static function kurikulumBannerCatatan(): ?string
    {
        return 'Mata kuliah yang tampil, ditambahkan, maupun diimpor mengikuti kurikulum ini.';
    }

    public static function shouldRegisterNavigation(): bool
    {
        if (DelegasiMenu::sembunyikanDariSuperAdmin()) {
            return false;
        }

        return static::canAccess();
    }

    /**
     * @return array<string, string>
     */
    public static function jenisOptions(): array
    {
        return [
            'wajib' => 'Wajib',
            'pilihan' => 'Pilihan',
            'praktikum' => 'Praktikum',
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return static::scopeEloquentByTimKurikulumUnits(
            parent::getEloquentQuery()->with('academicUnit'),
        );
    }

    public static function form(Schema $schema): Schema
    {
        $unitIds = static::scopedTimKurikulumUnitIds();

        return $schema
            ->components([
                Section::make('Data Mata Kuliah')
                    ->schema([
                        Select::make('academic_unit_id')
                            ->label('Unit pemilik MK')
                            ->options(static::timKurikulumUnitOptions())
                            ->searchable()
                            ->required()
                            ->default($unitIds->count() === 1 ? $unitIds->first() : null)
                            ->disabled($unitIds->count() === 1)
                            ->dehydrated(),

                        Placeholder::make('state_display')
                            ->label('State workflow MK')
                            ->content(fn (?Mk $record): string => strtoupper($record?->state ?? 'draft'))
                            ->visible(fn (?Mk $record): bool => $record !== null),

                        TextInput::make('nama')
                            ->label('Nama mata kuliah')
                            ->required()
                            ->maxLength(150)
                            ->columnSpanFull(),

                        TextInput::make('sks_teori')
                            ->label('SKS teori')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(24)
                            ->default(0)
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => static::syncSks($get, $set)),

                        TextInput::make('sks_praktik')
                            ->label('SKS praktik')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(24)
                            ->default(0)
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => static::syncSks($get, $set)),

                        TextInput::make('sks_lapangan')
                            ->label('SKS lapangan')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(24)
                            ->default(0)
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => static::syncSks($get, $set)),

                        TextInput::make('sks')
                            ->label('Total SKS')
                            ->numeric()
                            ->disabled()
                            ->dehydrated()
                            ->default(0),

                        Select::make('jenis')
                            ->label('Jenis')
                            ->options(static::jenisOptions())
                            ->required()
                            ->default('wajib'),

                        Select::make('koordinator_mk_id')
                            ->label('Koordinator MK')
                            ->helperText('Pilih dari dosen pengampu. Menetapkan koordinator otomatis memberi role Koordinator Mata Kuliah.')
                            ->options(fn (): array => static::koordinatorMkOptions())
                            ->searchable()
                            ->nullable(),

                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true)
                            ->inline(false),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function syncSks(Get $get, Set $set): void
    {
        $total = (int) $get('sks_teori') + (int) $get('sks_praktik') + (int) $get('sks_lapangan');
        $set('sks', $total);
    }

    /**
     * Opsi koordinator: seluruh user ber-role Dosen Pengampu, label
     * "Nama (kode_prodi)". Kode dari unit prodi penugasan; bila belum ada
     * prodi, pakai kode unit pertama.
     *
     * @return array<string, string>
     */
    public static function koordinatorMkOptions(): array
    {
        return User::query()
            ->role('Dosen Pengampu')
            ->with(['academicUnits' => fn ($query) => $query->orderBy('nama')])
            ->orderBy('full_name')
            ->get()
            ->mapWithKeys(fn (User $user): array => [
                $user->id => static::labelKoordinatorMk($user),
            ])
            ->all();
    }

    public static function labelKoordinatorMk(User $user): string
    {
        $nama = $user->full_name ?? $user->username ?? 'Tanpa nama';

        $kodeProdi = $user->academicUnits
            ->first(fn (AcademicUnit $unit): bool => $unit->isProdi())
            ?->code
            ?? $user->academicUnits->first()?->code;

        return filled($kodeProdi) ? "{$nama} ({$kodeProdi})" : $nama;
    }

    /**
     * @return array<string, string>
     */
    public static function descendantUnitOptionsForMk(Mk $mk): array
    {
        $ids = AcademicUnitScope::descendantIdsIncludingSelf($mk->academic_unit_id);

        return AcademicUnit::query()
            ->whereIn('id', $ids)
            ->orderBy('nama')
            ->get()
            ->mapWithKeys(fn (AcademicUnit $unit): array => [$unit->id => $unit->nama_lengkap])
            ->all();
    }

    public static function table(Table $table): Table
    {
        return static::applyKurikulumTerpilihTable(
            $table
                ->columns([
                    TextColumn::make('nama')->label('Nama')->searchable()->sortable(),
                    TextColumn::make('sks')->label('SKS')->sortable(),
                    TextColumn::make('jenis')
                        ->label('Jenis')
                        ->formatStateUsing(fn (string $state): string => static::jenisOptions()[$state] ?? $state),
                    TextColumn::make('state')->label('State')->badge(),
                    SelectColumn::make('koordinator_mk_id')
                        ->label('Koordinator MK')
                        ->options(fn (): array => static::koordinatorMkOptions())
                        ->searchableOptions()
                        ->placeholder('—')
                        ->selectablePlaceholder()
                        ->getOptionLabelUsing(function ($value): ?string {
                            if (blank($value)) {
                                return null;
                            }

                            $user = User::query()->with('academicUnits')->find($value);

                            return $user !== null ? static::labelKoordinatorMk($user) : null;
                        })
                        ->disabled(fn (Mk $record): bool => Gate::denies('update', $record))
                        ->rules(['nullable', 'uuid']),
                    IconColumn::make('is_active')->label('Aktif')->boolean(),
                ])
                ->recordActions([
                    EditAction::make(),
                ])
                ->toolbarActions([
                    BulkActionGroup::make([
                        DeleteBulkAction::make(),
                    ]),
                ]),
            fn (Builder $query, Kurikulum $kurikulum): Builder => $query
                ->where('kurikulum_id', $kurikulum->id),
        );
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMks::route('/'),
            'create' => CreateMk::route('/create'),
            'edit' => EditMk::route('/{record}/edit'),
        ];
    }
}
