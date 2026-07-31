<?php

namespace App\Modules\MK\Filament\Widgets;

use App\Models\User;
use App\Modules\Kalkulasi\Filament\Support\Concerns\CanAccessDashboardWidgets;
use App\Modules\MK\Services\DashboardKoordinatorMkService;
use App\Modules\MK\Models\MkUnit;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Sepuluh penawaran mata kuliah (mk_units) dengan rerata capaian CPL
 * tertinggi, discope ke MK yang dikoordinasikan user — analog
 * MkCapaianTertinggiTable milik Tim Kurikulum.
 */
class KoordinatorMkCapaianTertinggiTable extends TableWidget
{
    use CanAccessDashboardWidgets;

    public const JUMLAH_MK = 10;

    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return static::canViewDashboardWidgets()
            && static::isDashboardKoordinatorMk();
    }

    public function getTableHeading(): string|Htmlable|null
    {
        return '10 Capaian Mata Kuliah Tertinggi yang Ditawarkan';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(MkUnit::query()->whereRaw('1 = 0'))
            ->paginated(false)
            ->emptyStateHeading('Belum ada capaian mata kuliah')
            ->emptyStateDescription('Peringkat muncul setelah penilaian mata kuliah selesai dikalkulasi.')
            ->records(fn (): array => $this->barisMkTertinggi())
            ->columns([
                TextColumn::make('mk_nama')
                    ->label('Mata Kuliah'),
                TextColumn::make('mk_unit_kode')
                    ->label('Kode Penawaran'),
                TextColumn::make('kurikulum_nama')
                    ->label('Kurikulum'),
                TextColumn::make('jumlah_mahasiswa')
                    ->label('Jumlah Mahasiswa')
                    ->numeric(),
                TextColumn::make('rata_rata')
                    ->label('Rata-rata Capaian')
                    ->badge()
                    ->formatStateUsing(fn (?float $state): string => $state !== null
                        ? number_format($state, 2)
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
    public function barisMkTertinggi(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        return app(DashboardKoordinatorMkService::class)->mkTertinggi($user, self::JUMLAH_MK);
    }
}
