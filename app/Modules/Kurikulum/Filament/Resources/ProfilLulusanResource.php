<?php

namespace App\Modules\Kurikulum\Filament\Resources;

use App\Models\User;
use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\Kurikulum\Filament\Resources\ProfilLulusanResource\Pages\CreateProfilLulusan;
use App\Modules\Kurikulum\Filament\Resources\ProfilLulusanResource\Pages\EditProfilLulusan;
use App\Modules\Kurikulum\Filament\Resources\ProfilLulusanResource\Pages\ListProfilLulusans;
use App\Modules\Kurikulum\Filament\Support\Concerns\HasKurikulumTerpilihFilter;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Models\ProfilLulusan;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Menu Profil Lulusan — tampil sebelum CPL bila kurikulum terpilih
 * milik unit prodi (profil hanya relevan pada level prodi).
 */
class ProfilLulusanResource extends Resource
{
    use HasKurikulumTerpilihFilter;

    protected static ?string $model = ProfilLulusan::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|\UnitEnum|null $navigationGroup = 'Kurikulum';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Profil Lulusan';

    protected static ?string $modelLabel = 'profil lulusan';

    protected static ?string $pluralModelLabel = 'profil lulusan';

    protected static ?string $slug = 'profil-lulusan';

    public static function bisaKelola(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        return $user->hasRole('Super Admin')
            || AcademicUnitScope::scopedTimKurikulumUnitIdsFor($user)->isNotEmpty();
    }

    public static function canAccess(): bool
    {
        return static::bisaKelola();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::bisaKelola()
            && (KurikulumTerpilih::current()?->academicUnit?->isProdi() ?? false);
    }

    /**
     * Kurikulum prodi yang dapat dipilih sebagai induk profil.
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

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $query = parent::getEloquentQuery()->with('kurikulum.academicUnit');

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRole('Super Admin')) {
            return $query;
        }

        $unitIds = AcademicUnitScope::scopedTimKurikulumUnitIdsFor($user);

        return $query->whereHas(
            'kurikulum',
            fn (Builder $kurikulumQuery): Builder => $kurikulumQuery->whereIn('academic_unit_id', $unitIds),
        );
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Profil Lulusan')
                    ->schema([
                        Select::make('kurikulum_id')
                            ->label('Kurikulum')
                            ->options(static::kurikulumProdiOptions())
                            ->default(fn (): ?string => KurikulumTerpilih::current()?->academicUnit?->isProdi()
                                ? KurikulumTerpilih::currentId()
                                : null)
                            ->searchable()
                            ->required(),

                        TextInput::make('kode')
                            ->label('Kode')
                            ->required()
                            ->maxLength(10),

                        TextInput::make('nama')
                            ->label('Nama')
                            ->maxLength(150),

                        TextInput::make('urutan')
                            ->label('Urutan')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(255),

                        Textarea::make('deskripsi')
                            ->label('Deskripsi')
                            ->required()
                            ->rows(3)
                            ->columnSpanFull(),

                        Repeater::make('indikators')
                            ->label('Indikator')
                            ->relationship()
                            ->schema([
                                Textarea::make('nama')->label('Nama indikator')->rows(2),
                                Textarea::make('deskripsi')->label('Deskripsi')->rows(2),
                            ])
                            ->defaultItems(1)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode')->label('Kode')->sortable(),
                TextColumn::make('nama')->label('Nama')->searchable(),
                TextColumn::make('kurikulum.nama')->label('Kurikulum'),
                TextColumn::make('indikators_count')->label('Indikator')->counts('indikators'),
                TextColumn::make('urutan')->label('Urutan')->sortable(),
            ])
            ->filters([
                static::kurikulumTerpilihFilter(
                    fn (Builder $query, Kurikulum $kurikulum): Builder => $query
                        ->where('kurikulum_id', $kurikulum->id),
                ),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProfilLulusans::route('/'),
            'create' => CreateProfilLulusan::route('/create'),
            'edit' => EditProfilLulusan::route('/{record}/edit'),
        ];
    }
}
