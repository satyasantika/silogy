<?php

namespace App\Modules\MK\Filament\Concerns;

use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Services\MkCpmkAsesmenResetService;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

trait HasResetCpmkSubcpmkAsesmen
{
    protected function makeResetCpmkSubcpmkAsesmenAction(): Action
    {
        return Action::make('resetCpmkSubcpmkAsesmen')
            ->label('Reset CPMK & asesmen')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (Mk $record): string => 'Reset CPMK & asesmen '.$record->nama.'?')
            ->modalDescription(function (Mk $record): string {
                $jumlahCpmk = Cpmk::query()->where('mk_id', $record->id)->count();
                $jumlahAsesmen = KomponenPenilaian::query()->where('mk_id', $record->id)->count();

                return sprintf(
                    'Tindakan ini akan menghapus %d CPMK beserta seluruh Sub-CPMK-nya, dan %d komponen asesmen '
                    .'beserta pemetaan dan nilai mahasiswa terkait, pada mata kuliah ini. CPL, BoK, pemetaan CPL-MK, '
                    .'dan data mata kuliah itu sendiri TIDAK ikut terhapus. Tindakan ini tidak dapat dibatalkan.',
                    $jumlahCpmk,
                    $jumlahAsesmen,
                );
            })
            ->modalSubmitActionLabel('Ya, reset CPMK & asesmen')
            ->action(function (Mk $record): void {
                Gate::forUser(auth()->user())->authorize('update', $record);

                app(MkCpmkAsesmenResetService::class)->reset($record);

                Notification::make()
                    ->title('CPMK & asesmen direset')
                    ->body('CPMK, Sub-CPMK, dan komponen asesmen '.$record->nama.' telah dikosongkan.')
                    ->success()
                    ->send();
            });
    }
}
