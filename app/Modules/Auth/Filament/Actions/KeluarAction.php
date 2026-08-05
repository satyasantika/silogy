<?php

namespace App\Modules\Auth\Filament\Actions;

use App\Filament\Pages\Dashboard;
use App\Models\User;
use App\Modules\Auth\Filament\Pages\PilihPeranUnit;
use App\Modules\Auth\Support\ActiveRole;
use App\Modules\Auth\Support\PeranUnitFormFields;
use App\Modules\Institusi\Support\AcademicUnitTerpilih;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Lab404\Impersonate\Services\ImpersonateManager;

/**
 * Aksi Keluar dengan modal konfirmasi.
 *
 * Footer (non-mobile): dua paket tombol rata tengah —
 * 1) Tinggalkan impersonate, Ganti peran, Beranda
 * 2) Kembali, Ya keluar
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
            ->modalDescription('Yakin akan keluar aplikasi? Anda juga bisa ganti peran atau meninggalkan impersonate tanpa keluar.')
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            ->modalWidth(Width::FitContent)
            // Start agar urutan paket tidak di-reverse; pemusatan lewat CSS.
            ->modalFooterActionsAlignment(Alignment::Start)
            ->extraModalWindowAttributes([
                'class' => 'silogy-keluar-modal',
            ])
            ->extraModalFooterActions([
                ActionGroup::make([
                    Action::make('leaveImpersonateDariKeluar')
                        ->label('Tinggalkan impersonate')
                        ->icon(Heroicon::ArrowUturnLeft)
                        ->color('warning')
                        ->visible(fn (): bool => app(ImpersonateManager::class)->isImpersonating())
                        ->action(function () {
                            $manager = app(ImpersonateManager::class);

                            if (! $manager->isImpersonating()) {
                                return null;
                            }

                            $manager->leave();

                            session()->forget(ActiveRole::SESSION_KEY);
                            session()->forget(AcademicUnitTerpilih::SESSION_KEY);

                            return redirect()->to(url('/dashboard'));
                        }),

                    Action::make('gantiPeranDariKeluar')
                        ->label('Ganti peran')
                        ->icon(Heroicon::ArrowsRightLeft)
                        ->color('gray')
                        ->url(fn (): string => PilihPeranUnit::getUrl())
                        ->visible(fn (): bool => static::bisaGantiPeran())
                        ->cancelParentActions(),

                    Action::make('beranda')
                        ->label('Beranda')
                        ->icon(Heroicon::Home)
                        ->color('gray')
                        ->url(Dashboard::getUrl())
                        ->cancelParentActions(),
                ])->buttonGroup(),

                ActionGroup::make([
                    Action::make('kembali')
                        ->label('Kembali')
                        ->color('gray')
                        ->close(),

                    Action::make('yaKeluar')
                        ->label('Ya, keluar')
                        ->color('danger')
                        ->action(fn () => static::lakukanLogout()),
                ])->buttonGroup(),
            ])
            ->action(fn () => static::lakukanLogout());
    }

    public static function lakukanLogout(): mixed
    {
        Filament::auth()->logout();

        session()->invalidate();
        session()->regenerateToken();

        return redirect()->to(Filament::getLoginUrl());
    }

    public static function bisaGantiPeran(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        $roles = ActiveRole::ownedRoleNames($user);
        $peranAktif = PeranUnitFormFields::defaultRole($user);

        return count($roles) > 1
            || PeranUnitFormFields::roleMembutuhkanPilihanUnit($user, $peranAktif);
    }
}
