<?php

namespace App\Modules\MK\Filament\Resources;

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\Kurikulum\Filament\Support\Concerns\HasKurikulumTerpilihFilter;
use App\Modules\Kurikulum\Filament\Support\Concerns\HasTimKurikulumUnitScope;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Filament\Resources\MkUnitResource\Pages\CreateMkUnit;
use App\Modules\MK\Filament\Resources\MkUnitResource\Pages\EditMkUnit;
use App\Modules\MK\Filament\Resources\MkUnitResource\Pages\ListMkUnits;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use App\Support\Filament\DelegasiMenu;
use Filament\Actions\EditAction;
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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Rules\Unique;

class MkUnitResource extends Resource
{
    use HasKurikulumTerpilihFilter;
    use HasTimKurikulumUnitScope;

    protected static ?string $model = MkUnit::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static string|\UnitEnum|null $navigationGroup = 'Kurikulum';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Penawaran MK';

    protected static ?string $modelLabel = 'penawaran MK';

    protected static ?string $pluralModelLabel = 'penawaran MK';

    protected static ?string $slug = 'mk-units';

    public static function shouldRegisterNavigation(): bool
    {
        if (DelegasiMenu::sembunyikanDariSuperAdmin()) {
            return false;
        }

        $user = Auth::user();

        // Penawaran/adaptasi MK hanya relevan di level prodi.
        if ($user instanceof User && static::scopedTimKurikulumUnitIds()->isEmpty()) {
            return false;
        }

        return parent::shouldRegisterNavigation();
    }

    /**
     * @return Collection<int, string>
     */
    public static function scopedTimKurikulumUnitIds(): Collection
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return collect();
        }

        return AcademicUnitScope::scopedMkUnitProdiIdsFor($user);
    }

    public static function getEloquentQuery(): Builder
    {
        return static::scopeEloquentByTimKurikulumUnits(
            parent::getEloquentQuery()->with(['mk.academicUnit', 'academicUnit']),
        );
    }

    /**
     * Kurikulum prodi untuk konteks penawaran / update massal.
     *
     * @return array<string, string>
     */
    public static function kurikulumProdiOptions(): array
    {
        return collect(KurikulumTerpilih::options())
            ->filter(function (string $label, string $id): bool {
                return Kurikulum::query()->with('academicUnit')->find($id)?->academicUnit?->isProdi() ?? false;
            })
            ->all();
    }

    /**
     * MK yang dapat diadaptasi unit penawaran: milik unit sendiri
     * atau milik unit induknya (fakultas/universitas).
     *
     * @return array<string, string>
     */
    public static function adaptableMkOptions(?string $unitId): array
    {
        if (blank($unitId)) {
            return [];
        }

        $unit = AcademicUnit::query()->find($unitId);

        if (! $unit) {
            return [];
        }

        $ids = AcademicUnitScope::ancestorIdsIncludingSelf($unit);

        return Mk::query()
            ->whereIn('academic_unit_id', $ids)
            ->where('is_active', true)
            ->with('academicUnit')
            ->orderBy('nama')
            ->get()
            ->mapWithKeys(fn (Mk $mk): array => [
                $mk->id => "{$mk->nama} — {$mk->academicUnit?->nama}",
            ])
            ->all();
    }

    public static function form(Schema $schema): Schema
    {
        $unitIds = static::scopedTimKurikulumUnitIds();

        return $schema
            ->components([
                Section::make('Adaptasi / Penawaran MK')
                    ->description('Pilih MK milik unit sendiri atau unit induk (fakultas/universitas), lalu tentukan kode dan semester pelaksanaannya di unit Anda.')
                    ->schema([
                        Select::make('academic_unit_id')
                            ->label('Unit penawaran')
                            ->options(static::timKurikulumUnitOptions())
                            ->searchable()
                            ->required()
                            ->default($unitIds->count() === 1 ? $unitIds->first() : null)
                            ->disabled($unitIds->count() === 1)
                            ->dehydrated()
                            ->live()
                            ->rule(fn (): In => Rule::in(static::scopedTimKurikulumUnitIds()->all()))
                            ->afterStateUpdated(fn (Set $set) => $set('mk_id', null)),

                        Select::make('mk_id')
                            ->label('Mata kuliah')
                            ->helperText('Termasuk MK penciri universitas/fakultas dari unit induk.')
                            ->options(fn (Get $get): array => static::adaptableMkOptions($get('academic_unit_id')))
                            ->searchable()
                            ->required()
                            ->rule(fn (Get $get): In => Rule::in(
                                array_keys(static::adaptableMkOptions($get('academic_unit_id'))),
                            ))
                            ->rule(fn (Get $get, ?MkUnit $record): Unique => (new Unique('mk_units', 'mk_id'))
                                ->where('academic_unit_id', $get('academic_unit_id'))
                                ->ignore($record?->id))
                            ->validationMessages([
                                'unique' => 'MK ini sudah ditawarkan pada unit tersebut.',
                            ]),

                        TextInput::make('kode')
                            ->label('Kode MK di unit')
                            ->required()
                            ->maxLength(20)
                            ->rule(fn (Get $get, ?MkUnit $record): Unique => (new Unique('mk_units', 'kode'))
                                ->where('academic_unit_id', $get('academic_unit_id'))
                                ->ignore($record?->id)),

                        TextInput::make('semester_ke')
                            ->label('Semester ke-')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(14),

                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true)
                            ->inline(false),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::applyKurikulumTerpilihTable(
            $table
                ->columns([
                    TextColumn::make('mk.nama')->label('Mata kuliah')->searchable(),
                    TextColumn::make('kode')->label('Kode')->sortable(),
                    TextColumn::make('semester_ke')->label('Semester ke-')->sortable(),
                    IconColumn::make('is_active')->label('Aktif')->boolean(),
                ])
                ->recordActions([
                    EditAction::make(),
                ]),
            fn (Builder $query, Kurikulum $kurikulum): Builder => $query
                ->where('academic_unit_id', $kurikulum->academic_unit_id),
        );
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMkUnits::route('/'),
            'create' => CreateMkUnit::route('/create'),
            'edit' => EditMkUnit::route('/{record}/edit'),
        ];
    }
}
