<?php

namespace App\Modules\Mahasiswa\Filament\Resources\MahasiswaResource\Pages;

use App\Modules\Mahasiswa\Filament\Resources\MahasiswaResource;
use App\Modules\Mahasiswa\Policies\MahasiswaPolicy;
use App\Support\Filament\Pages\BaseCreateRecord;
use Illuminate\Auth\Access\AuthorizationException;

class CreateMahasiswa extends BaseCreateRecord
{
    protected static string $resource = MahasiswaResource::class;

    protected function beforeCreate(): void
    {
        $data = $this->form->getState();

        $allowed = app(MahasiswaPolicy::class)->canAccessStudyProgram(
            auth()->user(),
            $data['academic_unit_id'] ?? null,
        );

        if (! $allowed) {
            throw new AuthorizationException;
        }
    }
}
