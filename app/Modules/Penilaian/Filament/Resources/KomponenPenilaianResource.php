<?php

namespace App\Modules\Penilaian\Filament\Resources;

use App\Models\User;
use App\Modules\MK\Filament\Support\Concerns\HasKoordinatorMkScope;
use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource\Pages\CreateKomponenPenilaian;
use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource\Pages\EditKomponenPenilaian;
use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource\Pages\ListKomponenPenilaians;
use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource\RelationManagers\SubcpmkKomponenPenilaianRelationManager;
use App\Modules\Penilaian\Models\Evaluasi;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Policies\KomponenPenilaianPolicy;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class KomponenPenilaianResource extends Resource
{
    use HasKoordinatorMkScope;

    protected static ?string $model = KomponenPenilaian::class;

    protected static ?string $policy = KomponenPenilaianPolicy::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|\UnitEnum|null $navigationGroup = 'Penilaian';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'komponen penilaian';

    protected static ?string $pluralModelLabel = 'komponen penilaian';

    protected static ?string $slug = 'komponen-penilaian';

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();

        return $user instanceof User && app(KomponenPenilaianPolicy::class)->viewAny($user);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['kelasMk.mkUnit.mk', 'evaluasi']);

        $user = Auth::user();

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRole(['Super Admin', 'Auditor Mutu'])) {
            return $query;
        }

        if ($user->hasRole('Dosen Pengampu') && ! $user->hasAnyRole([
            'Admin Program Studi',
            'Admin Jurusan',
            'Admin Fakultas',
            'Admin Universitas',
        ])) {
            return $query->whereRaw('1 = 0');
        }

        $kelasIds = static::scopedKoordinatorKelasMkIds($user);

        if ($kelasIds->isEmpty() && ! $user->hasAnyRole([
            'Admin Program Studi',
            'Admin Jurusan',
            'Admin Fakultas',
            'Admin Universitas',
        ])) {
            return $query->whereRaw('1 = 0');
        }

        if ($kelasIds->isNotEmpty()) {
            return $query->whereIn('kelas_mk_id', $kelasIds);
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        $kelasOptions = static::scopedKoordinatorKelasMkOptions();

        return $schema
            ->components([
                Section::make('Komponen Penilaian')
                    ->schema([
                        Select::make('kelas_mk_id')
                            ->label('Kelas MK')
                            ->options($kelasOptions)
                            ->searchable()
                            ->required()
                            ->default(count($kelasOptions) === 1 ? array_key_first($kelasOptions) : null)
                            ->disabled(count($kelasOptions) === 1)
                            ->dehydrated(),

                        Select::make('evaluasi_id')
                            ->label('Jenis evaluasi')
                            ->options(fn (): array => Evaluasi::query()
                                ->orderBy('kode')
                                ->pluck('nama', 'id')
                                ->all())
                            ->searchable()
                            ->required()
                            ->preload(),

                        TextInput::make('kode')
                            ->label('Kode')
                            ->maxLength(30),

                        TextInput::make('nama')
                            ->label('Nama')
                            ->required()
                            ->maxLength(100),

                        TextInput::make('bobot')
                            ->label('Bobot (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(100)
                            ->required()
                            ->helperText('Total bobot semua komponen per kelas wajib 100%.'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kelasMk.mkUnit.mk.nama')
                    ->label('Mata kuliah')
                    ->sortable(),

                TextColumn::make('kelasMk.kode_kelas')
                    ->label('Kelas'),

                TextColumn::make('evaluasi.nama')
                    ->label('Evaluasi'),

                TextColumn::make('nama')
                    ->label('Nama')
                    ->searchable(),

                TextColumn::make('bobot')
                    ->label('Bobot')
                    ->suffix('%'),
            ])
            ->filters([
                SelectFilter::make('kelas_mk_id')
                    ->label('Kelas MK')
                    ->options(static::scopedKoordinatorKelasMkOptions())
                    ->searchable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            SubcpmkKomponenPenilaianRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKomponenPenilaians::route('/'),
            'create' => CreateKomponenPenilaian::route('/create'),
            'edit' => EditKomponenPenilaian::route('/{record}/edit'),
        ];
    }
}
