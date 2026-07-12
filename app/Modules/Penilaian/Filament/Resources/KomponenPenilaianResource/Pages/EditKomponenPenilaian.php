<?php

namespace App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource\Pages;

use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Support\Filament\Pages\BaseEditRecord;
use Filament\Actions\DeleteAction;
use Illuminate\Database\Eloquent\Model;

class EditKomponenPenilaian extends BaseEditRecord
{
    protected static string $resource = KomponenPenilaianResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var KomponenPenilaian $record */
        $record->update(collect($data)->only(['kode', 'evaluasi_id', 'nama', 'bobot'])->all());

        return $record;
    }
}
