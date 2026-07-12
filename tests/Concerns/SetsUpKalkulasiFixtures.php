<?php

namespace Tests\Concerns;

use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kalkulasi\Services\CpmkCalculator;
use App\Modules\Kalkulasi\Services\SubcpmkCalculator;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\States\DraftState;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkCpmk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\Penilaian\Models\Evaluasi;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\SemesterSeeder;

trait SetsUpKalkulasiFixtures
{
    protected function seedKalkulasiBase(): void
    {
        $this->seed(AcademicUnitSeeder::class);
        $this->seed(SemesterSeeder::class);
        $this->seed(EvaluasiSeeder::class);
    }

    /**
     * @return array{
     *     kelas: KelasMk,
     *     kmm: KelasMkMahasiswa,
     *     mk: Mk,
     *     cpmk: Cpmk,
     *     subcpmk: Subcpmk,
     *     evaluasi: Evaluasi,
     *     cpl: Cpl,
     *     cplBok: CplBok,
     *     cplMk: CplMk,
     *     mkUnit: MkUnit,
     *     semester: Semester
     * }
     */
    protected function createKelasPenilaianDasar(string $kodeMkUnit = 'IF-KALK'): array
    {
        $prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
        $semester = Semester::query()->where('status_aktif', true)->firstOrFail();
        $mk = Mk::factory()->create(['academic_unit_id' => $prodi->id]);
        $mkUnit = MkUnit::factory()->forMk($mk)->forAcademicUnit($prodi)->create(['kode' => $kodeMkUnit]);

        $kelas = KelasMk::query()->create([
            'mk_unit_id' => $mkUnit->id,
            'semester_id' => $semester->id,
            'kode_kelas' => 'A',
        ]);

        $mahasiswa = Mahasiswa::factory()->create(['academic_unit_id' => $prodi->id]);
        $kmm = KelasMkMahasiswa::query()->create([
            'kelas_mk_id' => $kelas->id,
            'mahasiswa_id' => $mahasiswa->id,
        ]);

        $cpmk = Cpmk::factory()->forMk($mk)->create();
        $cpl = Cpl::factory()->forAcademicUnit($prodi)->create();
        $bok = Bok::factory()->forAcademicUnit($prodi)->create();
        $cplBok = CplBok::query()->create([
            'cpl_id' => $cpl->id,
            'bok_id' => $bok->id,
            'bobot' => 100,
        ]);
        $cplMk = CplMk::query()->create([
            'cpl_bok_id' => $cplBok->id,
            'mk_id' => $mk->id,
            'bobot' => 100,
        ]);
        $mkCpmk = MkCpmk::factory()->forCplMkAndCpmk($cplMk, $cpmk)->create();
        $subcpmk = Subcpmk::factory()->for($mkCpmk)->create(['kode' => 'SUB-01']);

        $evaluasi = Evaluasi::query()->where('kode', 'uts')->firstOrFail();

        return compact(
            'kelas',
            'kmm',
            'mk',
            'mkUnit',
            'cpmk',
            'subcpmk',
            'evaluasi',
            'cpl',
            'cplBok',
            'cplMk',
            'semester',
        );
    }

    protected function buatKurikulumAktif(AcademicUnit $unit, int $targetCapaian = 75): Kurikulum
    {
        return Kurikulum::query()->create([
            'academic_unit_id' => $unit->id,
            'nama' => 'Kurikulum '.$unit->code,
            'tahun' => 2025,
            'target_capaian_lulusan' => $targetCapaian,
            'state' => DraftState::class,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $dasar
     */
    protected function jalankanKalkulasiHinggaCpmk(array $dasar, float $nilai = 85): void
    {
        $skp = $this->buatKomponenSkp(
            $dasar['kelas'],
            $dasar['subcpmk'],
            $dasar['evaluasi'],
            'UTS',
            100,
            100,
        );

        $this->isiNilai($skp, $dasar['kmm'], $nilai);

        app(SubcpmkCalculator::class)->calculate($dasar['kelas']->id);
        app(CpmkCalculator::class)->calculate($dasar['kelas']->id);
    }

    protected function buatKomponenSkp(
        KelasMk $kelas,
        Subcpmk $subcpmk,
        Evaluasi $evaluasi,
        string $kode,
        float $bobotKomponen,
        float $bobotSubcpmk,
    ): SubcpmkKomponenPenilaian {
        $kelas->loadMissing('mkUnit');

        $komponen = KomponenPenilaian::query()->create([
            'mk_id' => $kelas->mkUnit?->mk_id,
            'semester_id' => $kelas->semester_id,
            'evaluasi_id' => $evaluasi->id,
            'kode' => $kode,
            'nama' => $kode,
            'bobot' => $bobotKomponen,
        ]);

        return SubcpmkKomponenPenilaian::query()->create([
            'subcpmk_id' => $subcpmk->id,
            'komponen_penilaian_id' => $komponen->id,
            'bobot' => $bobotSubcpmk,
        ]);
    }

    protected function isiNilai(
        SubcpmkKomponenPenilaian $skp,
        KelasMkMahasiswa $kmm,
        float $nilai,
    ): NilaiMahasiswa {
        return NilaiMahasiswa::query()->updateOrCreate(
            [
                'subcpmk_komponenpenilaian_id' => $skp->id,
                'kelas_mk_mahasiswa_id' => $kmm->id,
            ],
            ['nilai' => $nilai],
        );
    }

    protected function buatSubcpmkKedua(Subcpmk $subcpmkPertama, string $kode = 'SUB-02'): Subcpmk
    {
        $mkCpmk = MkCpmk::query()->findOrFail($subcpmkPertama->mk_cpmk_id);

        return Subcpmk::factory()->for($mkCpmk)->create(['kode' => $kode]);
    }
}
