<?php

namespace App\Modules\Penilaian\Observers;

use App\Modules\Kalkulasi\Jobs\RecalkulasiCplJob;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use App\Modules\Penilaian\Services\ValidasiNilaiService;

class NilaiMahasiswaObserver
{
    public function saved(NilaiMahasiswa $nilai): void
    {
        $nilai->loadMissing('kelasMkMahasiswa.kelasMk.mkUnit');

        $kelasMk = $nilai->kelasMkMahasiswa?->kelasMk;

        if ($kelasMk !== null && ($nilai->wasRecentlyCreated || $nilai->wasChanged('nilai'))) {
            app(ValidasiNilaiService::class)->gugurkan($kelasMk);
        }

        $academicUnitId = $kelasMk?->mkUnit?->academic_unit_id;
        $semesterId = $kelasMk?->semester_id;

        if ($kelasMk === null || $academicUnitId === null || $semesterId === null) {
            return;
        }

        RecalkulasiCplJob::dispatch($kelasMk->id, $academicUnitId, $semesterId)
            ->onQueue('cpl-calculation')
            ->afterCommit();
    }

    public function deleted(NilaiMahasiswa $nilai): void
    {
        $nilai->loadMissing('kelasMkMahasiswa.kelasMk');

        $kelasMk = $nilai->kelasMkMahasiswa?->kelasMk;

        if ($kelasMk !== null) {
            app(ValidasiNilaiService::class)->gugurkan($kelasMk);
        }
    }
}
