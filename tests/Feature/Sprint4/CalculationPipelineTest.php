<?php

use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalkulasi\Jobs\RecalkulasiCplJob;
use App\Modules\Kalkulasi\Models\HasilCplMk;
use App\Modules\Kalkulasi\Models\HasilCplUnit;
use App\Modules\Kalkulasi\Models\HasilCpmk;
use App\Modules\Kalkulasi\Models\HasilSubcpmk;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\SetsUpKalkulasiFixtures;

uses(RefreshDatabase::class, SetsUpKalkulasiFixtures::class);

beforeEach(function () {
    $this->seedKalkulasiBase();
});

it('dispatches recalkulasi when nilai saved', function () {
    Queue::fake();

    $dasar = $this->createKelasPenilaianDasar();
    $skp = $this->buatKomponenSkp(
        $dasar['kelas'],
        $dasar['subcpmk'],
        $dasar['evaluasi'],
        'UTS',
        100,
        100,
    );

    NilaiMahasiswa::query()->updateOrCreate(
        [
            'subcpmk_komponenpenilaian_id' => $skp->id,
            'kelas_mk_mahasiswa_id' => $dasar['kmm']->id,
        ],
        ['nilai' => 88],
    );

    Queue::assertPushedOn(
        'cpl-calculation',
        RecalkulasiCplJob::class,
        function (RecalkulasiCplJob $job) use ($dasar): bool {
            return $job->kelasMkId === $dasar['kelas']->id
                && $job->academicUnitId === $dasar['mkUnit']->academic_unit_id
                && $job->semesterId === $dasar['semester']->id;
        },
    );
});

it('runs full 5 stage pipeline when invoked synchronously', function () {
    $dasar = $this->createKelasPenilaianDasar();
    $prodi = AcademicUnit::query()->findOrFail($dasar['mk']->academic_unit_id);

    $this->buatKurikulumAktif($prodi);

    NilaiMahasiswa::withoutEvents(function () use ($dasar): void {
        $skp = $this->buatKomponenSkp(
            $dasar['kelas'],
            $dasar['subcpmk'],
            $dasar['evaluasi'],
            'UTS',
            100,
            100,
        );

        $this->isiNilai($skp, $dasar['kmm'], 85);
    });

    RecalkulasiCplJob::dispatchSync(
        $dasar['kelas']->id,
        $dasar['mkUnit']->academic_unit_id,
        $dasar['semester']->id,
    );

    expect(HasilSubcpmk::query()->count())->toBeGreaterThan(0)
        ->and(HasilCpmk::query()->count())->toBeGreaterThan(0)
        ->and(HasilCplMk::query()->count())->toBeGreaterThan(0)
        ->and(HasilCplUnit::query()->count())->toBeGreaterThan(0);

    $hasilCplMk = HasilCplMk::query()
        ->where('cpl_id', $dasar['cpl']->id)
        ->where('kelas_mk_mahasiswa_id', $dasar['kmm']->id)
        ->first();

    expect($hasilCplMk)->not->toBeNull()
        ->and((float) $hasilCplMk->nilai_akhir)->toBe(85.0);

    $hasilCplUnit = HasilCplUnit::query()
        ->where('cpl_id', $dasar['cpl']->id)
        ->where('academic_unit_id', $prodi->id)
        ->where('semester_id', $dasar['semester']->id)
        ->first();

    expect($hasilCplUnit)->not->toBeNull()
        ->and((float) $hasilCplUnit->rata_rata)->toBe(85.0);
});
