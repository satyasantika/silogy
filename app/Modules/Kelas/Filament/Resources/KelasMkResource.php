<?php

namespace App\Modules\Kelas\Filament\Resources;

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Filament\Resources\KelasMkResource\Pages\CreateKelasMk;
use App\Modules\Kelas\Filament\Resources\KelasMkResource\Pages\EditKelasMk;
use App\Modules\Kelas\Filament\Resources\KelasMkResource\Pages\ListKelasMks;
use App\Modules\Kelas\Filament\Resources\KelasMkResource\RelationManagers\KelasMkMahasiswaRelationManager;
use App\Modules\Kelas\Filament\Support\Concerns\HasKelasMkUnitScope;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Policies\KelasMkPolicy;
use App\Modules\Kurikulum\Filament\Support\Concerns\HasKurikulumTerpilihFilter;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Support\PenawaranMkScope;
use App\Support\Filament\DelegasiMenu;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class KelasMkResource extends Resource
{
    use HasKelasMkUnitScope;
    use HasKurikulumTerpilihFilter;

    protected static ?string $model = KelasMk::class;

    protected static ?string $policy = KelasMkPolicy::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Kurikulum';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Kelas MK';

    protected static ?string $modelLabel = 'kelas MK';

    protected static ?string $pluralModelLabel = 'kelas MK';

    protected static ?string $slug = 'kelas-mks';

    /**
     * Kelas MK difilter menurut kurikulum unit yang dapat dikelola user.
     *
     * @return Collection<int, string>
     */
    protected static function kurikulumTerpilihFilterUnitIds(): ?Collection
    {
        $user = Auth::user();

        if ($user instanceof User && PenawaranMkScope::filterBerdasarkanPenawaranAkun($user)) {
            return PenawaranMkScope::unitIdsDariPenawaran($user);
        }

        return static::scopedAccessibleUnitIds();
    }

    /**
     * Admin prodi mengelola kelas sebelum kurikulum disusun — tampilkan
     * kelas MK menurut scope unit tanpa mewajibkan kurikulum terpilih.
     */
    protected static function kurikulumTerpilihWajib(): bool
    {
        return false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess() && ! DelegasiMenu::sembunyikanDariSuperAdmin();
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && app(KelasMkPolicy::class)->viewAny($user);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['mkUnit.mk', 'semester', 'dosenPengampu', 'koordinatorMk']);

        $user = Auth::user();

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRole(['Super Admin', 'Auditor Mutu'])) {
            return $query;
        }

        if ($user->hasRole('Dosen Pengampu') && $user->can('kelola_kelas') && ! $user->hasRole('Admin')) {
            return $query->where('dosen_pengampu_id', Auth::id());
        }

        if (PenawaranMkScope::isKoordinatorMkOnly($user)) {
            return $query->where('koordinator_mk_id', $user->id);
        }

        $unitIds = static::scopedAccessibleUnitIds();

        if ($unitIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas(
            'mkUnit',
            fn (Builder $mkUnitQuery): Builder => $mkUnitQuery->whereIn('academic_unit_id', $unitIds),
        );
    }

    public static function form(Schema $schema): Schema
    {
        $activeSemesterId = Semester::query()
            ->where('status_aktif', true)
            ->value('id');

        return $schema
            ->components([
                Section::make('Data Kelas MK')
                    ->schema([
                        Select::make('mk_unit_id')
                            ->label('Penawaran MK')
                            ->options(static::mkUnitOptions())
                            ->searchable()
                            ->required()
                            ->disabledOn('edit')
                            ->dehydrated()
                            ->columnSpanFull()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                                if (filled($get('koordinator_mk_id'))) {
                                    return;
                                }

                                $koordinatorDefault = MkUnit::query()
                                    ->with('mk')
                                    ->find($state)
                                    ?->mk?->koordinator_mk_id;

                                if ($koordinatorDefault !== null) {
                                    $set('koordinator_mk_id', $koordinatorDefault);
                                }
                            }),

                        Select::make('semester_id')
                            ->label('Semester')
                            ->disabled(fn (): bool => ! static::bolehKelolaStrukturKelas())
                            ->dehydrated(fn (): bool => static::bolehKelolaStrukturKelas())
                            ->relationship(
                                'semester',
                                'nama',
                                fn (Builder $query): Builder => $query->orderBy('kode'),
                            )
                            ->default($activeSemesterId)
                            ->required()
                            ->searchable()
                            ->preload(),

                        Select::make('kode_kelas')
                            ->label('Kode kelas')
                            ->disabled(fn (): bool => ! static::bolehKelolaStrukturKelas())
                            ->dehydrated(fn (): bool => static::bolehKelolaStrukturKelas())
                            ->options(static::kodeKelasOptions())
                            ->required()
                            ->searchable(),

                        Select::make('dosen_pengampu_id')
                            ->label('Dosen pengampu')
                            ->options(fn (Get $get): array => static::usersByRoleOptions(
                                'Dosen Pengampu',
                                static::academicUnitIdForMkUnit($get('mk_unit_id')),
                            ))
                            ->searchable()
                            ->nullable()
                            ->disabled(fn (?KelasMk $record): bool => ! static::bolehSetDosen($record))
                            ->dehydrated(fn (?KelasMk $record): bool => static::bolehSetDosen($record)),

                        Select::make('koordinator_mk_id')
                            ->label('Koordinator MK')
                            ->options(fn (Get $get): array => static::usersByRoleOptions(
                                'Koordinator Mata Kuliah',
                                static::academicUnitIdForMkUnit($get('mk_unit_id')),
                            ))
                            ->searchable()
                            ->nullable()
                            ->disabledOn('edit')
                            ->dehydrated(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        $user = Auth::user();
        $filterUnitIds = ($user instanceof User && PenawaranMkScope::filterBerdasarkanPenawaranAkun($user))
            ? PenawaranMkScope::unitIdsDariPenawaran($user)
            : static::scopedAccessibleUnitIds();

        return static::applyKurikulumTerpilihTable(
            $table
                ->columns([
                    TextColumn::make('mkUnit.mk.nama')
                        ->label('Mata kuliah')
                        ->searchable(query: function (Builder $query, string $search): Builder {
                            return $query->whereHas(
                                'mkUnit.mk',
                                fn (Builder $mkQuery): Builder => $mkQuery->where('nama', 'like', "%{$search}%"),
                            );
                        })
                        ->sortable(),

                    TextColumn::make('kode_kelas')
                        ->label('Kelas')
                        ->sortable(),

                    TextColumn::make('mahasiswas_count')
                        ->label('Mahasiswa')
                        ->counts('mahasiswas')
                        ->sortable(),

                    TextColumn::make('dosenPengampu.full_name')
                        ->label('Dosen pengampu')
                        ->placeholder('—')
                        ->formatStateUsing(function (KelasMk $record, ?string $state): string {
                            if (blank($state)) {
                                return '—';
                            }

                            if (filled($record->koordinator_mk_id) && $record->koordinator_mk_id === $record->dosen_pengampu_id) {
                                return e($state).' <span style="color:#166534;font-weight:600;">(koordinator)</span>';
                            }

                            return e($state);
                        })
                        ->html()
                        ->sortable(),
                ])
                ->recordUrl(fn (KelasMk $record): string => static::getUrl('edit', ['record' => $record]))
                ->recordActions([])
                ->toolbarActions([
                    BulkActionGroup::make([
                        DeleteBulkAction::make(),
                    ]),
                ])
                ->filters([
                    SelectFilter::make('semester_id')
                        ->label('Semester')
                        ->options(fn (): array => Semester::query()->orderByDesc('kode')->pluck('nama', 'id')->all())
                        ->query(function (Builder $query, array $data): Builder {
                            if (blank($data['value'] ?? null)) {
                                return $query;
                            }

                            return $query->where('semester_id', $data['value']);
                        })
                        ->searchable(),

                    SelectFilter::make('mk_id')
                        ->label('Mata kuliah')
                        ->options(fn (): array => PenawaranMkScope::mkFilterOptions())
                        ->query(function (Builder $query, array $data): Builder {
                            if (blank($data['value'] ?? null)) {
                                return $query;
                            }

                            return $query->whereHas(
                                'mkUnit',
                                fn (Builder $mkUnitQuery): Builder => $mkUnitQuery->where('mk_id', $data['value']),
                            );
                        })
                        ->searchable(),

                    SelectFilter::make('academic_unit_id')
                        ->label('Program studi')
                        ->options(fn (): array => AcademicUnit::query()
                            ->whereIn('id', $filterUnitIds)
                            ->where('type', 'study_program')
                            ->orderBy('nama')
                            ->pluck('nama', 'id')
                            ->all())
                        ->query(function (Builder $query, array $data): Builder {
                            if (blank($data['value'] ?? null)) {
                                return $query;
                            }

                            return $query->whereHas(
                                'mkUnit',
                                fn (Builder $mkUnitQuery): Builder => $mkUnitQuery->where('academic_unit_id', $data['value']),
                            );
                        })
                        ->visible($filterUnitIds->count() > 1)
                        ->columnSpanFull(),
                ])
                ->filtersLayout(FiltersLayout::AboveContent)
                ->filtersFormColumns(1)
                ->deferFilters(false),
            fn (Builder $query, Kurikulum $kurikulum): Builder => $query->whereHas(
                'mkUnit',
                fn (Builder $mkUnitQuery): Builder => $mkUnitQuery
                    ->where('academic_unit_id', $kurikulum->academic_unit_id),
            ),
        );
    }

    public static function getRelations(): array
    {
        return [
            KelasMkMahasiswaRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKelasMks::route('/'),
            'create' => CreateKelasMk::route('/create'),
            'edit' => EditKelasMk::route('/{record}/edit'),
        ];
    }

    /**
     * Koordinator MK tanpa peran Admin hanya melihat kelas yang ia koordinasikan.
     */
    public static function scopeHanyaKoordinatorPemilik(User $user): bool
    {
        return PenawaranMkScope::isKoordinatorMkOnly($user);
    }

    /** Field struktur kelas hanya diubah Admin (prodi) / Super Admin. */
    public static function bolehKelolaStrukturKelas(): bool
    {
        $user = Auth::user();

        return $user instanceof User
            && ($user->hasRole('Super Admin') || $user->hasRole('Admin'));
    }

    /** Penetapan dosen per kelas: Admin prodi atau Koordinator MK kelas tsb. */
    public static function bolehSetDosen(?KelasMk $record): bool
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        if ($record === null) {
            return static::canAssignDosenPengampu();
        }

        return app(KelasMkPolicy::class)->assignDosenPengampu($user, $record);
    }

    protected static function canAssignDosenPengampu(): bool
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->hasRole('Super Admin')) {
            return true;
        }

        return $user->can('setdosen_mk');
    }

    /**
     * Pilihan kode kelas A–Z.
     *
     * @return array<string, string>
     */
    public static function kodeKelasOptions(): array
    {
        return collect(range('A', 'Z'))
            ->mapWithKeys(fn (string $kode): array => [$kode => $kode])
            ->all();
    }
}
