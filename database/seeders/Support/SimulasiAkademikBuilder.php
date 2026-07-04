<?php

namespace Database\Seeders\Support;

use App\Models\User;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\CPL\Models\CplProfilLulusan;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kalkulasi\Jobs\RecalkulasiCplJob;
use App\Modules\Kalkulasi\Models\HasilCplUnit;
use App\Modules\Kalkulasi\Services\CplMkUnitCalculator;
use App\Modules\Kalkulasi\Services\CplUnitAggregator;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Models\ProfilIndikator;
use App\Modules\Kurikulum\Models\ProfilLulusan;
use App\Modules\Kurikulum\States\AktifState;
use App\Modules\Kurikulum\States\BokState;
use App\Modules\Kurikulum\States\CplState;
use App\Modules\Kurikulum\States\MkState;
use App\Modules\Kurikulum\States\ProfilLulusanState;
use App\Modules\Kurikulum\States\SetdosenmkState;
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
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SimulasiAkademikBuilder
{
    /** @var array<string, string> */
    protected array $mkProdi = [
        'MAT101' => 'Kalkulus I',
        'MAT102' => 'Aljabar Linear',
        'MAT103' => 'Geometri Analitik',
        'MAT104' => 'Statistika Matematika',
        'MAT105' => 'Matematika Diskrit',
        'MAT106' => 'Analisis Real',
    ];

    public function __construct(
        protected Semester $semester,
        protected User $timkur,
        protected User $korma,
        protected User $dosenProdi,
    ) {}

    public function seedProdi(AcademicUnit $prodi): void
    {
        if ($this->sudahAdaHasilCpl($prodi)) {
            return;
        }

        $kurikulum = $this->buatKurikulumAktif($prodi);
        $cplIds = $this->buatCplDanProfil($prodi, $kurikulum);
        $cplBokMap = $this->buatBokDanPivot($prodi, $cplIds);
        $kelasCollection = collect();

        foreach ($this->mkProdi as $kode => $nama) {
            $kelas = $this->buatRantaiPenilaianProdi(
                unit: $prodi,
                cplBokMap: $cplBokMap,
                kodeMk: $kode,
                namaMk: $nama,
                dosen: $this->dosenProdi,
                koordinator: $this->korma,
                semesterKe: (int) ((array_search($kode, array_keys($this->mkProdi), true) % 4) + 1),
            );

            if ($kelas !== null) {
                $kelasCollection->push($kelas);
            }
        }

        $this->jalankanKalkulasi($kelasCollection, $prodi);
        $this->agregasiInduk($prodi);
    }

    /**
     * @param  array{unit: AcademicUnit, kode: string, nama: string, dosen: User, koordinator: User}  $definisi
     */
    public function seedMkUnitRingkas(array $definisi): void
    {
        $unit = $definisi['unit'];
        $dosen = $definisi['dosen'];
        $koordinator = $definisi['koordinator'];

        if (KelasMk::query()
            ->where('semester_id', $this->semester->id)
            ->whereHas('mkUnit', fn ($q) => $q
                ->where('academic_unit_id', $unit->id)
                ->where('kode', $definisi['kode']))
            ->exists()) {
            return;
        }

        $prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
        $mahasiswaSample = Mahasiswa::query()
            ->where('academic_unit_id', $prodi->id)
            ->limit(10)
            ->get();

        if ($mahasiswaSample->isEmpty()) {
            return;
        }

        $suffixUnit = $this->kodeSingkatUnit($unit->type);

        $cpl = Cpl::query()->firstOrCreate(
            ['academic_unit_id' => $unit->id, 'kode' => 'CPL-SIM-'.$suffixUnit],
            [
                'id' => (string) Str::uuid(),
                'deskripsi' => 'CPL simulasi '.$unit->nama,
                'domain' => 'kognitif',
            ],
        );

        $bok = Bok::query()->firstOrCreate(
            ['academic_unit_id' => $unit->id, 'kode' => 'BOK-SIM-'.$suffixUnit],
            [
                'id' => (string) Str::uuid(),
                'nama' => 'BoK simulasi '.$unit->nama,
                'deskripsi' => 'BoK sementara untuk demo',
            ],
        );

        $cplBok = CplBok::query()->firstOrCreate(
            ['cpl_id' => $cpl->id, 'bok_id' => $bok->id],
            ['id' => (string) Str::uuid(), 'bobot' => 100],
        );

        $mk = Mk::query()->firstOrCreate(
            ['academic_unit_id' => $unit->id, 'nama' => $definisi['nama']],
            [
                'id' => (string) Str::uuid(),
                'state' => 'penilaian',
                'sks_teori' => 2,
                'sks_praktik' => 1,
                'sks_lapangan' => 0,
                'sks' => 3,
                'jenis' => 'wajib',
                'is_active' => true,
            ],
        );

        CplMk::query()->firstOrCreate(
            ['cpl_bok_id' => $cplBok->id, 'mk_id' => $mk->id],
            ['id' => (string) Str::uuid(), 'bobot' => 100],
        );

        $mkUnit = MkUnit::query()->firstOrCreate(
            ['mk_id' => $mk->id, 'academic_unit_id' => $unit->id, 'kode' => $definisi['kode']],
            [
                'id' => (string) Str::uuid(),
                'semester_ke' => 1,
                'is_active' => true,
            ],
        );

        $kelas = KelasMk::query()->firstOrCreate(
            [
                'mk_unit_id' => $mkUnit->id,
                'semester_id' => $this->semester->id,
                'kode_kelas' => 'SIM',
            ],
            [
                'id' => (string) Str::uuid(),
                'dosen_pengampu_id' => $dosen->id,
                'koordinator_mk_id' => $koordinator->id,
                'kapasitas' => 40,
            ],
        );

        $cpmk = Cpmk::query()->firstOrCreate(
            ['mk_id' => $mk->id, 'kode' => 'CPMK-'.$definisi['kode']],
            [
                'id' => (string) Str::uuid(),
                'deskripsi' => 'CPMK simulasi '.$definisi['nama'],
            ],
        );

        $cplMk = CplMk::query()->where('mk_id', $mk->id)->firstOrFail();

        $mkCpmk = MkCpmk::query()->firstOrCreate(
            ['cpl_mk_id' => $cplMk->id, 'cpmk_id' => $cpmk->id],
            ['id' => (string) Str::uuid(), 'bobot' => 100],
        );

        $subcpmk = Subcpmk::query()->firstOrCreate(
            ['mk_cpmk_id' => $mkCpmk->id, 'semester_id' => $this->semester->id, 'kode' => 'SUB-'.$definisi['kode']],
            [
                'id' => (string) Str::uuid(),
                'deskripsi' => 'Sub-CPMK simulasi',
                'bobot' => 100,
            ],
        );

        $uts = Evaluasi::query()->where('kode', 'uts')->firstOrFail();
        $uas = Evaluasi::query()->where('kode', 'uas')->firstOrFail();

        $komponenUts = $this->buatKomponen($kelas, $uts, 'UTS', 50);
        $komponenUas = $this->buatKomponen($kelas, $uas, 'UAS', 50);

        $skpIds = [];
        foreach ([$komponenUts, $komponenUas] as $komponen) {
            $skp = SubcpmkKomponenPenilaian::query()->firstOrCreate(
                ['subcpmk_id' => $subcpmk->id, 'komponen_penilaian_id' => $komponen->id],
                ['id' => (string) Str::uuid(), 'bobot' => 100],
            );
            $skpIds[] = $skp->id;
        }

        foreach ($mahasiswaSample as $mhs) {
            $kmm = KelasMkMahasiswa::query()->firstOrCreate(
                ['kelas_mk_id' => $kelas->id, 'mahasiswa_id' => $mhs->id],
                ['id' => (string) Str::uuid()],
            );

            $this->isiNilaiAcak($kmm, $skpIds);
        }

        $this->jalankanKalkulasi(collect([$kelas]), $unit);
    }

    protected function sudahAdaHasilCpl(AcademicUnit $unit): bool
    {
        return HasilCplUnit::query()
            ->where('academic_unit_id', $unit->id)
            ->where('semester_id', $this->semester->id)
            ->exists();
    }

    protected function buatKurikulumAktif(AcademicUnit $prodi): Kurikulum
    {
        $kode = 'SIM-'.($prodi->code ?? 'PRODI').'-2025';

        $kurikulum = Kurikulum::query()->firstOrCreate(
            ['academic_unit_id' => $prodi->id, 'kode' => $kode],
            [
                'id' => (string) Str::uuid(),
                'nama' => 'Kurikulum Simulasi 2025',
                'tahun' => 2025,
                'target_capaian_lulusan' => 75,
                'deskripsi' => 'Data sementara untuk demo end-to-end SILOGY',
                'is_active' => false,
                'dibuat_oleh' => $this->timkur->id,
            ],
        );

        if ($kurikulum->state->equals(AktifState::class) && $kurikulum->is_active) {
            return $kurikulum;
        }

        if ($kurikulum->profilLulusan()->doesntExist()) {
            $profil = ProfilLulusan::query()->create([
                'id' => (string) Str::uuid(),
                'kurikulum_id' => $kurikulum->id,
                'kode' => 'PL-SIM-01',
                'nama' => 'Pendidik Matematika Profesional',
                'deskripsi' => 'Profil lulusan simulasi prodi '.$prodi->nama,
                'urutan' => 1,
            ]);

            ProfilIndikator::query()->create([
                'id' => (string) Str::uuid(),
                'profil_id' => $profil->id,
                'nama' => 'Menguasai konsep matematika dan pedagogik',
                'deskripsi' => 'Indikator simulasi',
            ]);
        }

        $this->lanjutkanState($kurikulum, ProfilLulusanState::class);
        $this->lanjutkanState($kurikulum, CplState::class);
        $this->lanjutkanState($kurikulum, BokState::class);
        $this->lanjutkanState($kurikulum, MkState::class);
        $this->lanjutkanState($kurikulum, SetdosenmkState::class);
        $this->lanjutkanState($kurikulum, AktifState::class);

        $kurikulum->update(['is_active' => true]);

        return $kurikulum->fresh();
    }

    /**
     * @return list<string>
     */
    protected function buatCplDanProfil(AcademicUnit $prodi, Kurikulum $kurikulum): array
    {
        $profil = $kurikulum->profilLulusan()->firstOrFail();
        $cplIds = [];

        foreach ([
            ['kode' => 'CPL-SIM-01', 'deskripsi' => 'Mampu merancang pembelajaran matematika', 'domain' => 'kognitif'],
            ['kode' => 'CPL-SIM-02', 'deskripsi' => 'Berkomitmen pada etika pendidikan', 'domain' => 'afektif'],
        ] as $row) {
            $cpl = Cpl::query()->firstOrCreate(
                ['academic_unit_id' => $prodi->id, 'kode' => $row['kode']],
                ['id' => (string) Str::uuid(), ...$row],
            );

            CplProfilLulusan::query()->firstOrCreate(
                ['cpl_id' => $cpl->id, 'profil_lulusan_id' => $profil->id],
                ['id' => (string) Str::uuid()],
            );

            $cplIds[] = $cpl->id;
        }

        return $cplIds;
    }

    /**
     * @param  list<string>  $cplIds
     * @return array<string, string>
     */
    protected function buatBokDanPivot(AcademicUnit $prodi, array $cplIds): array
    {
        $map = [];
        $bokDefs = [
            ['kode' => 'BOK-SIM-01', 'nama' => 'Aljabar & Kalkulus'],
            ['kode' => 'BOK-SIM-02', 'nama' => 'Pedagogik & Evaluasi'],
            ['kode' => 'BOK-SIM-03', 'nama' => 'Statistika & Analisis'],
        ];

        foreach ($bokDefs as $index => $def) {
            $bok = Bok::query()->firstOrCreate(
                ['academic_unit_id' => $prodi->id, 'kode' => $def['kode']],
                ['id' => (string) Str::uuid(), 'nama' => $def['nama'], 'deskripsi' => 'BoK simulasi'],
            );

            $cplId = $cplIds[$index % count($cplIds)];
            $cplBok = CplBok::query()->firstOrCreate(
                ['cpl_id' => $cplId, 'bok_id' => $bok->id],
                ['id' => (string) Str::uuid(), 'bobot' => 100],
            );

            $map[$def['kode']] = $cplBok->id;
        }

        return $map;
    }

    /**
     * @param  array<string, string>  $cplBokMap
     */
    protected function buatRantaiPenilaianProdi(
        AcademicUnit $unit,
        array $cplBokMap,
        string $kodeMk,
        string $namaMk,
        User $dosen,
        User $koordinator,
        int $semesterKe,
    ): ?KelasMk {
        $cplBokId = array_values($cplBokMap)[crc32($kodeMk) % count($cplBokMap)];

        $mk = Mk::query()->firstOrCreate(
            ['academic_unit_id' => $unit->id, 'nama' => $namaMk],
            [
                'id' => (string) Str::uuid(),
                'state' => 'penilaian',
                'sks_teori' => 2,
                'sks_praktik' => 1,
                'sks_lapangan' => 0,
                'sks' => 3,
                'jenis' => 'wajib',
                'is_active' => true,
            ],
        );

        CplMk::query()->firstOrCreate(
            ['cpl_bok_id' => $cplBokId, 'mk_id' => $mk->id],
            ['id' => (string) Str::uuid(), 'bobot' => 100],
        );

        $mkUnit = MkUnit::query()->firstOrCreate(
            ['mk_id' => $mk->id, 'academic_unit_id' => $unit->id, 'kode' => $kodeMk],
            [
                'id' => (string) Str::uuid(),
                'semester_ke' => $semesterKe,
                'is_active' => true,
            ],
        );

        $kelas = KelasMk::query()->firstOrCreate(
            [
                'mk_unit_id' => $mkUnit->id,
                'semester_id' => $this->semester->id,
                'kode_kelas' => 'A',
            ],
            [
                'id' => (string) Str::uuid(),
                'dosen_pengampu_id' => $dosen->id,
                'koordinator_mk_id' => $koordinator->id,
                'kapasitas' => 40,
            ],
        );

        $cpmk = Cpmk::query()->firstOrCreate(
            ['mk_id' => $mk->id, 'kode' => 'CPMK-'.$kodeMk],
            [
                'id' => (string) Str::uuid(),
                'deskripsi' => 'CPMK simulasi '.$namaMk,
            ],
        );

        $cplMk = CplMk::query()->where('mk_id', $mk->id)->firstOrFail();

        $mkCpmk = MkCpmk::query()->firstOrCreate(
            ['cpl_mk_id' => $cplMk->id, 'cpmk_id' => $cpmk->id],
            ['id' => (string) Str::uuid(), 'bobot' => 100],
        );

        $subcpmkIds = [];
        foreach (['A', 'B'] as $suffix) {
            $sub = Subcpmk::query()->firstOrCreate(
                [
                    'mk_cpmk_id' => $mkCpmk->id,
                    'semester_id' => $this->semester->id,
                    'kode' => 'SUB-'.$kodeMk.'-'.$suffix,
                ],
                [
                    'id' => (string) Str::uuid(),
                    'deskripsi' => 'Sub-CPMK simulasi '.$suffix,
                    'bobot' => 50,
                ],
            );
            $subcpmkIds[] = $sub->id;
        }

        $uts = Evaluasi::query()->where('kode', 'uts')->firstOrFail();
        $uas = Evaluasi::query()->where('kode', 'uas')->firstOrFail();
        $quiz = Evaluasi::query()->where('kode', 'quiz')->firstOrFail();
        $tugas = Evaluasi::query()->where('kode', 'tugas')->firstOrFail();

        $komponenList = [
            $this->buatKomponen($kelas, $uts, 'UTS', 30),
            $this->buatKomponen($kelas, $uas, 'UAS', 40),
            $this->buatKomponen($kelas, $quiz, 'Quiz', 15),
            $this->buatKomponen($kelas, $tugas, 'Tugas', 15),
        ];

        $skpIds = [];
        foreach ($subcpmkIds as $subcpmkId) {
            foreach ($komponenList as $komponen) {
                $skp = SubcpmkKomponenPenilaian::query()->firstOrCreate(
                    ['subcpmk_id' => $subcpmkId, 'komponen_penilaian_id' => $komponen->id],
                    ['id' => (string) Str::uuid(), 'bobot' => 100],
                );
                $skpIds[] = $skp->id;
            }
        }

        $mahasiswa = Mahasiswa::query()
            ->where('academic_unit_id', $unit->id)
            ->get();

        NilaiMahasiswa::withoutEvents(function () use ($kelas, $mahasiswa, $skpIds): void {
            foreach ($mahasiswa as $mhs) {
                $kmm = KelasMkMahasiswa::query()->firstOrCreate(
                    ['kelas_mk_id' => $kelas->id, 'mahasiswa_id' => $mhs->id],
                    ['id' => (string) Str::uuid()],
                );

                foreach ($skpIds as $skpId) {
                    NilaiMahasiswa::query()->updateOrCreate(
                        [
                            'subcpmk_komponenpenilaian_id' => $skpId,
                            'kelas_mk_mahasiswa_id' => $kmm->id,
                        ],
                        [
                            'id' => (string) Str::uuid(),
                            'nilai' => fake()->randomFloat(2, 60, 95),
                        ],
                    );
                }
            }
        });

        return $kelas;
    }

    /**
     * @param  list<string>  $skpIds
     */
    protected function isiNilaiAcak(KelasMkMahasiswa $kmm, array $skpIds): void
    {
        NilaiMahasiswa::withoutEvents(function () use ($kmm, $skpIds): void {
            foreach ($skpIds as $skpId) {
                NilaiMahasiswa::query()->updateOrCreate(
                    [
                        'subcpmk_komponenpenilaian_id' => $skpId,
                        'kelas_mk_mahasiswa_id' => $kmm->id,
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'nilai' => fake()->randomFloat(2, 60, 95),
                    ],
                );
            }
        });
    }

    protected function buatKomponen(KelasMk $kelas, Evaluasi $evaluasi, string $nama, float $bobot): KomponenPenilaian
    {
        return KomponenPenilaian::query()->firstOrCreate(
            ['kelas_mk_id' => $kelas->id, 'kode' => $nama],
            [
                'id' => (string) Str::uuid(),
                'evaluasi_id' => $evaluasi->id,
                'nama' => $nama,
                'bobot' => $bobot,
            ],
        );
    }

    /**
     * @param  Collection<int, KelasMk>  $kelasCollection
     */
    protected function jalankanKalkulasi(Collection $kelasCollection, AcademicUnit $unit): void
    {
        foreach ($kelasCollection as $kelas) {
            RecalkulasiCplJob::dispatchSync(
                $kelas->id,
                $unit->id,
                $this->semester->id,
            );
        }
    }

    protected function agregasiInduk(AcademicUnit $prodi): void
    {
        $prodi->loadMissing('parent.parent.parent');
        $ancestor = $prodi->parent;

        while ($ancestor !== null) {
            app(CplMkUnitCalculator::class)->calculate($ancestor->id, $this->semester->id);
            app(CplUnitAggregator::class)->aggregate($ancestor->id, $this->semester->id);
            $ancestor = $ancestor->parent;
        }
    }

    /**
     * @param  class-string  $stateClass
     */
    protected function lanjutkanState(Kurikulum $kurikulum, string $stateClass): void
    {
        $kurikulum->refresh();

        if ($kurikulum->state->equals($stateClass)) {
            return;
        }

        if (! $kurikulum->state->canTransitionTo($stateClass)) {
            return;
        }

        $kurikulum->state->transitionTo($stateClass);
    }

    protected function kodeSingkatUnit(string $type): string
    {
        return match ($type) {
            'university' => 'UNIV',
            'faculty' => 'FAK',
            'department' => 'JUR',
            'study_program' => 'PRODI',
            default => strtoupper(substr($type, 0, 4)),
        };
    }
}
