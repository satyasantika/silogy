<?php

namespace App\Modules\Kurikulum\Filament\Support\Concerns;

use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use Illuminate\Validation\ValidationException;

/**
 * Saat create CPL/BoK/MK/MkUnit, isi kurikulum_id + academic_unit_id
 * dari kurikulum terpilih di session.
 */
trait AssignsKurikulumTerpilihOnCreate
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $kurikulum = KurikulumTerpilih::current();

        if ($kurikulum === null) {
            throw ValidationException::withMessages([
                'data.kurikulum_id' => 'Pilih kurikulum terpilih terlebih dahulu sebelum menambah data.',
            ]);
        }

        $data['kurikulum_id'] = $kurikulum->id;
        $data['academic_unit_id'] = $kurikulum->academic_unit_id;

        return $data;
    }
}
