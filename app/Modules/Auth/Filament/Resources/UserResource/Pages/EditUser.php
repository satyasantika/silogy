<?php

namespace App\Modules\Auth\Filament\Resources\UserResource\Pages;

use App\Modules\Auth\Filament\Resources\UserResource;
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
                // '/dashboard' saja tidak cukup: Livewire::redirect() mengirim
                // string ini apa adanya ke browser (tidak lewat UrlGenerator),
                // jadi di deployment subpath (mis. /demo-silogy) hasilnya
                // menuju ke akar domain, bukan /demo-silogy/dashboard.
                ->redirectTo(route('filament.admin.pages.dashboard')),
            DeleteAction::make()
                ->iconButton()
                ->tooltip('Hapus'),
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
