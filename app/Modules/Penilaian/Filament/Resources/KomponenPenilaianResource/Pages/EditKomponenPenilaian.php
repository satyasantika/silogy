<?php

namespace App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource\Pages;

use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource;
use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource\Pages\Concerns\ValidatesBobotKomponenSama100;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Services\KomponenPenilaianMassalService;
use App\Support\Filament\Pages\BaseEditRecord;
use Filament\Actions\DeleteAction;
use Illuminate\Database\Eloquent\Model;

class EditKomponenPenilaian extends BaseEditRecord
{
    use ValidatesBobotKomponenSama100;

    protected static string $resource = KomponenPenilaianResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function beforeSave(): void
    {
        $this->validateBobotKomponenSama100($this->form->getState());
    }

    /**
     * Asesmen berlaku untuk semua kelas pada mata kuliah + semester yang
     * sama; sekali edit di sini diterapkan juga ke komponen berkode sama
     * pada kelas lain.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var KomponenPenilaian $record */
        $kodeLama = $record->kode;
        $payload = collect($data)->only(['kode', 'evaluasi_id', 'nama', 'bobot'])->all();

        $record->update($payload);

        $massal = KomponenPenilaianMassalService::resolveUntukRecord($record);

        if ($massal !== null) {
            KomponenPenilaianMassalService::perbaruiSemuaKelas($record, $kodeLama, $payload, $massal);
        }

        return $record;
    }
}
