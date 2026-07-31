<?php

namespace App\Modules\Penilaian\Filament\Resources;

use App\Models\User;
use App\Modules\MK\Models\Mk;
use App\Modules\Penilaian\Filament\Pages\InputNilai;
use App\Modules\Penilaian\Filament\Resources\PenilaianDosenResource\Pages\ListPenilaianDosens;
use App\Modules\Penilaian\Policies\PenilaianDosenPolicy;
use App\Modules\Penilaian\Services\PenilaianDosenService;
use App\Modules\Penilaian\Support\PenilaianSemesterTerpilih;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class PenilaianDosenResource extends Resource
{
    protected static ?string $model = Mk::class;

    protected static ?string $policy = PenilaianDosenPolicy::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Penilaian';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Penilaian';

    protected static ?string $modelLabel = 'penilaian';

    protected static ?string $pluralModelLabel = 'penilaian';

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
            ->with('academicUnit')
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
            ->description(fn (): HtmlString => static::semesterBannerHtml())
            ->recordUrl(fn (Mk $record): string => InputNilai::getUrl(['mk_id' => $record->id]))
            ->columns([
                Stack::make([
                    Split::make([
                        TextColumn::make('nama')
                            ->label('Mata kuliah')
                            ->searchable()
                            ->sortable()
                            ->weight(FontWeight::Bold),

                        TextColumn::make('total_sks')
                            ->label('SKS')
                            ->sortable()
                            ->size('sm'),
                    ]),

                    TextColumn::make('academicUnit.nama_lengkap')
                        ->label('Unit pemilik MK')
                        ->size('sm')
                        ->color('gray')
                        ->toggleable(isToggledHiddenByDefault: true),

                    TextColumn::make('ringkasan_kelas')
                        ->label('')
                        ->state(fn (Mk $record): HtmlString => PenilaianDosenService::ringkasanKelasHtml(
                            $record,
                            $user,
                            PenilaianSemesterTerpilih::currentId(),
                        ))
                        ->html(),
                ])->space(1),
            ])
            ->contentGrid(['md' => 2, 'xl' => 3])
            ->paginated([6, 12, 24])
            ->defaultPaginationPageOption(12)
            ->modifyQueryUsing(function (Builder $query) use ($user): Builder {
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
            ->deferFilters(false);
    }

    /**
     * @param  Builder<Mk>  $query
     * @return Builder<Mk>
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

    protected static function semesterFilter(User $user): SelectFilter
    {
        return SelectFilter::make('semester_terpilih')
            ->label('Semester')
            ->default(fn (): ?string => PenilaianSemesterTerpilih::currentId())
            ->selectablePlaceholder(false)
            ->columnSpanFull()
            ->schema([
                Select::make('value')
                    ->label('Semester')
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
                $semesterId = static::resolveSemesterFilterState($data['value'] ?? null);

                if (blank($semesterId)) {
                    return $query->whereRaw('1 = 0');
                }

                PenilaianSemesterTerpilih::set($semesterId);

                return static::scopeKeSemester($query, $user, $semesterId);
            })
            ->indicateUsing(function (array $data): ?string {
                $semesterId = static::resolveSemesterFilterState($data['value'] ?? null);

                if (blank($semesterId)) {
                    return null;
                }

                return 'Semester: '.PenilaianSemesterTerpilih::label($semesterId);
            });
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

    protected static function semesterBannerHtml(): HtmlString
    {
        $label = PenilaianSemesterTerpilih::label();

        return new HtmlString(
            '<div style="padding:12px 14px;border-radius:8px;background:#eff6ff;border:1px solid #bfdbfe;'
            .'color:#1e3a8a;font-size:13px;line-height:1.55;">'
            .'<span style="opacity:.88;">Semester terpilih:</span> <strong>'.e($label).'</strong>'
            .'</div>'
        );
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPenilaianDosens::route('/'),
        ];
    }
}
