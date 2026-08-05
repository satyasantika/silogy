<?php

namespace App\Modules\Kurikulum\Filament\Pages;

use App\Modules\Kurikulum\Filament\Support\Concerns\HasLaporanAnalisisCplPimpinan;
use App\Support\Filament\NavigationGroupPeran;
use App\Support\Filament\NavigationSortPeran;
use Filament\Actions\Contracts\HasActions;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Contracts\HasTable;

/**
 * Menu laporan Pimpinan: grafik radar CPL per MK penyumbang
 * (setara tab "Grafik CPL" di Analisis MK).
 */
class GrafikCpl extends Page implements HasActions, HasTable
{
    use HasLaporanAnalisisCplPimpinan;

    protected string $view = 'filament.modules.kurikulum.pages.grafik-cpl';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroupPeran::resolve('Laporan');
    }

    public static function getNavigationSort(): ?int
    {
        return NavigationSortPeran::resolve('grafik-cpl', 20);
    }

    protected static ?string $navigationLabel = 'Grafik CPL';

    protected static ?string $title = 'Grafik CPL';

    protected static ?string $slug = 'laporan/grafik-cpl';
}
