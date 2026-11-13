<?php

use App\Models\User;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Models\AcademicUnitUser;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
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

if (! function_exists('siapkanDuaProdi')) {
    /**
     * Dua prodi di bawah fakultas yang sama; yang kedua dibuat bila seeder
     * hanya menyediakan satu.
     *
     * @return array{0: AcademicUnit, 1: AcademicUnit}
     */
    function siapkanDuaProdi(): array
    {
        $prodi = AcademicUnit::query()->where('type', 'study_program')->orderBy('id')->get();

        if ($prodi->count() < 2) {
            $fakultas = AcademicUnit::query()->where('type', 'faculty')->firstOrFail();
            $prodi->push(AcademicUnit::factory()->studyProgram($fakultas)->create(['status' => 'aktif']));
        }

        return [$prodi[0], $prodi[1]];
    }
}

if (! function_exists('siapkanKelasValidasi')) {
    /**
     * Kelas dengan penugasan selesai dan nilai terisi penuh (80 dan 60).
     * `mk_pemilik` (AcademicUnit) mengisi mk.academic_unit_id; atribut lain
     * (mis. koordinator_mk_id) masuk ke kelas_mk.
     *
     * @param  array<string, mixed>  $atribut
     * @return array{kelas: KelasMk, kmm: list<KelasMkMahasiswa>, komponen: KomponenPenilaian, pivot: SubcpmkKomponenPenilaian}
     */
    function siapkanKelasValidasi(User $dosen, ?AcademicUnit $prodi = null, array $atribut = []): array
    {
        $prodi ??= AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
        $pemilik = $atribut['mk_pemilik'] ?? $prodi;
        unset($atribut['mk_pemilik']);

        $semester = Semester::query()->where('status_aktif', true)->firstOrFail();
        $mk = Mk::factory()->create(['academic_unit_id' => $pemilik->id]);
        $mkUnit = MkUnit::factory()->forMk($mk)->forAcademicUnit($prodi)->create();

        $kelas = KelasMk::query()->create([
            'mk_unit_id' => $mkUnit->id,
            'semester_id' => $semester->id,
            'kode_kelas' => 'A',
            'dosen_pengampu_id' => $dosen->id,
            ...$atribut,
        ]);

        $kmm = [];
        foreach ([80, 60] as $nilai) {
            $mahasiswa = Mahasiswa::factory()->create(['academic_unit_id' => $prodi->id]);
            $kmm[] = KelasMkMahasiswa::query()->create([
                'kelas_mk_id' => $kelas->id,
                'mahasiswa_id' => $mahasiswa->id,
            ]);
        }

        $cpmk = Cpmk::factory()->forMk($mk)->untukSemester($semester->id)->create();
        $cpl = Cpl::factory()->forAcademicUnit($prodi)->create();
        $bok = Bok::factory()->forAcademicUnit($prodi)->create();
        $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id, 'bobot' => 100]);
        $cplMk = CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $mk->id, 'bobot' => 100]);
        $mkCpmk = MkCpmk::factory()->forCplMkAndCpmk($cplMk, $cpmk)->create();
        $subcpmk = Subcpmk::factory()->for($mkCpmk)->untukSemester($semester->id)->create();

        $komponen = komponenUntukSemester($mk->id, $semester->id, [
            'evaluasi_id' => Evaluasi::query()->where('kode', 'quiz')->firstOrFail()->id,
            'kode' => 'Asesmen01',
            'nama' => 'Kuis',
            'bobot' => 100,
        ]);
        $pivot = SubcpmkKomponenPenilaian::query()->create([
            'semester_id' => semesterAsesmen($komponen->id),
            'subcpmk_id' => $subcpmk->id,
            'komponen_penilaian_id' => $komponen->id,
            'bobot' => 100,
        ]);

        foreach ($kmm as $i => $baris) {
            NilaiMahasiswa::query()->create([
                'subcpmk_komponenpenilaian_id' => $pivot->id,
                'kelas_mk_mahasiswa_id' => $baris->id,
                'nilai' => [80, 60][$i],
            ]);
        }

        return compact('kelas', 'kmm', 'komponen', 'pivot');
    }
}

