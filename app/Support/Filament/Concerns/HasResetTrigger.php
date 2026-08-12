<?php

namespace App\Support\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Tombol titik-tiga vertikal (icon-only, di samping tombol impor massal)
 * untuk memicu reset (kosongkan) data satu resource. Nonaktif — BUKAN
 * disembunyikan — bila data resource itu sudah dipakai/diinteraksikan oleh
 * tabel lain (mis. CPL yang sudah dipetakan ke BoK), supaya user tetap
 * bisa membuka dropdown dan melihat aksinya ada tapi tidak bisa diklik,
 * daripada aksi itu hilang tanpa penjelasan.
 */
trait HasResetTrigger
{
    protected function makeResetTriggerAction(): ActionGroup
    {
        return ActionGroup::make([
            Action::make('resetData')
                ->label('Reset '.$this->resetEntitasLabel())
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Reset '.$this->resetEntitasLabel().'?')
                ->modalDescription($this->resetModalDescription())
                ->modalSubmitActionLabel('Ya, reset '.$this->resetEntitasLabel())
                ->disabled(fn (): bool => ! $this->resetBisaDilakukan())
                ->action(function (): void {
                    $this->resetJalankan();

                    Notification::make()
                        ->title($this->resetEntitasLabel().' direset')
                        ->success()
                        ->send();

                    $this->resetTable();
                }),
        ])
            ->icon(Heroicon::OutlinedEllipsisVertical)
            ->color('gray')
            ->iconButton()
            ->tooltip('Reset '.$this->resetEntitasLabel());
    }

    abstract protected function resetEntitasLabel(): string;

    abstract protected function resetModalDescription(): string;

    abstract protected function resetBisaDilakukan(): bool;

    abstract protected function resetJalankan(): void;
}
