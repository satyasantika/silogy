<?php

namespace App\Modules\Kurikulum\Filament\Support\Concerns;

use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use Filament\Tables\Columns\Layout\Component as LayoutComponent;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Filter "Kurikulum" yang selalu tampil di atas tabel (Profil, CPL, BoK,
 * MK, Penawaran MK). Pilihan tersimpan di session sehingga konsisten
 * antarhalaman; default mengikuti KurikulumTerpilih.
 */
trait HasKurikulumTerpilihFilter
{
    /**
     * Terapkan hanya filter Kurikulum dengan layout seragam di atas tabel.
     *
     * @param  callable(Builder<Model>, Kurikulum): Builder<Model>  $applyScope
     */
    protected static function applyKurikulumTerpilihTable(Table $table, callable $applyScope): Table
    {
        return $table
            ->filters([
                static::kurikulumTerpilihFilter($applyScope),
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
     */
    /**
     * @param  array<string, int>  $contentGrid
     */
    protected static function applyKurikulumTerpilihCardTable(
        Table $table,
        array $cardSchema,
        callable $applyScope,
        array $contentGrid = ['md' => 2, 'xl' => 3],
    ): Table {
        return static::applyKurikulumTerpilihTable(
            $table
                ->columns([
                    Stack::make($cardSchema)->space(1),
                ])
                ->contentGrid($contentGrid)
                ->paginated([6, 12, 24])
                ->defaultPaginationPageOption(12),
            $applyScope,
        );
    }

    /**
     * @param  callable(Builder<Model>, Kurikulum): Builder<Model>  $applyScope
     */
    protected static function kurikulumTerpilihFilter(callable $applyScope): SelectFilter
    {
        return SelectFilter::make('kurikulum_terpilih')
            ->label('Kurikulum')
            ->options(fn (): array => KurikulumTerpilih::options())
            ->default(fn (): ?string => KurikulumTerpilih::currentId())
            ->selectablePlaceholder(false)
            ->searchable()
            ->columnSpanFull()
            ->query(function (Builder $query, array $data) use ($applyScope): Builder {
                $id = $data['value'] ?? null;

                if (blank($id)) {
                    return $query;
                }

                // Sinkronkan pilihan ke session agar halaman lain mengikuti.
                KurikulumTerpilih::set($id);

                $kurikulum = Kurikulum::query()->with('academicUnit')->find($id);

                if (! $kurikulum) {
                    return $query;
                }

                return $applyScope($query, $kurikulum);
            })
            ->indicateUsing(function (array $data): ?string {
                $id = $data['value'] ?? null;

                if (blank($id)) {
                    return null;
                }

                $kurikulum = Kurikulum::query()->with('academicUnit')->find($id);

                return $kurikulum ? 'Kurikulum: '.KurikulumTerpilih::label($kurikulum) : null;
            });
    }
}
