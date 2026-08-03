<?php

namespace App\Modules\Penilaian\Filament\Resources;

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Penilaian\Filament\Resources\PenilaianDosenResource\Pages\ListPenilaianDosens;
use App\Modules\Penilaian\Policies\PenilaianDosenPolicy;
use App\Modules\Penilaian\Services\PenilaianDosenService;
use App\Modules\Penilaian\Support\PenilaianSemesterTerpilih;
use App\Support\Filament\NavigationGroupPeran;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PenilaianDosenResource extends Resource
{
    protected static ?string $model = AcademicUnit::class;

    protected static ?string $policy = PenilaianDosenPolicy::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroupPeran::resolve('Pengampu MK');
    }

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Pengampu MK';

    protected static ?string $modelLabel = 'pengampu MK';

    protected static ?string $pluralModelLabel = 'pengampu MK';

    protected static ?string $slug = 'penilaian';

    protected static ?string $recordTitleAttribute = 'nama';

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && app(PenilaianDosenPolicy::class)->viewAny($user);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()
            ->whereHas(
                'mkUnits.kelasMks',
                fn (Builder $kelasQuery): Builder => $kelasQuery->where('dosen_pengampu_id', $user->id),
            );
    }

    public static function table(Table $table): Table
    {
        $user = Auth::user();

        return $table
            ->selectable(false)
            ->extraAttributes([
                'class' => 'silogy-mk-semester-toolbar silogy-penilaian-dosen',
            ])
            ->recordUrl(null)
            ->columns([
                Stack::make([
                    TextColumn::make('judul_unit')
                        ->label('Program studi')
                        ->state(function (AcademicUnit $record): string {
                            return PenilaianDosenService::judulKartuUnitHtml($record)->toHtml();
                        })
                        ->html()
                        ->searchable(query: function (Builder $query, string $search): Builder {
                            $term = '%'.$search.'%';

                            return $query->where(function (Builder $inner) use ($term): void {
                                $inner->where('nama', 'like', $term)
                                    ->orWhere('nama_lengkap', 'like', $term)
                                    ->orWhereHas(
                                        'mkUnits',
                                        fn (Builder $mkUnitQuery): Builder => $mkUnitQuery
                                            ->where('kode', 'like', $term)
                                            ->orWhereHas(
                                                'mk',
                                                fn (Builder $mkQuery): Builder => $mkQuery->where('nama', 'like', $term),
                                            ),
                                    );
                            });
                        }),

                    TextColumn::make('tabel_kelas')
                        ->label('')
                        ->state(function (AcademicUnit $record) use ($user): string {
                            if (! $user instanceof User) {
                                return '';
                            }

                            return PenilaianDosenService::tabelKelasUnitHtml(
                                $record,
                                $user,
                                PenilaianSemesterTerpilih::currentId(),
                            )->toHtml();
                        })
                        ->html(),
                ])->space(1),
            ])
            ->contentGrid(['default' => 1])
            ->defaultSort('nama')
            ->paginated(false)
            ->columnManager(false)
            ->modifyQueryUsing(function (Builder $query) use ($user): Builder {
                if (! $user instanceof User) {
                    return $query->whereRaw('1 = 0');
                }

                $semesterId = PenilaianSemesterTerpilih::currentId();

                if (blank($semesterId)) {
                    return $query->whereRaw('1 = 0');
                }

                return static::scopeKeSemester($query, $user, $semesterId);
            })
            ->filters([
                static::semesterFilter($user),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(1)
            ->deferFilters(false)
            ->hiddenFilterIndicators();
    }

    /**
     * @param  Builder<AcademicUnit>  $query
     * @return Builder<AcademicUnit>
     */
    protected static function scopeKeSemester(Builder $query, User $user, string $semesterId): Builder
    {
        return $query->whereHas(
            'mkUnits.kelasMks',
            fn (Builder $kelasQuery): Builder => $kelasQuery
                ->where('dosen_pengampu_id', $user->id)
                ->where('semester_id', $semesterId),
        );
    }

    protected static function semesterFilter(?User $user): SelectFilter
    {
        return SelectFilter::make('semester_terpilih')
            ->label('Semester')
            ->default(fn (): ?string => PenilaianSemesterTerpilih::currentId())
            ->selectablePlaceholder(false)
            ->columnSpanFull()
            ->schema([
                Select::make('value')
                    ->label('Semester')
                    ->hiddenLabel()
                    ->prefix('Semester')
                    ->options(fn (): array => PenilaianSemesterTerpilih::options())
                    ->default(fn (): ?string => PenilaianSemesterTerpilih::currentId())
                    ->selectablePlaceholder(false)
                    ->native(true)
                    ->searchable(false)
                    ->live()
                    ->afterStateHydrated(function (Select $component, $state): void {
                        $resolved = static::resolveSemesterFilterState($state);

                        if (blank($resolved)) {
                            return;
                        }

                        $component->state($resolved);
                        PenilaianSemesterTerpilih::set($resolved);
                    }),
            ])
            ->query(function (Builder $query, array $data) use ($user): Builder {
                if (! $user instanceof User) {
                    return $query->whereRaw('1 = 0');
                }

                $semesterId = static::resolveSemesterFilterState($data['value'] ?? null);

                if (blank($semesterId)) {
                    return $query->whereRaw('1 = 0');
                }

                PenilaianSemesterTerpilih::set($semesterId);

                return static::scopeKeSemester($query, $user, $semesterId);
            })
            ->indicateUsing(fn (): array => []);
    }

    protected static function resolveSemesterFilterState(mixed $state = null): ?string
    {
        $options = PenilaianSemesterTerpilih::options();

        if ($options === []) {
            return null;
        }

        $candidate = filled($state) ? (string) $state : PenilaianSemesterTerpilih::currentId();

        if (filled($candidate) && array_key_exists($candidate, $options)) {
            return $candidate;
        }

        return PenilaianSemesterTerpilih::defaultId();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPenilaianDosens::route('/'),
        ];
    }
}
