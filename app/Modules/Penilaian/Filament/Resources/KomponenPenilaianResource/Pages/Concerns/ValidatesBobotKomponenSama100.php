<?php

namespace App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource\Pages\Concerns;

use App\Modules\Penilaian\Rules\BobotKomponenSama100Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

trait ValidatesBobotKomponenSama100
{
    protected function validateBobotKomponenSama100(array $data): void
    {
        $kelasMkId = $data['kelas_mk_id'] ?? $this->getRecord()?->kelas_mk_id;

        if ($kelasMkId === null) {
            return;
        }

        $ignoreId = $this->getRecord()?->getKey();

        $validator = Validator::make(
            ['bobot' => $data['bobot'] ?? 0],
            [
                'bobot' => [
                    'required',
                    'numeric',
                    new BobotKomponenSama100Rule($kelasMkId, is_string($ignoreId) ? $ignoreId : null),
                ],
            ],
        );

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }
    }
}
