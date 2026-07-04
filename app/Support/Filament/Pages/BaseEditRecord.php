<?php

namespace App\Support\Filament\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

/**
 * Halaman edit standar: record sudah tersimpan, sehingga pendamping
 * tombol Simpan bukan lagi "Batal" melainkan "Kembali" (ke daftar).
 */
abstract class BaseEditRecord extends EditRecord
{
    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->icon(Heroicon::Check);
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()
            ->label('Kembali')
            ->icon(Heroicon::ArrowUturnLeft)
            ->url(static::getResource()::getUrl('index'));
    }
}
