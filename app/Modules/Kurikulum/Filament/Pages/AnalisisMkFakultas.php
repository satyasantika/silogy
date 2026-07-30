<?php

namespace App\Modules\Kurikulum\Filament\Pages;

use App\Modules\Kurikulum\Filament\Support\Concerns\HasAnalisisMkForUnitType;
use Filament\Actions\Contracts\HasActions;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Contracts\HasTable;

/**
 * Laporan CPL untuk Pimpinan/Tim Kurikulum fakultas — mengikuti kurikulum
 * terpilih di session. Hanya aktif saat kurikulum yang dikerjakan berada
 * di level fakultas; datanya rollup lintas semua prodi yang mengadaptasi
 * MK kurikulum fakultas ini (lihat AnalisisMkProdiService::mkUnitIdsUntukKurikulum()).
 */
class AnalisisMkFakultas extends Page implements HasActions, HasTable
{
    use HasAnalisisMkForUnitType;

    protected string $view = 'filament.modules.kurikulum.pages.analisis-mk';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static string|\UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?string $navigationLabel = 'Analisis MK Fakultas';

    protected static ?string $title = 'Analisis MK Fakultas';

    protected static ?string $slug = 'laporan/analisis-mk-fakultas';

    public static function analisisUnitType(): string
    {
        return 'faculty';
    }
}
