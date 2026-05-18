<?php

namespace App\Modules\Kalkulasi\Filament\Widgets;

use App\Modules\Kalkulasi\Filament\Support\Concerns\CanAccessDashboardWidgets;
use App\Modules\Kalkulasi\Models\HasilCplMkUnit;
use App\Modules\Kalkulasi\Services\DashboardCplDataService;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\Support\Htmlable;

class CplPerMkUnitTable extends TableWidget
{
    use CanAccessDashboardWidgets;
    use InteractsWithPageFilters;

    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return static::canViewDashboardWidgets();
    }

    public function getTableHeading(): string|Htmlable|null
    {
        return 'Drill-down Capaian per MK & Penawaran (MK Unit)';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(HasilCplMkUnit::query()->whereRaw('1 = 0'))
            ->paginated(false)
            ->records(fn (): array => $this->resolveMkUnitRows())
            ->columns([
                TextColumn::make('mk_nama')
                    ->label('Mata Kuliah'),
                TextColumn::make('mk_unit_kode')
                    ->label('Kode MK Unit'),
                TextColumn::make('rata_rata')
                    ->label('Rata-rata')
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('jumlah_mahasiswa')
                    ->label('Jumlah Mahasiswa')
                    ->numeric(),
                TextColumn::make('persentase_tercapai')
                    ->label('% Tercapai')
                    ->badge()
                    ->formatStateUsing(fn (?float $state): string => $state !== null
                        ? number_format($state, 2).'%'
                        : '—')
                    ->color(fn (?float $state): string|array|null => match (true) {
                        $state === null => 'gray',
                        $state < 60 => Color::Red,
                        $state < 75 => Color::Amber,
                        default => Color::Green,
                    }),
            ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function resolveMkUnitRows(): array
    {
        $academicUnitId = $this->pageFilters['academic_unit_id'] ?? null;
        $semesterId = $this->pageFilters['semester_id'] ?? null;
        $cplId = $this->pageFilters['cpl_id'] ?? null;

        if ($academicUnitId === null || $semesterId === null || $cplId === null) {
            return [];
        }

        return app(DashboardCplDataService::class)->mkUnitTableRows(
            (string) $academicUnitId,
            (string) $semesterId,
            (string) $cplId,
        );
    }
}
