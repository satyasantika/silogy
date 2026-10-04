<?php

namespace App\Modules\Auth\Filament\Resources\UserResource\Pages;

use App\Models\User;
use App\Modules\Auth\Filament\Resources\UserResource;
use App\Modules\Auth\Support\PeranUnitFormFields;
use App\Support\Filament\Pages\BaseEditRecord;
use Filament\Actions\DeleteAction;
use STS\FilamentImpersonate\Actions\Impersonate;

class EditUser extends BaseEditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Impersonate::make()
                ->iconButton()
                ->tooltip('Peniruan')
                ->record($this->getRecord())
                ->requiresConfirmation()
                ->modalHeading('Impersonate pengguna ini?')
                ->modalDescription(function (): string {
                    /** @var User $record */
                    $record = $this->getRecord();

                    return "Tindakan ini akan masuk sebagai {$record->full_name} ({$record->username}). Semua aktivitas Anda selanjutnya tercatat sebagai pengguna tersebut sampai Anda memilih \"Tinggalkan impersonate\".";
                })
                ->modalSubmitActionLabel('Lanjutkan')
                // Closure — lihat catatan di UserResource.php pada action yang sama.
                ->redirectTo(fn (): string => PeranUnitFormFields::redirectUrlAfterImpersonateStart())
                // Lihat catatan di UserResource.php pada action yang sama.
                ->backTo(fn (): ?string => request()->header('referer')),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        return $data;
    }
}
