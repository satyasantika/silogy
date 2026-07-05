<?php

namespace App\Modules\Kurikulum\Filament\Resources;

use App\Models\User;
use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\Kurikulum\Filament\Resources\ProfilLulusanResource\Pages\CreateProfilLulusan;
use App\Modules\Kurikulum\Filament\Resources\ProfilLulusanResource\Pages\EditProfilLulusan;
use App\Modules\Kurikulum\Filament\Resources\ProfilLulusanResource\Pages\ListProfilLulusans;
use App\Modules\Kurikulum\Filament\Support\Concerns\HasKurikulumTerpilihFilter;
use App\Modules\Kurikulum\Filament\Support\ProfilIndikatorForm;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Models\ProfilIndikator;
use App\Modules\Kurikulum\Models\ProfilLulusan;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Support\Filament\DelegasiMenu;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

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
        if (DelegasiMenu::sembunyikanDariSuperAdmin()) {
            return false;
        }

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
        $query = parent::getEloquentQuery()
            ->with(['kurikulum.academicUnit', 'indikators']);

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

                        RichEditor::make('deskripsi')
                            ->label('Deskripsi')
                            ->required()
                            ->columnSpanFull(),

                        ProfilIndikatorForm::repeater(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::applyKurikulumTerpilihCardTable(
            $table
                ->selectable(false)
                ->recordActions([
                    EditAction::make(),
                ]),
            [
                Split::make([
                    TextColumn::make('kode')
                        ->label('Kode')
                        ->sortable()
                        ->weight(FontWeight::Bold),

                    TextColumn::make('urutan')
                        ->label('Urutan')
                        ->sortable()
                        ->size('sm'),
                ]),

                TextColumn::make('nama')
                    ->label('Nama')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('deskripsi')
                    ->label('Deskripsi')
                    ->formatStateUsing(fn (?string $state): string => filled($state)
                        ? Str::limit(trim(strip_tags($state)), 80)
                        : '—')
                    ->size('sm')
                    ->color('gray'),

                TextColumn::make('indikators_preview')
                    ->label('Indikator')
                    ->getStateUsing(function (ProfilLulusan $record): array {
                        return $record->indikators
                            ->values()
                            ->map(function (ProfilIndikator $indikator, int $index): ?string {
                                $nama = trim(strip_tags((string) $indikator->nama));

                                if ($nama === '') {
                                    return null;
                                }

                                return ($index + 1).'. '.$nama;
                            })
                            ->filter()
                            ->values()
                            ->all();
                    })
                    ->listWithLineBreaks()
                    ->placeholder('Belum ada indikator')
                    ->size('sm')
                    ->color('gray'),
            ],
            fn (Builder $query, Kurikulum $kurikulum): Builder => $query
                ->where('kurikulum_id', $kurikulum->id),
            contentGrid: ['default' => 1],
        );
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
