<?php

namespace App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource\Pages\Concerns;

use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Rules\BobotKomponenSama100Rule;
use App\Modules\Penilaian\Services\KomponenPenilaianMassalService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

trait ValidatesBobotKomponenSama100
{
    protected function validateBobotKomponenSama100(array $data): void
    {
        $record = $this->getRecord();
        $record = $record instanceof KomponenPenilaian ? $record : null;

        $kelasMkIds = static::resolveKelasMkIdsUntukValidasiBobot($data, $record);

        if ($kelasMkIds === null) {
            return;
        }

        // Kecualikan berdasar kode TERSIMPAN (sebelum diedit), bukan kode
        // baru yang mungkin sedang diganti — agar baris lama tidak ikut
        // terhitung ganda bersama nilai bobot yang baru diisi.
        $kode = $record?->kode ?? ($data['kode'] ?? null);

        $validator = Validator::make(
            ['bobot' => $data['bobot'] ?? 0],
            [
                'bobot' => [
                    'required',
                    'numeric',
                    new BobotKomponenSama100Rule($kelasMkIds, $kode),
                ],
            ],
        );

        if ($validator->fails()) {
            $statePath = $this->form->getStatePath();

            throw ValidationException::withMessages([
                filled($statePath) ? "{$statePath}.bobot" : 'bobot' => $validator->errors()->get('bobot'),
            ]);
        }
    }

    /**
     * Bobot dihitung terhadap seluruh kelas pada mata kuliah + semester yang
     * sama (mode massal); bila tidak tersedia, jatuh kembali ke satu kelas
     * yang dipilih (mode lama).
     *
     * @param  array<string, mixed>  $data
     * @return list<string>|null
     */
    protected static function resolveKelasMkIdsUntukValidasiBobot(array $data, ?KomponenPenilaian $record): ?array
    {
        $massal = $record instanceof KomponenPenilaian
            ? KomponenPenilaianMassalService::resolveUntukRecord($record)
            : KomponenPenilaianMassalService::resolveUntukPembuatan();

        if ($massal !== null) {
            return $massal['kelas']->pluck('id')->all();
        }

        $kelasMkId = $data['kelas_mk_id'] ?? $record?->kelas_mk_id;

        return $kelasMkId !== null ? [$kelasMkId] : null;
    }
}
