<?php

namespace App\Modules\Auth\Filament\Concerns;

use App\Models\User;
use App\Modules\Auth\Support\PeranUnitFormFields;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Modal "Ganti peran & unit", dipakai bersama oleh WelcomeWidget (kartu
 * Selamat Datang) dan PeranUnitMenu (menu pengguna baru) — satu modal
 * gabungan (peran + unit sekaligus), bukan dua modal terpisah.
 *
 * Method HARUS public (bukan protected seperti pola trait aksi lain di
 * app ini, mis. HasSetBanyakKelasMk) karena dipanggil langsung dari view
 * Blade lewat `{{ $this->gantiPeranUnitAction() }}` — kode Blade yang
 * dikompilasi berjalan di luar konteks kelas, jadi hanya bisa memanggil
 * method public.
 */
trait HasGantiPeranUnitAction
{
    public function gantiPeranUnitAction(): Action
    {
        return Action::make('gantiPeranUnit')
            ->label('Ganti')
            ->link()
            ->icon(Heroicon::ArrowsRightLeft)
            ->modalHeading('Ganti peran & unit')
            ->modalSubmitActionLabel('Simpan')
            ->schema(function (): array {
                $user = auth()->user();

                return $user instanceof User ? PeranUnitFormFields::schema($user) : [];
            })
            ->fillForm(function (): array {
                $user = auth()->user();

                if (! $user instanceof User) {
                    return [];
                }

                return [
                    'role' => PeranUnitFormFields::defaultRole($user),
                    'unit_id' => PeranUnitFormFields::defaultUnitId($user),
                ];
            })
            ->action(function (array $data): void {
                PeranUnitFormFields::apply($data);

                Notification::make()->title('Peran & unit diperbarui')->success()->send();

                $this->redirect(request()->header('referer') ?? url('/dashboard'));
            });
    }
}
