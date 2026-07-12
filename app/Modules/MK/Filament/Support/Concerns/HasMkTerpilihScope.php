<?php

namespace App\Modules\MK\Filament\Support\Concerns;

use App\Modules\MK\Support\MkTerpilih;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\Layout\Component as LayoutComponent;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * Scope tabel CPMK / Sub-CPMK / Asesmen ke MK terpilih dari halaman
 * Mata Kuliah Koordinator (bukan filter yang bisa diganti di sini).
 */
trait HasMkTerpilihScope
{
    /**
     * @param  array<int, LayoutComponent|Column>  $cardSchema
     * @param  callable(Builder<Model>, string): Builder<Model>  $applyMkScope
     */
    protected static function applyMkTerpilihCardTable(
        Table $table,
        array $cardSchema,
        callable $applyMkScope,
        array $contentGrid = ['md' => 2, 'xl' => 3],
    ): Table {
        return $table
            ->description(fn (): HtmlString => MkTerpilih::bannerHtml())
            ->columns([
                Stack::make($cardSchema)->space(1),
            ])
            ->contentGrid($contentGrid)
            ->paginated(false)
            ->modifyQueryUsing(function (Builder $query) use ($applyMkScope): Builder {
                $mkId = MkTerpilih::currentId();

                if (blank($mkId)) {
                    return $query->whereRaw('1 = 0');
                }

                return $applyMkScope($query, $mkId);
            });
    }
}
