<?php

namespace App\Modules\Kurikulum\Filament\Resources;

use App\Models\User;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\Institusi\Filament\Resources\AcademicUnitResource;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kurikulum\Filament\Resources\KurikulumResource\Pages\CreateKurikulum;
use App\Modules\Kurikulum\Filament\Resources\KurikulumResource\Pages\EditKurikulum;
use App\Modules\Kurikulum\Filament\Resources\KurikulumResource\Pages\ListKurikulums;
use App\Modules\Kurikulum\Filament\Resources\KurikulumResource\RelationManagers\BokRelationManager;
use App\Modules\Kurikulum\Filament\Resources\KurikulumResource\RelationManagers\CplMkRelationManager;
use App\Modules\Kurikulum\Filament\Resources\KurikulumResource\RelationManagers\CplRelationManager;
use App\Modules\Kurikulum\Filament\Resources\KurikulumResource\RelationManagers\ProfilLulusanRelationManager;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Models\ProfilLulusan;
use App\Modules\Kurikulum\States\AktifState;
use App\Modules\Kurikulum\States\BokState;
use App\Modules\Kurikulum\States\CplState;
use App\Modules\Kurikulum\States\DraftState;
use App\Modules\Kurikulum\States\KurikulumState;
use App\Modules\Kurikulum\States\MkState;
use App\Modules\Kurikulum\States\ProfilLulusanState;
use App\Modules\Kurikulum\States\SetdosenmkState;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Slider;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Spatie\ModelStates\State;

class KurikulumResource extends Resource
{
    protected static ?string $model = Kurikulum::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|\UnitEnum|null $navigationGroup = 'Kurikulum';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'kurikulum';

    protected static ?string $pluralModelLabel = 'kurikulum';

    protected static ?string $recordTitleAttribute = 'nama';

    protected static ?string $slug = 'kurikulums';

    /**
     * @var array<string, class-string<KurikulumState>>
     */
    public static array $stateClassMap = [
        'draft' => DraftState::class,
        'profil_lulusan' => ProfilLulusanState::class,
        'cpl' => CplState::class,
        'bok' => BokState::class,
        'mk' => MkState::class,
        'setdosenmk' => SetdosenmkState::class,
        'aktif' => AktifState::class,
    ];

    /**
     * @return array<string, string>
     */
    public static function stateOptions(): array
    {
        return [
            'draft' => 'Draft',
            'profil_lulusan' => 'Profil Lulusan',
            'cpl' => 'CPL',
            'bok' => 'BoK',
            'mk' => 'Mata Kuliah',
            'setdosenmk' => 'Set Dosen MK',
            'aktif' => 'Aktif',
        ];
    }

    public static function stateColor(string $state): string
    {
        return match ($state) {
            'draft' => 'gray',
            'profil_lulusan' => 'info',
            'cpl' => 'warning',
            'bok' => 'primary',
            'mk' => 'info',
            'setdosenmk' => 'warning',
            'aktif' => 'success',
            default => 'gray',
        };
    }

    /**
     * @return class-string<KurikulumState>
     */
    public static function stateClassFor(string $stateName): string
    {
        return static::$stateClassMap[$stateName] ?? DraftState::class;
    }

    /**
     * @return list<string>
     */
    public static function workflowStepsFor(Kurikulum $kurikulum): array
    {
        $kurikulum->loadMissing('academicUnit');

        if ($kurikulum->academicUnit->isProdi()) {
            return ['draft', 'profil_lulusan', 'cpl', 'bok', 'mk', 'setdosenmk', 'aktif'];
        }

        return ['draft', 'cpl', 'bok', 'mk', 'setdosenmk', 'aktif'];
    }

