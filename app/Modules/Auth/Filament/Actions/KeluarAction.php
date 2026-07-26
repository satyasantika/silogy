<?php

namespace App\Modules\Auth\Filament\Actions;

use App\Filament\Pages\Dashboard;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;

/**
 * Aksi Keluar dengan modal konfirmasi ringkas: Kembali, Beranda, Ya keluar.
 * Ganti peran sengaja tidak di sini — hanya di menu identitas pengguna.
 */
class KeluarAction
{
    public static function make(string $name = 'keluar'): Action
    {
        return Action::make($name)
            ->label('Keluar')
            ->icon(Heroicon::ArrowLeftEndOnRectangle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalIcon(Heroicon::ArrowLeftEndOnRectangle)
            ->modalHeading('Keluar aplikasi')
            ->modalDescription('Yakin akan keluar aplikasi?')
            ->modalSubmitActionLabel('Ya, keluar')
            ->modalCancelActionLabel('Kembali')
            ->modalFooterActionsAlignment(Alignment::End)
            ->extraModalFooterActions([
                Action::make('beranda')
                    ->label('Beranda')
                    ->icon(Heroicon::Home)
                    ->color('gray')
                    ->url(Dashboard::getUrl())
                    ->cancelParentActions(),
            ])
            ->action(function () {
                Filament::auth()->logout();

                session()->invalidate();
                session()->regenerateToken();

                return redirect()->to(Filament::getLoginUrl());
            });
    }
}
