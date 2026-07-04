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
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
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
use Illuminate\Support\Facades\Auth;

class KelasMkResource extends Resource
{
    use HasKelasMkUnitScope;
    use HasKurikulumTerpilihFilter;

    protected static ?string $model = KelasMk::class;

    protected static ?string $policy = KelasMkPolicy::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Kelas';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'kelas MK';

    protected static ?string $pluralModelLabel = 'kelas MK';

    protected static ?string $slug = 'kelas-mks';

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

        if ($user->hasRole('Dosen Pengampu') && $user->can('kelola_kelas') && ! $user->hasAnyRole([
            'Admin Program Studi',
            'Admin Jurusan',
            'Admin Fakultas',
            'Admin Universitas',
        ])) {
            return $query->where('dosen_pengampu_id', Auth::id());
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
                            ->relationship(
                                'semester',
                                'nama',
                                fn (Builder $query): Builder => $query->orderBy('kode'),
                            )
                            ->default($activeSemesterId)
                            ->required()
                            ->searchable()
                            ->preload(),

                        TextInput::make('kode_kelas')
                            ->label('Kode kelas')
                            ->placeholder('A')
                            ->required()
                            ->maxLength(10),

                        Select::make('dosen_pengampu_id')
                            ->label('Dosen pengampu')
                            ->options(fn (Get $get): array => static::usersByRoleOptions(
                                'Dosen Pengampu',
                                static::academicUnitIdForMkUnit($get('mk_unit_id')),
                            ))
                            ->searchable()
                            ->nullable()
                            ->disabled(fn (): bool => ! static::canAssignDosenPengampu())
                            ->dehydrated(fn (): bool => static::canAssignDosenPengampu()),

                        Select::make('koordinator_mk_id')
                            ->label('Koordinator MK')
                            ->options(fn (Get $get): array => static::usersByRoleOptions(
                                'Koordinator Mata Kuliah',
                                static::academicUnitIdForMkUnit($get('mk_unit_id')),
                            ))
                            ->searchable()
                            ->nullable(),

                        TextInput::make('kapasitas')
                            ->label('Kapasitas')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(500)
                            ->nullable(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        $accessibleUnitIds = static::scopedAccessibleUnitIds();
        $activeSemesterId = Semester::query()
            ->where('status_aktif', true)
            ->value('id');

        return $table
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

                TextColumn::make('semester.kode')
                    ->label('Semester')
                    ->sortable(),

                TextColumn::make('dosenPengampu.full_name')
                    ->label('Dosen pengampu')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('koordinatorMk.full_name')
                    ->label('Koordinator MK')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('kapasitas')
                    ->label('Kapasitas')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                static::kurikulumTerpilihFilter(
                    fn (Builder $query, Kurikulum $kurikulum): Builder => $query->whereHas(
                        'mkUnit',
                        fn (Builder $mkUnitQuery): Builder => $mkUnitQuery
                            ->where('academic_unit_id', $kurikulum->academic_unit_id),
                    ),
                ),
                SelectFilter::make('semester_id')
                    ->label('Semester')
                    ->relationship('semester', 'nama')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('academic_unit_id')
                    ->label('Program studi')
                    ->options(fn (): array => AcademicUnit::query()
                        ->whereIn('id', $accessibleUnitIds)
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
                    ->visible($accessibleUnitIds->count() > 1),

                SelectFilter::make('mk_id')
                    ->label('Mata kuliah')
                    ->options(fn (): array => Mk::query()
                        ->whereHas(
                            'mkUnits',
                            fn (Builder $mkUnitQuery): Builder => $mkUnitQuery->whereIn('academic_unit_id', $accessibleUnitIds),
                        )
                        ->orderBy('nama')
                        ->pluck('nama', 'id')
                        ->all())
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
            ])
            ->headerActions([
                Action::make('setDosenMassal')
                    ->label('Set Dosen Massal')
                    ->icon(Heroicon::OutlinedUserGroup)
                    ->visible(fn (): bool => static::canAssignDosenPengampu())
                    ->form([
                        Select::make('semester_id')
                            ->label('Semester')
                            ->options(fn (): array => Semester::query()
                                ->orderBy('kode')
                                ->pluck('nama', 'id')
                                ->all())
                            ->default($activeSemesterId)
                            ->required()
                            ->searchable(),

                        Select::make('default_dosen_id')
                            ->label('Dosen pengampu default')
                            ->options(fn (): array => static::usersByRoleOptions('Dosen Pengampu'))
                            ->searchable()
                            ->required(),

                        Toggle::make('gunakan_koordinator')
                            ->label('Utamakan koordinator MK sebagai dosen pengampu bila sudah ditetapkan')
                            ->default(true),
                    ])
                    ->action(function (array $data): void {
                        $unitIds = static::scopedAccessibleUnitIds();
                        $updated = 0;

                        $kelasQuery = KelasMk::query()
                            ->where('semester_id', $data['semester_id'])
                            ->whereNull('dosen_pengampu_id')
                            ->whereHas(
                                'mkUnit',
                                fn (Builder $mkUnitQuery): Builder => $mkUnitQuery->whereIn('academic_unit_id', $unitIds),
                            );

                        foreach ($kelasQuery->cursor() as $kelas) {
                            $dosenId = ($data['gunakan_koordinator'] ?? false) && $kelas->koordinator_mk_id
                                ? $kelas->koordinator_mk_id
                                : $data['default_dosen_id'];

                            $kelas->update(['dosen_pengampu_id' => $dosenId]);
                            $updated++;
                        }

                        Notification::make()
                            ->title('Penetapan dosen massal selesai')
                            ->body("{$updated} kelas MK diperbarui.")
                            ->success()
                            ->send();
                    }),
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
}
