<?php

namespace App\Modules\Kurikulum\Filament\Pages;

use App\Modules\Kurikulum\Filament\Support\Concerns\HasAnalisisMkForUnitType;
use App\Support\Filament\NavigationGroupPeran;
use App\Support\Filament\NavigationSortPeran;
use Filament\Actions\Contracts\HasActions;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Contracts\HasTable;

/**
 * Laporan CPL untuk Pimpinan/Tim Kurikulum universitas — mengikuti
 * kurikulum terpilih di session. Hanya aktif saat kurikulum yang dikerjakan
 * berada di level universitas; datanya rollup lintas semua prodi yang
 * mengadaptasi MK kurikulum universitas ini (lihat
 * AnalisisMkProdiService::mkUnitIdsUntukKurikulum()).
 */
class AnalisisMkUniversitas extends Page implements HasActions, HasTable
{
    use HasAnalisisMkForUnitType;

    protected string $view = 'filament.modules.kurikulum.pages.analisis-mk';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroupPeran::resolve('Laporan');
    }

    public static function getNavigationSort(): ?int
    {
        return NavigationSortPeran::resolve('analisis-mk', null);
    }

    protected static ?string $navigationLabel = 'Analisis MK Universitas';

    protected static ?string $title = 'Analisis MK Universitas';

    protected static ?string $slug = 'laporan/analisis-mk-universitas';

    public static function analisisUnitType(): string
    {
        return 'university';
    }
}
