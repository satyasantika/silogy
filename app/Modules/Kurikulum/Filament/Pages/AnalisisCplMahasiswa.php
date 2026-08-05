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
 * Menu laporan Pimpinan: hasil analisis CPL per mahasiswa
 * (setara tab "Hasil Analisis Asesmen CPL per Mahasiswa" di Analisis MK).
 */
class AnalisisCplMahasiswa extends Page implements HasActions, HasTable
{
    use HasLaporanAnalisisCplPimpinan;

    protected string $view = 'filament.modules.kurikulum.pages.analisis-cpl-mahasiswa';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroupPeran::resolve('Laporan');
    }

    public static function getNavigationSort(): ?int
    {
        return NavigationSortPeran::resolve('analisis-cpl-mahasiswa', 30);
    }

    protected static ?string $navigationLabel = 'Analisis per Mahasiswa';

    protected static ?string $title = 'Analisis per Mahasiswa';

    protected static ?string $slug = 'laporan/analisis-cpl-mahasiswa';
}
