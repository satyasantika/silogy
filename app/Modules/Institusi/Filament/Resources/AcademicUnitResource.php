<?php

namespace App\Modules\Institusi\Filament\Resources;

use App\Modules\Institusi\Filament\Resources\AcademicUnitResource\Pages\CreateAcademicUnit;
use App\Modules\Institusi\Filament\Resources\AcademicUnitResource\Pages\EditAcademicUnit;
use App\Modules\Institusi\Filament\Resources\AcademicUnitResource\Pages\ListAcademicUnits;
use App\Modules\Institusi\Filament\Resources\AcademicUnitResource\RelationManagers\AcademicUnitUsersRelationManager;
use App\Modules\Institusi\Models\AcademicUnit;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class AcademicUnitResource extends Resource
{
    protected static ?string $model = AcademicUnit::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    // Menu Super Admin (satu-satunya role yang bisa melihat resource ini)
    // tampil datar tanpa header grup — lihat shouldRegisterNavigation().
    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'unit akademik';

    protected static ?string $pluralModelLabel = 'unit akademik';

    protected static ?string $recordTitleAttribute = 'nama';

    protected static ?string $slug = 'academic-units';

    /**
     * Menu unit akademik hanya untuk Super Admin — role operasional
     * bekerja lewat kurikulum terpilih dan penugasan unit.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('Super Admin') ?? false;
    }

    /**
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        return [
            'university' => 'Universitas',
            'faculty' => 'Fakultas',
            'department' => 'Jurusan',
            'study_program' => 'Program Studi',
        ];
    }

    /**
     * Label jenis + nama untuk card kurikulum/MK.
     * Nama dibersihkan dari awalan jenis agar tidak redundan
     * ("Fakultas · Fakultas …", "Program Studi · Program Studi …").
     *
     * @return array{0: string, 1: string}
     */
    public static function jenisDanNamaUntukCard(?AcademicUnit $unit): array
    {
        if (! $unit instanceof AcademicUnit) {
            return ['Unit', '—'];
        }

        $typeLabel = static::typeOptions()[$unit->type] ?? $unit->type;
        $nama = trim((string) $unit->nama);

        foreach (static::typeOptions() as $label) {
            $prefix = $label.' ';

            if (str_starts_with($nama, $prefix)) {
                $nama = trim(substr($nama, strlen($prefix)));
                break;
            }
        }

        if ($unit->isProdi() && filled($unit->jenjang)) {
            $jenjang = (string) $unit->jenjang;

            if ($nama === '' || ! preg_match('/^'.preg_quote($jenjang, '/').'(\s|$)/u', $nama)) {
                $nama = trim($jenjang.' '.$nama);
            }
        }

        return [$typeLabel, $nama !== '' ? $nama : '—'];
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            'draft' => 'Draft',
            'aktif' => 'Aktif',
            'nonaktif' => 'Nonaktif',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function jenjangOptions(): array
    {
        return [
            'D3' => 'D3',
            'D4' => 'D4',
            'S1' => 'S1',
            'S2' => 'S2',
            'S3' => 'S3',
            'Profesi' => 'Profesi',
        ];
    }

    /**
     * Program studi dapat berinduk ke jurusan atau langsung ke fakultas
     * (keberadaan jurusan bersifat opsional).
     *
     * @return list<string>
     */
    public static function parentTypesFor(?string $type): array
    {
        return match ($type) {
            'faculty' => ['university'],
            'department' => ['faculty'],
            'study_program' => ['department', 'faculty'],
            default => [],
        };
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitas')
                    ->schema([
                        Select::make('type')
                            ->label('Jenis unit')
                            ->options(static::typeOptions())
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                if ($state === 'university') {
                                    $set('parent_id', null);
                                }
                            }),

                        Select::make('parent_id')
                            ->label('Unit induk')
                            ->relationship(
                                name: 'parent',
                                titleAttribute: 'nama',
                                modifyQueryUsing: function (Builder $query, Get $get): Builder {
                                    $parentTypes = static::parentTypesFor($get('type'));

                                    if ($parentTypes === []) {
                                        return $query->whereRaw('1 = 0');
                                    }

                                    return $query
                                        ->whereIn('type', $parentTypes)
                                        ->orderByRaw("CASE type WHEN 'university' THEN 1 WHEN 'faculty' THEN 2 WHEN 'department' THEN 3 ELSE 4 END")
                                        ->orderBy('nama');
                                },
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn (AcademicUnit $record): string => static::formatUnitOptionLabel($record)
                            )
                            ->searchable(['nama', 'code'])
                            ->preload()
                            ->disabled(fn (Get $get): bool => $get('type') === 'university' || blank($get('type')))
                            ->dehydrated(fn (Get $get): bool => $get('type') !== 'university')
                            ->required(fn (Get $get): bool => filled($get('type')) && $get('type') !== 'university')
                            ->helperText(fn (Get $get): ?string => $get('type') === 'study_program'
                                ? 'Pilih jurusan, atau langsung fakultas jika tidak memiliki jurusan.'
                                : null),

                        TextInput::make('code')
                            ->label('Kode')
                            ->maxLength(30),

                        TextInput::make('nama')
                            ->label('Nama')
                            ->required()
                            ->maxLength(150),

                        TextInput::make('singkatan')
                            ->label('Singkatan')
                            ->maxLength(30),

                        Select::make('status')
                            ->label('Status')
                            ->options(static::statusOptions())
                            ->default('draft')
                            ->required(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Detail Prodi')
                    ->schema([
                        TextInput::make('kode_pddikti')
                            ->label('Kode PDDIKTI')
                            ->maxLength(30)
                            ->unique(
                                table: AcademicUnit::class,
                                column: 'kode_pddikti',
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule) => $rule->whereNotNull('kode_pddikti'),
                            ),

                        Select::make('jenjang')
                            ->label('Jenjang')
                            ->options(static::jenjangOptions()),

                        TextInput::make('gelar_lulusan')
                            ->label('Gelar lulusan')
                            ->maxLength(50)
                            ->placeholder('S.Kom.'),

                        TextInput::make('mapel')
                            ->label('Mata pelajaran')
                            ->maxLength(100),
                    ])
                    ->columns(2)
                    ->visible(fn (Get $get): bool => $get('type') === 'study_program')
                    ->columnSpanFull(),

                Section::make('Profil')
                    ->schema([
                        Textarea::make('visi')
                            ->label('Visi')
                            ->rows(3)
                            ->columnSpanFull(),
                        Textarea::make('misi')
                            ->label('Misi')
                            ->rows(3)
                            ->columnSpanFull(),
                        Textarea::make('tujuan')
                            ->label('Tujuan')
                            ->rows(3)
                            ->columnSpanFull(),
                        Textarea::make('sasaran_strategis')
                            ->label('Sasaran strategis')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Akreditasi & SK')
                    ->schema([
                        TextInput::make('tahun_pendirian')
                            ->label('Tahun pendirian')
                            ->maxLength(4),
                        TextInput::make('sk_pendirian')
                            ->label('SK pendirian')
                            ->maxLength(100),
                        TextInput::make('tahun_akreditasi')
                            ->label('Tahun akreditasi')
                            ->maxLength(4),
                        TextInput::make('peringkat_akreditasi')
                            ->label('Peringkat akreditasi')
                            ->maxLength(20)
                            ->placeholder('Unggul / A / B'),
                        TextInput::make('sk_akreditasi')
                            ->label('SK akreditasi')
                            ->maxLength(100),
                        TextInput::make('tahun_internasional')
                            ->label('Tahun pengakuan internasional')
                            ->maxLength(4),
                        TextInput::make('sk_internasional')
                            ->label('SK pengakuan internasional')
                            ->maxLength(100),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Kontak')
                    ->schema([
                        Textarea::make('alamat')
                            ->label('Alamat')
                            ->rows(2)
                            ->columnSpanFull(),
                        TextInput::make('no_telepon')
                            ->label('No. telepon')
                            ->tel()
                            ->maxLength(20),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(100),
                        TextInput::make('website')
                            ->label('Website')
                            ->url()
                            ->maxLength(100),
                        FileUpload::make('logo_path')
                            ->label('Logo')
                            ->disk('public')
                            ->directory('academic-units/logos')
                            ->image()
                            ->maxSize(2048)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query
                    ->with('parent')
                    ->orderByRaw("CASE type WHEN 'university' THEN 1 WHEN 'faculty' THEN 2 WHEN 'department' THEN 3 WHEN 'study_program' THEN 4 ELSE 5 END")
                    ->orderBy('nama')
            )
            ->columns([
                TextColumn::make('type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => static::typeOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'university' => 'primary',
                        'faculty' => 'info',
                        'department' => 'warning',
                        'study_program' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('nama')
                    ->label('Nama')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function (string $state, AcademicUnit $record): string {
                        $depth = 0;
                        $parent = $record->parent;
                        while ($parent) {
                            $depth++;
                            $parent = $parent->parent;
                        }

                        return str_repeat('  ', $depth).$state;
                    }),

                TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('jenjang')
                    ->label('Jenjang')
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => static::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'aktif' => 'success',
                        'draft' => 'gray',
                        'nonaktif' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Jenis unit')
                    ->options(static::typeOptions()),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(static::statusOptions()),
            ])
            ->groups([
                Group::make('parent_id')
                    ->label('Unit induk')
                    ->getTitleFromRecordUsing(
                        fn (AcademicUnit $record): string => $record->parent?->nama ?? '— Unit akar —'
                    )
                    ->collapsible(),
            ])
            ->defaultGroup('parent_id')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords('delete'),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            AcademicUnitUsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAcademicUnits::route('/'),
            'create' => CreateAcademicUnit::route('/create'),
            'edit' => EditAcademicUnit::route('/{record}/edit'),
        ];
    }

    public static function formatUnitOptionLabel(AcademicUnit $record): string
    {
        $type = static::typeOptions()[$record->type] ?? $record->type;
        $code = $record->code ? " ({$record->code})" : '';

        return "{$record->nama_lengkap}{$code} — {$type}";
    }
}
