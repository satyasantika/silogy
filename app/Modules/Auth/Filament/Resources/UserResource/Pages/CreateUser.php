<?php

namespace App\Modules\Auth\Filament\Resources\UserResource\Pages;

use App\Modules\Auth\Filament\Resources\UserResource;
use App\Support\Filament\Pages\BaseCreateRecord;

class CreateUser extends BaseCreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['email_verified_at'] ??= now();

        return $data;
    }
}
