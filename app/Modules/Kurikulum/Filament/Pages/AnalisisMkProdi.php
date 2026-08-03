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
 * Laporan CPL untuk Pimpinan/Tim Kurikulum prodi — mengikuti kurikulum
 * terpilih di session (sama seperti CPL/BoK/MK/interaksi). Hanya aktif
 * saat kurikulum yang dikerjakan berada di level program studi; lihat
 * AnalisisMkFakultas/AnalisisMkUniversitas untuk level lain.
 */
class AnalisisMkProdi extends Page implements HasActions, HasTable
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

    protected static ?string $navigationLabel = 'Analisis MK';

    protected static ?string $title = 'Analisis MK';

    protected static ?string $slug = 'laporan/analisis-mk-prodi';

    public static function analisisUnitType(): string
    {
        return 'study_program';
    }
}
