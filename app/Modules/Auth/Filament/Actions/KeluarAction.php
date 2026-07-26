<?php

namespace App\Modules\Auth\Filament\Actions;

use App\Filament\Pages\Dashboard;
use App\Models\User;
use App\Modules\Auth\Support\ActiveRole;
use App\Modules\Auth\Support\PeranUnitFormFields;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Aksi Keluar dengan modal konfirmasi: Kembali, Beranda, Ganti peran (multi-role),
 * dan Ya keluar.
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
            ->extraModalFooterActions(fn (): array => array_values(array_filter([
                Action::make('beranda')
                    ->label('Beranda')
                    ->icon(Heroicon::Home)
                    ->color('gray')
                    ->url(Dashboard::getUrl())
                    ->cancelParentActions(),
                static::gantiPeranFooterAction(),
            ])))
            ->action(function () {
                Filament::auth()->logout();

                session()->invalidate();
                session()->regenerateToken();

                return redirect()->to(Filament::getLoginUrl());
            });
    }

    protected static function gantiPeranFooterAction(): ?Action
    {
        $user = auth()->user();

        if (! $user instanceof User || count(ActiveRole::ownedRoleNames($user)) <= 1) {
            return null;
        }

        return Action::make('gantiPeranDariKeluar')
            ->label('Ganti peran')
            ->icon(Heroicon::ArrowsRightLeft)
            ->color('gray')
            ->modalHeading('Ganti peran & unit')
            ->modalSubmitActionLabel('Simpan')
            ->schema(fn (): array => PeranUnitFormFields::schema($user))
            ->fillForm(fn (): array => [
                'role' => PeranUnitFormFields::defaultRole($user),
                'unit_id' => PeranUnitFormFields::defaultUnitId($user),
            ])
            ->action(function (array $data) {
                PeranUnitFormFields::apply($data);

                Notification::make()->title('Peran & unit diperbarui')->success()->send();

                return redirect()->to(request()->header('referer') ?? Dashboard::getUrl());
            })
            ->cancelParentActions();
    }
}
