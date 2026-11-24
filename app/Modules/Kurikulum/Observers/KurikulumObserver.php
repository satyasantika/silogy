<?php

namespace App\Modules\Kurikulum\Observers;

use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\SinkronValidatorKurikulum;
use App\Modules\Penilaian\Support\ValidatorNilaiScope;

/**
 * Peran Validator Nilai diturunkan dari hak per kurikulum, jadi mematikan
 * switch atau memindahkan kurikulum ke prodi lain harus menyinkronkan ulang
 * peran orang-orang yang terdampak, termasuk penghuni prodi lama.
 */
class KurikulumObserver
{
    public function __construct(private readonly SinkronValidatorKurikulum $sinkron) {}

    public function saved(Kurikulum $kurikulum): void
    {
        $unitBerubah = $kurikulum->wasChanged('academic_unit_id');

        if (! $unitBerubah && ! $kurikulum->wasChanged('validasi_nilai_aktif')) {
            return;
        }

        $sebelumnya = [];

        if ($unitBerubah && ! $kurikulum->wasRecentlyCreated) {
            $lama = $kurikulum->replicate();
            $lama->academic_unit_id = $kurikulum->getOriginal('academic_unit_id');
            $sebelumnya = ValidatorNilaiScope::otomatisBerhakUserIds($lama)->all();
        }

        $this->sinkron->sinkronkan($kurikulum, $sebelumnya);
    }
}
