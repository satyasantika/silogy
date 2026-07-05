<?php

namespace App\Modules\Kurikulum\Filament\Support\Concerns;

use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\Layout\Component as LayoutComponent;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

/**
 * Filter "Kurikulum" yang selalu tampil di atas tabel (Profil, CPL, BoK,
 * MK, Penawaran MK). Pilihan tersimpan di session sehingga konsisten
 * antarhalaman; default mengikuti KurikulumTerpilih.
 */
trait HasKurikulumTerpilihFilter
{
    /**
     * Terapkan banner kurikulum terpilih + scope query (tanpa filter dropdown).
     *
     * @param  callable(Builder<Model>, Kurikulum): Builder<Model>  $applyScope
     */
    protected static function applyKurikulumTerpilihTable(Table $table, callable $applyScope): Table
    {
        return static::decorateKurikulumTerpilihTable($table, $applyScope);
    }

    /**
     * Filter kurikulum + filter tambahan (mis. semester/MK pada Kelas MK).
     *
     * @param  array<int, \Filament\Tables\Filters\BaseFilter>  $extraFilters
     * @param  callable(Builder<Model>, Kurikulum): Builder<Model>  $applyScope
     */
    protected static function applyKurikulumTerpilihTableWithExtraFilters(
        Table $table,
        callable $applyScope,
        array $extraFilters = [],
    ): Table {
        return static::decorateKurikulumTerpilihTable($table, $applyScope)
            ->filters([
                static::kurikulumTerpilihFilter($applyScope),
                ...$extraFilters,
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(1)
            ->deferFilters(false);
    }

    /**
     * Terapkan filter Kurikulum + layout card grid seragam (Profil, CPL, BoK, dll.).
     *
     * @param  array<int, LayoutComponent|\Filament\Tables\Columns\Column>  $cardSchema
     * @param  callable(Builder<Model>, Kurikulum): Builder<Model>  $applyScope
     * @param  array<string, int>  $contentGrid
     */
    protected static function applyKurikulumTerpilihCardTable(
        Table $table,
        array $cardSchema,
        callable $applyScope,
        array $contentGrid = ['md' => 2, 'xl' => 3],
        bool $withKurikulumFilter = false,
    ): Table {
        $table = $table
            ->columns([
                Stack::make($cardSchema)->space(1),
            ])
            ->contentGrid($contentGrid)
            ->paginated([6, 12, 24])
            ->defaultPaginationPageOption(12);

        if ($withKurikulumFilter) {
            return $table
                ->filters([
                    static::kurikulumTerpilihFilter($applyScope),
                ])
                ->filtersLayout(FiltersLayout::AboveContent)
                ->filtersFormColumns(1)
                ->deferFilters(false);
        }

        return static::applyKurikulumTerpilihTable($table, $applyScope);
    }

    /**
     * @param  callable(Builder<Model>, Kurikulum): Builder<Model>  $applyScope
     */
    protected static function decorateKurikulumTerpilihTable(Table $table, callable $applyScope): Table
    {
        return $table
            ->description(fn (): HtmlString => KurikulumTerpilih::bannerHtml())
            ->modifyQueryUsing(function (Builder $query) use ($applyScope): Builder {
                $kurikulum = KurikulumTerpilih::current();

                if (! $kurikulum instanceof Kurikulum) {
                    if (static::kurikulumTerpilihWajib()) {
                        return $query->whereRaw('1 = 0');
                    }

                    return $query;
                }

                return $applyScope($query, $kurikulum);
            });
    }

    /**
     * Halaman yang wajib punya kurikulum terpilih sebelum menampilkan data.
     * Kelas MK meng-override ke false agar Admin prodi tetap melihat kelas
     * walau belum ada kurikulum pada unitnya.
     */
    protected static function kurikulumTerpilihWajib(): bool
    {
        return true;
    }

    /**
     * @param  callable(Builder<Model>, Kurikulum): Builder<Model>  $applyScope
     */
    protected static function kurikulumTerpilihFilter(callable $applyScope): SelectFilter
    {
        return SelectFilter::make('kurikulum_terpilih')
            ->label('Kurikulum')
            ->default(fn (): ?string => static::resolveKurikulumFilterState())
            ->selectablePlaceholder(false)
            ->columnSpanFull()
            ->schema([
                Select::make('value')
                    ->label('Kurikulum')
                    ->options(fn (): array => static::resolveKurikulumFilterOptions())
                    ->default(fn (): ?string => static::resolveKurikulumFilterState())
                    ->selectablePlaceholder(false)
                    ->native(true)
                    ->searchable(false)
                    ->live()
                    ->afterStateHydrated(function (Select $component, $state): void {
                        $resolved = static::resolveKurikulumFilterState($state);

                        if (blank($resolved)) {
                            return;
                        }

                        $component->state($resolved);
                        KurikulumTerpilih::set($resolved);
                    }),
            ])
            ->query(function (Builder $query, array $data) use ($applyScope): Builder {
                $id = static::resolveKurikulumFilterId($data);

                if (blank($id)) {
                    return $query;
                }

                KurikulumTerpilih::set($id);

                $kurikulum = Kurikulum::query()->with('academicUnit')->find($id);

                if (! $kurikulum) {
                    return $query;
                }

                return $applyScope($query, $kurikulum);
            })
            ->indicateUsing(function (array $data): ?string {
                $id = static::resolveKurikulumFilterId($data);

                if (blank($id)) {
                    return null;
                }

                $kurikulum = Kurikulum::query()->with('academicUnit')->find($id);

                return $kurikulum ? 'Kurikulum: '.KurikulumTerpilih::label($kurikulum) : null;
            });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected static function resolveKurikulumFilterId(array $data): ?string
    {
        return static::resolveKurikulumFilterState($data['value'] ?? null);
    }

    /**
     * Unit pemilik opsi kurikulum; null = ikuti scope default KurikulumTerpilih.
     *
     * @return Collection<int, string>|null
     */
    protected static function kurikulumTerpilihFilterUnitIds(): ?Collection
    {
        return null;
    }

    /**
     * @return array<string, string>
     */
    protected static function resolveKurikulumFilterOptions(): array
    {
        $unitIds = static::kurikulumTerpilihFilterUnitIds();

        if ($unitIds instanceof Collection) {
            return KurikulumTerpilih::optionsForUnits($unitIds);
        }

        return KurikulumTerpilih::options();
    }

    protected static function resolveKurikulumFilterState(mixed $state = null): ?string
    {
        $options = static::resolveKurikulumFilterOptions();

        if ($options === []) {
            return null;
        }

        $candidate = filled($state) ? (string) $state : KurikulumTerpilih::currentId();

        if (filled($candidate) && array_key_exists($candidate, $options)) {
            return $candidate;
        }

        $defaultId = KurikulumTerpilih::currentId();

        if (filled($defaultId) && array_key_exists($defaultId, $options)) {
            return $defaultId;
        }

        return (string) array_key_first($options);
    }
}