    /**
     * @return Collection<int, string>
     */
    public static function scopedKurikulumUnitIds(): Collection
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return collect();
        }

        return AcademicUnitScope::scopedTimKurikulumUnitIdsFor($user);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('academicUnit');

        $unitIds = static::scopedKurikulumUnitIds();

        if ($unitIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        if (Auth::user()?->hasRole('Super Admin')) {
            return $query;
        }

        return $query->whereIn('academic_unit_id', $unitIds);
    }

    public static function form(Schema $schema): Schema
    {
        $scopedUnitIds = static::scopedKurikulumUnitIds();

        return $schema
            ->components([
                Section::make('Data Kurikulum')
                    ->schema([
                        Select::make('academic_unit_id')
                            ->label('Unit akademik')
                            ->options(function () use ($scopedUnitIds): array {
                                return AcademicUnit::query()
                                    ->whereIn('id', $scopedUnitIds)
                                    ->orderBy('nama')
                                    ->get()
                                    ->mapWithKeys(fn (AcademicUnit $unit): array => [
                                        $unit->id => AcademicUnitResource::formatUnitOptionLabel($unit),
                                    ])
                                    ->all();
                            })
                            ->searchable()
                            ->required()
                            ->default($scopedUnitIds->count() === 1 ? $scopedUnitIds->first() : null)
                            ->disabled($scopedUnitIds->count() === 1)
                            ->dehydrated(),

                        TextInput::make('nama')
                            ->label('Nama kurikulum')
                            ->required()
                            ->maxLength(150),

                        TextInput::make('kode')
                            ->label('Kode')
                            ->maxLength(30)
                            ->placeholder('KUR-2025-IF'),

                        Select::make('tahun')
                            ->label('Tahun')
                            ->options(fn (): array => collect(range((int) date('Y') + 1, 2015))
                                ->mapWithKeys(fn (int $year): array => [$year => (string) $year])
                                ->all())
                            ->default((int) date('Y'))
                            ->required(),

                        Slider::make('target_capaian_lulusan')
                            ->label('Target capaian lulusan (%)')
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(1)
                            ->default(75)
                            ->required(),

                        RichEditor::make('deskripsi')
                            ->label('Deskripsi')
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label('Kurikulum aktif')
                            ->inline(false)
                            ->default(false),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('academicUnit.nama')
                    ->label('Unit akademik')
                    ->sortable(),

                TextColumn::make('tahun')
                    ->label('Tahun')
                    ->sortable(),

                TextColumn::make('state')
                    ->label('State')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => static::stateOptions()[(string) $state] ?? (string) $state)
                    ->color(fn ($state): string => static::stateColor((string) $state)),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),

                TextColumn::make('progres')
                    ->label('Progres pengisian')
                    ->state(fn (Kurikulum $record): string => static::progresPengisian($record)->toHtml())
                    ->html(),
            ])
            ->filters([
                SelectFilter::make('academic_unit_id')
                    ->label('Unit akademik')
                    ->relationship(
                        'academicUnit',
                        'nama',
                        fn (Builder $query): Builder => $query->whereIn('id', static::scopedKurikulumUnitIds()),
                    ),

                SelectFilter::make('academic_unit_type')
                    ->label('Jenis unit')
                    ->options(AcademicUnitResource::typeOptions())
                    ->query(function (Builder $query, array $data): Builder {
                        $type = $data['value'] ?? null;

                        if (blank($type)) {
                            return $query;
                        }

                        return $query->whereHas(
                            'academicUnit',
                            fn (Builder $unitQuery): Builder => $unitQuery->where('type', $type),
                        );
                    }),

                SelectFilter::make('state')
                    ->label('State workflow')
                    ->options(static::stateOptions()),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('lanjutkanState')
                    ->label('Lanjutkan State')
                    ->icon(Heroicon::OutlinedArrowRightCircle)
                    ->visible(fn (Kurikulum $record): bool => $record->state->hasTransitionableStates())
                    ->form(function (Kurikulum $record): array {
                        $options = collect($record->state->transitionableStateInstances())
                            ->mapWithKeys(function (State $state): array {
                                $name = $state::getMorphClass();

                                return [$state::class => static::stateOptions()[$name] ?? $name];
                            })
                            ->all();

                        return [
                            Select::make('target_state')
                                ->label('State berikutnya')
                                ->options($options)
                                ->required(),
                        ];
                    })
                    ->action(function (Kurikulum $record, array $data): void {
                        $targetClass = $data['target_state'];

                        if (! is_subclass_of($targetClass, KurikulumState::class)) {
                            Notification::make()
                                ->title('State tidak valid')
                                ->danger()
                                ->send();

                            return;
                        }

                        if (! $record->state->canTransitionTo($targetClass)) {
                            Notification::make()
                                ->title('Transisi tidak diizinkan')
                                ->body('Pastikan prasyarat state workflow sudah terpenuhi.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->state->transitionTo($targetClass);

                        Notification::make()
                            ->title('State diperbarui')
                            ->body('Kurikulum berpindah ke: '.(static::stateOptions()[$record->fresh()->state->getValue()] ?? ''))
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Ringkasan keberadaan isian per tahap OBE untuk kurikulum ini:
     * Profil (khusus prodi), CPL, BoK, MK, Penawaran MK, dan Kelas MK.
     */
    public static function progresPengisian(Kurikulum $kurikulum): HtmlString
    {
        $unitId = $kurikulum->academic_unit_id;
        $kurikulum->loadMissing('academicUnit');

        $tahap = [];

        if ($kurikulum->academicUnit?->isProdi()) {
            $tahap['Profil'] = ProfilLulusan::query()
                ->where('kurikulum_id', $kurikulum->id)->count();
        }

        $tahap['CPL'] = Cpl::query()->where('academic_unit_id', $unitId)->count();
        $tahap['BoK'] = Bok::query()->where('academic_unit_id', $unitId)->count();
        $tahap['MK'] = Mk::query()->where('academic_unit_id', $unitId)->count();
        $tahap['Penawaran'] = MkUnit::query()->where('academic_unit_id', $unitId)->count();
        $tahap['Kelas'] = KelasMk::query()
            ->whereHas('mkUnit', fn (Builder $query): Builder => $query->where('academic_unit_id', $unitId))
            ->count();

        $badges = collect($tahap)
            ->map(function (int $jumlah, string $label): string {
                $warna = $jumlah > 0 ? '#16a34a' : '#9ca3af';

                return '<span style="display:inline-block;margin:1px 2px;padding:1px 7px;border-radius:9999px;'
                    .'font-size:11px;font-weight:600;color:#fff;background:'.$warna.';white-space:nowrap;">'
                    .e($label).' '.$jumlah.'</span>';
            })
            ->implode('');

        return new HtmlString($badges);
    }

    public static function getRelations(): array
    {
        return [
            ProfilLulusanRelationManager::class,
            CplRelationManager::class,
            BokRelationManager::class,
            CplMkRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKurikulums::route('/'),
            'create' => CreateKurikulum::route('/create'),
            'edit' => EditKurikulum::route('/{record}/edit'),
        ];
    }
}
