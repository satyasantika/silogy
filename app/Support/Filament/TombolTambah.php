<?php

namespace App\Support\Filament;

use Filament\Actions\CreateAction;
use Filament\Support\Icons\Heroicon;

/**
 * Tombol Tambah (input manual satu per satu) yang berdampingan dengan
 * Impor massal: bulat, berikon plus, label hanya untuk pembaca layar.
 * Ukurannya ukuran tombol bawaan sehingga setinggi tombol Impor massal;
 * bentuk bulatnya dari kelas `silogy-tombol-bulat` di tema panel
 * (resources/views/filament/hooks/unsil-theme-styles.blade.php).
 */
class TombolTambah
{
    public static function make(): CreateAction
    {
        return CreateAction::make()
            ->label('Tambah')
            ->hiddenLabel()
            ->icon(Heroicon::Plus)
            ->tooltip('Tambah satu per satu')
            ->extraAttributes(['class' => 'silogy-tombol-bulat']);
    }
}
