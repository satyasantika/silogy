<?php

namespace App\Support\Filament\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Icons\Heroicon;

/**
 * Halaman create standar: pasangan tombol Simpan|Batal ber-icon.
 * Setelah simpan berhasil, Filament mengarahkan ke halaman edit
 * yang pasangan tombolnya menjadi Simpan|Kembali (lihat BaseEditRecord).
 */
abstract class BaseCreateRecord extends CreateRecord
{
    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->icon(Heroicon::Check);
    }

    protected function getCreateAnotherFormAction(): Action
    {
        return parent::getCreateAnotherFormAction()
            ->icon(Heroicon::Plus);
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()
            ->label('Batal')
            ->icon(Heroicon::XMark);
    }
}
