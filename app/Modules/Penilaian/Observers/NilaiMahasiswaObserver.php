<?php

namespace App\Modules\Penilaian\Observers;

use App\Modules\Kalkulasi\Jobs\RecalkulasiCplJob;
use App\Modules\Penilaian\Models\NilaiMahasiswa;

class NilaiMahasiswaObserver
{
    public function saved(NilaiMahasiswa $nilai): void
    {
        $nilai->loadMissing('kelasMkMahasiswa.kelasMk.mkUnit');

        $kelasMk = $nilai->kelasMkMahasiswa?->kelasMk;
        $academicUnitId = $kelasMk?->mkUnit?->academic_unit_id;
        $semesterId = $kelasMk?->semester_id;

        if ($kelasMk === null || $academicUnitId === null || $semesterId === null) {
            return;
        }

        RecalkulasiCplJob::dispatch($kelasMk->id, $academicUnitId, $semesterId)
            ->onQueue('cpl-calculation')
            ->afterCommit();
    }
}
