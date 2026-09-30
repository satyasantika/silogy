<?php

namespace App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource\Pages;

use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Models\KomponenPenilaianSemester;
use App\Modules\Penilaian\Policies\KomponenPenilaianPolicy;
use App\Support\Filament\Pages\BaseCreateRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

class CreateKomponenPenilaian extends BaseCreateRecord
{
    protected static string $resource = KomponenPenilaianResource::class;

    protected function beforeCreate(): void
    {
        $data = $this->form->getState();
        $user = auth()->user();

        if (! $user || ! app(KomponenPenilaianPolicy::class)->create($user)) {
            throw new AuthorizationException;
        }

        $mkId = $data['mk_id'] ?? null;

        if (blank($mkId)) {
            throw new AuthorizationException;
        }

        $stub = new KomponenPenilaian(['mk_id' => $mkId]);

        if (! app(KomponenPenilaianPolicy::class)->update($user, $stub)) {
            throw new AuthorizationException;
        }
    }

    /**
     * semester_id dan bobot bukan kolom komponen_penilaian lagi: keduanya
     * milik pasangan (asesmen, semester) dan ditulis ke pivot, supaya satu
     * asesmen bisa dipakai ulang di semester lain dengan bobot berbeda.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $semesterId = (string) ($data['semester_id'] ?? '');
        $bobot = (float) ($data['bobot'] ?? 100);

        unset($data['semester_id'], $data['bobot']);

        /** @var KomponenPenilaian $record */
        $record = static::getModel()::create($data);

        if ($semesterId !== '') {
            KomponenPenilaianSemester::query()->updateOrCreate(
                [
                    'komponen_penilaian_id' => $record->id,
                    'semester_id' => $semesterId,
                ],
                ['bobot' => $bobot],
            );
        }

        return $record;
    }
}