if (! function_exists('siapkanKelasDuaSubcpmk')) {
    /**
     * Kelas validasi yang asesmennya dibebankan ke dua Sub-CPMK: pivot pertama
     * (kode SUB-A, bobot 100) sudah bernilai 80 dan 60 bagi kedua peserta;
     * pivot kedua (kode SUB-B, bobot 300) belum bernilai.
     *
     * @return array{kelas: KelasMk, kmm: list<KelasMkMahasiswa>, komponen: KomponenPenilaian, pivot: SubcpmkKomponenPenilaian, pivot2: SubcpmkKomponenPenilaian}
     */
    function siapkanKelasDuaSubcpmk(User $dosen, ?AcademicUnit $prodi = null): array
    {
        $data = siapkanKelasValidasi($dosen, $prodi);
        $pivot = $data['pivot'];

        $pivot->subcpmk()->update(['kode' => 'SUB-A']);
        $subcpmk2 = Subcpmk::factory()
            ->for($pivot->subcpmk->mkCpmk)
            ->untukSemester((string) $pivot->semester_id)
            ->create(['kode' => 'SUB-B']);

        $data['pivot2'] = SubcpmkKomponenPenilaian::query()->create([
            'semester_id' => $pivot->semester_id,
            'subcpmk_id' => $subcpmk2->id,
            'komponen_penilaian_id' => $data['komponen']->id,
            'bobot' => 300,
        ]);

        return $data;
    }
}

if (! function_exists('siapkanKelasTigaSubcpmk')) {
    /**
     * Kelas dua-Sub-CPMK ditambah SUB-C (bobot pivot 200). Bawaan: SUB-C
     * ikut asesmen yang sama (tiga pivot). Dengan `$asesmenTerpisah`, SUB-C
     * dipindah ke asesmen tunggal kedua (Asesmen02, bobot 40; Asesmen01 60).
     * `pivot3` bernilai null pada kedua peserta sampai tes mengisinya.
     *
     * @return array{kelas: KelasMk, kmm: list<KelasMkMahasiswa>, komponen: KomponenPenilaian, pivot: SubcpmkKomponenPenilaian, pivot2: SubcpmkKomponenPenilaian, pivot3: SubcpmkKomponenPenilaian, komponen2: KomponenPenilaian|null}
     */
    function siapkanKelasTigaSubcpmk(User $dosen, ?AcademicUnit $prodi = null, bool $asesmenTerpisah = false): array
    {
        $data = siapkanKelasDuaSubcpmk($dosen, $prodi);
        $pivot = $data['pivot'];
        $komponen2 = null;
        $komponenPivot3 = $data['komponen'];

        if ($asesmenTerpisah) {
            $mkId = $data['komponen']->mk_id;
            komponenUntukSemester($mkId, semesterAsesmen($data['komponen']->id), ['kode' => 'Asesmen01', 'bobot' => 60]);
            $komponen2 = komponenUntukSemester($mkId, semesterAsesmen($data['komponen']->id), [
                'kode' => 'Asesmen02',
                'nama' => 'Tugas',
                'bobot' => 40,
            ]);
            $komponenPivot3 = $komponen2;
        }

        $subcpmk3 = Subcpmk::factory()
            ->for($pivot->subcpmk->mkCpmk)
            ->untukSemester((string) $pivot->semester_id)
            ->create(['kode' => 'SUB-C']);

        $data['pivot3'] = SubcpmkKomponenPenilaian::query()->create([
            'semester_id' => $pivot->semester_id,
            'subcpmk_id' => $subcpmk3->id,
            'komponen_penilaian_id' => $komponenPivot3->id,
            'bobot' => 200,
        ]);
        $data['komponen2'] = $komponen2;

        return $data;
    }
}

if (! function_exists('jadikanValidator')) {
    /** Menugaskan user pada unit dengan penanda validator nilai (peran ikut otomatis). */
    function jadikanValidator(User $user, AcademicUnit $unit): void
    {
        AcademicUnitUser::query()->create([
            'academic_unit_id' => $unit->id,
            'user_id' => $user->id,
            'status_validator_nilai' => true,
        ]);
    }
}
