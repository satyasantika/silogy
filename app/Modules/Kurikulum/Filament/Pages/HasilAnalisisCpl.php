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
 * Menu laporan Pimpinan: ringkasan hasil analisis asesmen CPL
 * (setara tab "Hasil Analisis Asesmen CPL" di Analisis MK).
 */
class HasilAnalisisCpl extends Page implements HasActions, HasTable
{
    use HasLaporanAnalisisCplPimpinan;

    protected string $view = 'filament.modules.kurikulum.pages.hasil-analisis-cpl';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroupPeran::resolve('Laporan');
    }

    public static function getNavigationSort(): ?int
    {
        return NavigationSortPeran::resolve('hasil-analisis-cpl', 10);
    }

    protected static ?string $navigationLabel = 'Hasil Analisis CPL';

    protected static ?string $title = 'Hasil Analisis CPL';

    protected static ?string $slug = 'laporan/hasil-analisis-cpl';
}
