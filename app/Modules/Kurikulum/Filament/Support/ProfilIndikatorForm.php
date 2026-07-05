<?php

namespace App\Modules\Kurikulum\Filament\Support;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Illuminate\Support\Str;

/**
 * Skema repeater indikator profil lulusan — dipakai di form profil
 * (menu Profil Lulusan dan relation manager kurikulum).
 */
class ProfilIndikatorForm
{
    public static function repeater(): Repeater
    {
        return Repeater::make('indikators')
            ->label('Indikator')
            ->relationship()
            ->schema([
                Textarea::make('nama')
                    ->label('Nama indikator')
                    ->required()
                    ->rows(1)
                    ->autosize()
                    ->columnSpanFull(),
            ])
            ->defaultItems(1)
            ->addActionLabel('Tambah indikator')
            ->reorderableWithDragAndDrop()
            ->orderColumn('urutan')
            ->collapsible()
            ->itemLabel(fn (array $state): ?string => filled($state['nama'] ?? null)
                ? Str::limit(trim(strip_tags((string) $state['nama'])), 60)
                : 'Indikator baru')
            ->columnSpanFull();
    }
}
