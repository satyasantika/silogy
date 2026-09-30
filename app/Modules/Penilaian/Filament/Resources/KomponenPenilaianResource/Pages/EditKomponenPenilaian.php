<?php

namespace App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource\Pages;

use App\Modules\Kalender\Support\SemesterTerpilih;
use App\Modules\MK\Support\MkTerpilih;
use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Models\KomponenPenilaianSemester;
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
     * Isi form untuk bobot dan semester dibaca dari pivot semester terpilih,
     * bukan dari baris asesmen — asesmen yang sama bisa berlaku di beberapa
     * semester dengan bobot berbeda.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $semesterId = $this->semesterKonteks();

        /** @var KomponenPenilaian $record */
        $record = $this->getRecord();

        $data['semester_id'] = $semesterId;
        $data['bobot'] = $semesterId === null ? null : $record->bobotUntukSemester($semesterId);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var KomponenPenilaian $record */
        $record->update(collect($data)->only(['kode', 'evaluasi_id', 'nama'])->all());

        $semesterId = filled($data['semester_id'] ?? null)
            ? (string) $data['semester_id']
            : $this->semesterKonteks();

        if ($semesterId !== null && array_key_exists('bobot', $data)) {
            KomponenPenilaianSemester::query()->updateOrCreate(
                [
                    'komponen_penilaian_id' => $record->id,
                    'semester_id' => $semesterId,
                ],
                ['bobot' => (float) $data['bobot']],
            );
        }

        return $record;
    }

    private function semesterKonteks(): ?string
    {
        $semesterId = SemesterTerpilih::currentId(MkTerpilih::currentId())
            ?? SemesterTerpilih::defaultId();

        return filled($semesterId) ? (string) $semesterId : null;
    }

    protected function afterSave(): void
    {
        $this->dispatch('komponen-penilaian-bobot-diperbarui');
    }
}
