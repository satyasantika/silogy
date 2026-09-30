<?php

namespace App\Modules\MK\Filament\Resources\PerubahanCpmkResource\Pages;

use App\Models\User;
use App\Modules\MK\Filament\Resources\PerubahanCpmkResource;
use App\Modules\MK\Models\PerubahanCpmkRequest;
use App\Modules\MK\Services\PerubahanCpmkService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ListPerubahanCpmks extends ListRecords
{
    protected static string $resource = PerubahanCpmkResource::class;

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->recordActions([
                ViewAction::make(),
                static::setujuiAction(),
                static::tolakAction(),
            ]);
    }

    public static function setujuiAction(): Action
    {
        return Action::make('setujui')
            ->label('Setujui')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Setujui perubahan CPMK')
            ->modalDescription(
                'Koordinator MK akan bisa menyusun ulang CPMK mata kuliah ini untuk semester tersebut. '
                .'CPMK yang diubah memengaruhi pemetaannya ke CPL.',
            )
            ->schema([
                Textarea::make('catatan_peninjau')
                    ->label('Catatan (opsional)')
                    ->rows(3),
            ])
            ->visible(fn (PerubahanCpmkRequest $record): bool => Auth::user()?->can('setujui', $record) ?? false)
            ->action(function (array $data, PerubahanCpmkRequest $record): void {
                $user = Auth::user();

                if (! $user instanceof User) {
                    return;
                }

                app(PerubahanCpmkService::class)->setujui(
                    $record,
                    $user,
                    filled($data['catatan_peninjau'] ?? null) ? (string) $data['catatan_peninjau'] : null,
                );

                Notification::make()
                    ->title('Usulan disetujui')
                    ->body('Koordinator MK kini boleh mengubah CPMK pada semester tersebut.')
                    ->success()
                    ->send();
            });
    }

    public static function tolakAction(): Action
    {
        return Action::make('tolak')
            ->label('Tolak')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->modalHeading('Tolak perubahan CPMK')
            ->schema([
                Textarea::make('catatan_peninjau')
                    ->label('Alasan penolakan')
                    // Wajib: penolakan tanpa alasan membuat koordinator tidak
                    // tahu apa yang harus diperbaiki sebelum mengajukan lagi.
                    ->required()
                    ->rows(3),
            ])
            ->visible(fn (PerubahanCpmkRequest $record): bool => Auth::user()?->can('tolak', $record) ?? false)
            ->action(function (array $data, PerubahanCpmkRequest $record): void {
                $user = Auth::user();

                if (! $user instanceof User) {
                    return;
                }

                app(PerubahanCpmkService::class)->tolak($record, $user, (string) $data['catatan_peninjau']);

                Notification::make()
                    ->title('Usulan ditolak')
                    ->body('CPMK semester tersebut tetap seperti sebelumnya.')
                    ->warning()
                    ->send();
            });
    }
}
