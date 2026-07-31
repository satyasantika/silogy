<?php

namespace App\Modules\MK\Filament\Support\Concerns;

use App\Modules\Kalender\Support\SemesterTerpilih;
use App\Modules\MK\Support\MkTerpilih;
use Filament\Forms\Components\Select;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Filter semester terpilih — hanya Koordinator Mata Kuliah (opsi dari tabel semesters).
 */
trait HasSemesterTerpilihFilter
{
    protected static function usesSemesterTerpilihFilter(): bool
    {
        return SemesterTerpilih::berlakuUntukUser();
    }

    /**
     * @param  callable(Builder<Model>, string): Builder<Model>  $applyScope
     */
    protected static function applySemesterTerpilihTable(Table $table, callable $applyScope): Table
    {
        if (! static::usesSemesterTerpilihFilter()) {
            return $table;
        }

        return $table
            ->filters([
                static::semesterTerpilihFilter($applyScope),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(1)
            ->deferFilters(false)
            ->modifyQueryUsing(function (Builder $query) use ($applyScope): Builder {
                $mkId = MkTerpilih::currentId();
                $semesterId = SemesterTerpilih::currentId($mkId);

                if (blank($mkId) || blank($semesterId)) {
                    return $query->whereRaw('1 = 0');
                }

                return $applyScope($query, $semesterId);
            });
    }

    /**
     * @param  callable(Builder<Model>, string): Builder<Model>  $applyScope
     * @param  array{indikator?: bool, labelTersembunyi?: bool}  $opsi
     */
    protected static function semesterTerpilihFilter(callable $applyScope, array $opsi = []): SelectFilter
    {
        $tampilkanIndikator = $opsi['indikator'] ?? true;
        $labelTersembunyi = $opsi['labelTersembunyi'] ?? false;

        $filter = SelectFilter::make('semester_terpilih')
            ->label('Semester')
            ->visible(fn (): bool => static::usesSemesterTerpilihFilter())
            ->default(fn (): ?string => SemesterTerpilih::currentId())
            ->selectablePlaceholder(false)
            ->columnSpanFull()
            ->schema([
                Select::make('value')
                    ->label('Semester')
                    ->hiddenLabel($labelTersembunyi)
                    ->prefix($labelTersembunyi ? 'Semester' : null)
                    ->options(fn (): array => SemesterTerpilih::options())
                    ->default(fn (): ?string => SemesterTerpilih::currentId())
                    ->selectablePlaceholder(false)
                    ->native(true)
                    ->searchable(false)
                    ->live()
                    ->afterStateHydrated(function (Select $component, $state): void {
                        $mkId = MkTerpilih::currentId();
                        $resolved = static::resolveSemesterFilterState($mkId, $state);

                        if (blank($resolved) || blank($mkId)) {
                            return;
                        }

                        $component->state($resolved);
                        SemesterTerpilih::set($mkId, $resolved);
                    }),
            ])
            ->query(function (Builder $query, array $data) use ($applyScope): Builder {
                $mkId = MkTerpilih::currentId();
                $semesterId = static::resolveSemesterFilterState($mkId, $data['value'] ?? null);

                if (blank($mkId) || blank($semesterId)) {
                    return $query;
                }

                SemesterTerpilih::set($mkId, $semesterId);

                return $applyScope($query, $semesterId);
            });

        if (! $tampilkanIndikator) {
            return $filter->indicateUsing(fn (): array => []);
        }

        return $filter->indicateUsing(function (array $data): ?string {
            $semesterId = static::resolveSemesterFilterState(MkTerpilih::currentId(), $data['value'] ?? null);

            if (blank($semesterId)) {
                return null;
            }

            return 'Semester: '.SemesterTerpilih::label($semesterId);
        });
    }

    protected static function resolveSemesterFilterState(?string $mkId, mixed $state = null): ?string
    {
        if (blank($mkId)) {
            return null;
        }

        $options = SemesterTerpilih::options($mkId);

        if ($options === []) {
            return null;
        }

        $candidate = filled($state) ? (string) $state : SemesterTerpilih::currentId($mkId);

        if (filled($candidate) && array_key_exists($candidate, $options)) {
            return $candidate;
        }

        return SemesterTerpilih::defaultId();
    }
}
