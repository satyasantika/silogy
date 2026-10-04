<?php

namespace Database\Seeders\Support;

use App\Models\User;
use App\Modules\BoK\Models\Bok;
use App\Modules\BoK\Models\BokKodeOverride;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplKodeOverride;
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
use App\Modules\MK\Models\CpmkSemester;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkCpmk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\MK\Models\SubcpmkSemester;
use App\Modules\Penilaian\Models\Evaluasi;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Models\KomponenPenilaianSemester;
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

    /** Jumlah MK prodi yang tersedia sebagai bahan simulasi. */
    public const MAKS_MK = 6;

    /**
     * @param  int  $jumlahMk  banyaknya MK prodi yang dibangun (1..MAKS_MK), diambil
     *                         berurutan dari MAT101 supaya Kalkulus I selalu ada.
     */
    public function __construct(
        protected Semester $semester,
        protected User $timkur,
        protected User $korma,
        protected User $dosenProdi,
        int $jumlahMk = self::MAKS_MK,
    ) {
        $this->mkProdi = array_slice($this->mkProdi, 0, max(1, min($jumlahMk, self::MAKS_MK)), true);
    }

    /**
     * Contoh kosong: satu kurikulum aktif tanpa isi dan satu MK yang Koordinatornya
     * sudah ditetapkan. Profil lulusan, CPL, BoK, penawaran MK, CPMK, kelas, dan
     * nilai sengaja tidak ada; pengunjung mengisinya sendiri bersama peran lain.
     */
    public function seedProdiKosong(AcademicUnit $prodi): void
    {
        $kurikulum = Kurikulum::query()->firstOrCreate(
            ['academic_unit_id' => $prodi->id, 'kode' => 'SIM-'.($prodi->code ?? 'PRODI').'-2025'],
            [
                'id' => (string) Str::uuid(),
                'nama' => 'Kurikulum Simulasi 2025',
                'tahun' => 2025,
                'target_capaian_lulusan' => 75,
                'deskripsi' => 'Kurikulum kosong untuk latihan pengisian dari awal',
                'is_active' => true,
                'dibuat_oleh' => $this->timkur->id,
            ],
        );

        Mk::query()->firstOrCreate(
            ['kurikulum_id' => $kurikulum->id, 'nama' => 'Kalkulus I'],
            [
                'id' => (string) Str::uuid(),
                'academic_unit_id' => $prodi->id,
                'state' => 'draft',
                'koordinator_mk_id' => $this->korma->id,
                'sks_teori' => 2,
                'sks_praktik' => 1,
                'sks_lapangan' => 0,
                'sks' => 3,
                'jenis' => 'wajib',
                'is_active' => true,
            ],
        );
    }

    /**
     * Contoh terisi ringkas: SATU mata kuliah (Kalkulus I) yang memikul kedua CPL,
     * masing-masing lewat satu CPMK, lengkap sampai nilai. Tiap rantai dilaporkan
     * lewat $lapor supaya progres pembangunannya terlihat per ruas OBE.
     *
     * @param  callable(string): void  $lapor
     */
    public function seedProdiRingkas(AcademicUnit $prodi, callable $lapor): void
    {
        if ($this->sudahAdaHasilCpl($prodi)) {
            return;
        }

        $kodeMk = 'MAT101';
        $namaMk = array_values($this->mkProdi)[0];

        $lapor('Membuat kurikulum');
        $kurikulum = $this->buatKurikulumDasar($prodi);

        $lapor('Membuat profil lulusan');
        if (! ($kurikulum->state->equals(AktifState::class) && $kurikulum->is_active)) {
            $this->buatProfilLulusan($prodi, $kurikulum);
            $kurikulum = $this->aktifkanKurikulum($kurikulum);
        }

        $lapor('Membuat CPL');
        $cplIds = $this->buatCplDanProfil($prodi, $kurikulum);

        $lapor('Membuat BoK dan pemetaan CPL–BoK');
        $cplBokMap = $this->buatBokDanPivot($kurikulum, $cplIds, count($cplIds));

        $lapor('Membuat mata kuliah dan penawarannya');
        $mk = Mk::query()->firstOrCreate(
            ['kurikulum_id' => $kurikulum->id, 'nama' => $namaMk],
            [
                'id' => (string) Str::uuid(),
                'academic_unit_id' => $prodi->id,
                'state' => 'penilaian',
                'koordinator_mk_id' => $this->korma->id,
                'sks_teori' => 2,
                'sks_praktik' => 1,
                'sks_lapangan' => 0,
                'sks' => 3,
                'jenis' => 'wajib',
                'is_active' => true,
            ],
        );

        // Satu CPL–MK per CPL: MK ini satu-satunya penyumbang tiap CPL (bobot 100).
        $cplMkList = [];
        foreach (array_values($cplBokMap) as $cplBokId) {
            $cplMkList[] = CplMk::query()->firstOrCreate(
                ['cpl_bok_id' => $cplBokId, 'mk_id' => $mk->id],
                ['id' => (string) Str::uuid(), 'bobot' => 100],
            );
        }

        $mkUnit = MkUnit::query()->firstOrCreate(
            ['mk_id' => $mk->id, 'kurikulum_id' => $kurikulum->id, 'kode' => $kodeMk],
            [
                'id' => (string) Str::uuid(),
                'academic_unit_id' => $prodi->id,
                'semester_ke' => 1,
                'is_active' => true,
            ],
        );

        $lapor('Membuat CPMK dan pemetaannya ke CPL');
        $mkCpmkList = [];
        foreach ($cplMkList as $i => $cplMk) {
            $cpmk = Cpmk::query()->firstOrCreate(
                ['mk_id' => $mk->id, 'kode' => 'CPMK-'.$kodeMk.'-'.($i + 1)],
                [
                    'id' => (string) Str::uuid(),
                    'deskripsi' => 'CPMK '.($i + 1).' '.$namaMk.' (menopang CPL-SIM-0'.($i + 1).')',
                ],
            );

            $this->berlakukanCpmk($cpmk);

            $mkCpmkList[] = MkCpmk::query()->firstOrCreate(
                ['cpl_mk_id' => $cplMk->id, 'cpmk_id' => $cpmk->id],
                ['id' => (string) Str::uuid(), 'bobot' => 100],
            );
        }

        $lapor('Membuat Sub-CPMK');
        $subcpmkIds = [];
        foreach ($mkCpmkList as $i => $mkCpmk) {
            foreach (['A', 'B'] as $suffix) {
                $sub = $this->buatSubcpmk($mkCpmk, 'SUB-'.$kodeMk.'-'.($i + 1).$suffix, 'Sub-CPMK '.($i + 1).$suffix, 50);
                $subcpmkIds[] = $sub->id;
            }
        }

        $lapor('Membuat asesmen dan pemetaannya ke Sub-CPMK');
        $komponenList = [
            $this->buatKomponen($mk, Evaluasi::query()->where('kode', 'uts')->firstOrFail(), 'UTS', 30),
            $this->buatKomponen($mk, Evaluasi::query()->where('kode', 'uas')->firstOrFail(), 'UAS', 40),
            $this->buatKomponen($mk, Evaluasi::query()->where('kode', 'quiz')->firstOrFail(), 'Quiz', 15),
            $this->buatKomponen($mk, Evaluasi::query()->where('kode', 'tugas')->firstOrFail(), 'Tugas', 15),
        ];

        // Bobot asesmen dibagi rata ke semua Sub-CPMK yang dipetakan, seperti
        // SubcpmkAsesmenPemetaanService::redistribusiBobotMerata(); total per asesmen tetap utuh.
        $skpIds = [];
        foreach ($subcpmkIds as $subcpmkId) {
            foreach ($komponenList as $komponen) {
                $skp = SubcpmkKomponenPenilaian::query()->firstOrCreate(
                    [
                        'subcpmk_id' => $subcpmkId,
                        'komponen_penilaian_id' => $komponen->id,
                        'semester_id' => $this->semester->id,
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'bobot' => round($komponen->bobotUntukSemester((string) $this->semester->id) / count($subcpmkIds), 2),
                    ],
                );
                $skpIds[] = $skp->id;
            }
        }

        $lapor('Membuat kelas, dosen pengampu, dan peserta');
        $kelas = KelasMk::query()->firstOrCreate(
            ['mk_unit_id' => $mkUnit->id, 'semester_id' => $this->semester->id, 'kode_kelas' => 'A'],
            [
                'id' => (string) Str::uuid(),
                'dosen_pengampu_id' => $this->dosenProdi->id,
                'koordinator_mk_id' => $this->korma->id,
                'kapasitas' => 40,
            ],
        );

        $peserta = [];
        foreach (Mahasiswa::query()->where('academic_unit_id', $prodi->id)->get() as $mhs) {
            $peserta[] = KelasMkMahasiswa::query()->firstOrCreate(
                ['kelas_mk_id' => $kelas->id, 'mahasiswa_id' => $mhs->id],
                ['id' => (string) Str::uuid()],
            );
        }

        $lapor('Mengisi nilai mahasiswa');
        foreach ($peserta as $kmm) {
            $this->isiNilaiAcak($kmm, $skpIds);
        }

        $lapor('Menghitung hasil analisis CPL');
        $this->jalankanKalkulasi(collect([$kelas]), $prodi);
    }

    /**
     * @param  bool  $agregasiInduk  false pada sandbox simulasi: induk prodi hanya wadah kosong
     */
    public function seedProdi(AcademicUnit $prodi, bool $agregasiInduk = true): void
    {
        if ($this->sudahAdaHasilCpl($prodi)) {
            return;
        }

        $kurikulum = $this->buatKurikulumAktif($prodi);
        $cplIds = $this->buatCplDanProfil($prodi, $kurikulum);
        $cplBokMap = $this->buatBokDanPivot($kurikulum, $cplIds);
        $kelasCollection = collect();

        foreach ($this->mkProdi as $kode => $nama) {
            $kelas = $this->buatRantaiPenilaianProdi(
                unit: $prodi,
                kurikulum: $kurikulum,
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

        if ($agregasiInduk) {
            $this->agregasiInduk($prodi);
        }
    }

    /**
     * @param  array{unit: AcademicUnit, kode: string, nama: string, dosen: User, koordinator: User, prodi_sumber?: AcademicUnit}  $definisi
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

        // Prodi sumber peserta WAJIB bisa ditentukan pemanggil. Tanpa itu baris
        // ini memungut prodi mana pun yang kebetulan pertama ditemukan — pada
        // basis data berisi data nyata, artinya mahasiswa sungguhan ikut
        // terdaftar ke kelas simulasi.
        $prodi = $definisi['prodi_sumber']
            ?? AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
        $mahasiswaSample = Mahasiswa::query()
            ->where('academic_unit_id', $prodi->id)
            ->limit(10)
            ->get();

        if ($mahasiswaSample->isEmpty()) {
            return;
        }

        $suffixUnit = $this->kodeSingkatUnit($unit->type);
        $kurikulum = $this->pastikanKurikulum($unit);

        $cpl = Cpl::query()->firstOrCreate(
            ['kurikulum_id' => $kurikulum->id, 'kode' => 'CPL-SIM-'.$suffixUnit],
            [
                'id' => (string) Str::uuid(),
                'academic_unit_id' => $unit->id,
                'deskripsi' => 'CPL simulasi '.$unit->nama,
                'domain' => ['kognitif'],
            ],
        );

        $bok = Bok::query()->firstOrCreate(
            ['kurikulum_id' => $kurikulum->id, 'kode' => 'BOK-SIM-'.$suffixUnit],
            [
                'id' => (string) Str::uuid(),
                'academic_unit_id' => $unit->id,
                'nama' => 'BoK simulasi '.$unit->nama,
                'deskripsi' => 'BoK sementara untuk demo',
            ],
        );

        $cplBok = CplBok::query()->firstOrCreate(
            ['cpl_id' => $cpl->id, 'bok_id' => $bok->id],
            ['id' => (string) Str::uuid(), 'bobot' => 100],
        );

        $mk = Mk::query()->firstOrCreate(
            ['kurikulum_id' => $kurikulum->id, 'nama' => $definisi['nama']],
            [
                'id' => (string) Str::uuid(),
                'academic_unit_id' => $unit->id,
                'state' => 'penilaian',
                'koordinator_mk_id' => $koordinator->id,
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
            ['mk_id' => $mk->id, 'kurikulum_id' => $kurikulum->id, 'kode' => $definisi['kode']],
            [
                'id' => (string) Str::uuid(),
                'academic_unit_id' => $unit->id,
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

        $this->berlakukanCpmk($cpmk);

        $cplMk = CplMk::query()->where('mk_id', $mk->id)->firstOrFail();

        $mkCpmk = MkCpmk::query()->firstOrCreate(
            ['cpl_mk_id' => $cplMk->id, 'cpmk_id' => $cpmk->id],
            ['id' => (string) Str::uuid(), 'bobot' => 100],
        );

        $subcpmk = $this->buatSubcpmk($mkCpmk, 'SUB-'.$definisi['kode'], 'Sub-CPMK simulasi', 100);

        $uts = Evaluasi::query()->where('kode', 'uts')->firstOrFail();
        $uas = Evaluasi::query()->where('kode', 'uas')->firstOrFail();

        $komponenUts = $this->buatKomponen($mk, $uts, 'UTS', 50);
        $komponenUas = $this->buatKomponen($mk, $uas, 'UAS', 50);

        $skpIds = [];
        foreach ([$komponenUts, $komponenUas] as $komponen) {
            // Bobot pivot langsung dipatok sama dengan bobot Komponen itu
            // sendiri — satu-satunya Sub-CPMK yang dipetakan ke komponen
            // ini, jadi seluruh bobot komponen jadi kontribusinya.
            $skp = SubcpmkKomponenPenilaian::query()->firstOrCreate(
                [
                    'subcpmk_id' => $subcpmk->id,
                    'komponen_penilaian_id' => $komponen->id,
                    'semester_id' => $this->semester->id,
                ],
                [
                    'id' => (string) Str::uuid(),
                    'bobot' => $komponen->bobotUntukSemester((string) $this->semester->id),
                ],
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

    /**
     * Prodi mengadaptasi MK milik unit lain (universitas/fakultas) yang
     * sebelumnya dibuat lewat seedMkUnitRingkas(): menambah baris MkUnit
     * berskala prodi untuk MK yang sama (tanpa mengubah baris milik unit
     * asal), sehingga CPL/BoK unit asal otomatis tersingkap di menu prodi
     * lewat CplBokAdaptasiScope — lalu memberi kode alias khusus prodi
     * untuk mendemokan CplKodeOverride/BokKodeOverride.
     */
    public function seedAdaptasiLintasUnit(
        AcademicUnit $prodi,
        AcademicUnit $unitAsal,
        string $mkKode,
        string $kodeAliasCpl,
        string $kodeAliasBok,
    ): void {
        $mkUnitAsal = MkUnit::query()
            ->where('academic_unit_id', $unitAsal->id)
            ->where('kode', $mkKode)
            ->first();

        if ($mkUnitAsal === null) {
            return;
        }

        $kurikulumProdi = $this->pastikanKurikulum($prodi);

        MkUnit::query()->firstOrCreate(
            ['mk_id' => $mkUnitAsal->mk_id, 'kurikulum_id' => $kurikulumProdi->id, 'kode' => $mkKode],
            [
                'id' => (string) Str::uuid(),
                'academic_unit_id' => $prodi->id,
                'semester_ke' => 1,
                'is_active' => true,
            ],
        );

        $suffixUnit = $this->kodeSingkatUnit($unitAsal->type);

        $cplAsal = Cpl::query()
            ->where('academic_unit_id', $unitAsal->id)
            ->where('kode', 'CPL-SIM-'.$suffixUnit)
            ->first();

        if ($cplAsal !== null) {
            CplKodeOverride::query()->updateOrCreate(
                ['academic_unit_id' => $prodi->id, 'cpl_id' => $cplAsal->id],
                ['id' => (string) Str::uuid(), 'kode' => $kodeAliasCpl],
            );
        }

        $bokAsal = Bok::query()
            ->where('academic_unit_id', $unitAsal->id)
            ->where('kode', 'BOK-SIM-'.$suffixUnit)
            ->first();

        if ($bokAsal !== null) {
            BokKodeOverride::query()->updateOrCreate(
                ['academic_unit_id' => $prodi->id, 'bok_id' => $bokAsal->id],
                ['id' => (string) Str::uuid(), 'kode' => $kodeAliasBok],
            );
        }
    }

    protected function sudahAdaHasilCpl(AcademicUnit $unit): bool
    {
        return HasilCplUnit::query()
            ->where('academic_unit_id', $unit->id)
            ->where('semester_id', $this->semester->id)
            ->exists();
    }

    protected function pastikanKurikulum(AcademicUnit $unit): Kurikulum
    {
        $existing = Kurikulum::query()
            ->where('academic_unit_id', $unit->id)
            ->orderByDesc('is_active')
            ->orderByDesc('tahun')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        if ($unit->isProdi()) {
            return $this->buatKurikulumAktif($unit);
        }

        return Kurikulum::query()->create([
            'id' => (string) Str::uuid(),
            'academic_unit_id' => $unit->id,
            'nama' => 'Kurikulum Simulasi '.$unit->nama,
            'kode' => 'SIM-'.($unit->code ?? 'UNIT').'-2025',
            'tahun' => 2025,
            'target_capaian_lulusan' => 75,
            'deskripsi' => 'Data sementara untuk demo end-to-end SILOGY',
            'is_active' => true,
            'dibuat_oleh' => $this->timkur->id,
        ]);
    }

    protected function buatKurikulumAktif(AcademicUnit $prodi): Kurikulum
    {
        $kurikulum = $this->buatKurikulumDasar($prodi);

        if ($kurikulum->state->equals(AktifState::class) && $kurikulum->is_active) {
            return $kurikulum;
        }

        $this->buatProfilLulusan($prodi, $kurikulum);

        return $this->aktifkanKurikulum($kurikulum);
    }

    /** Baris kurikulum saja (belum aktif, belum berprofil). */
    protected function buatKurikulumDasar(AcademicUnit $prodi): Kurikulum
    {
        $kode = 'SIM-'.($prodi->code ?? 'PRODI').'-2025';

        return Kurikulum::query()->firstOrCreate(
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
    }

    protected function buatProfilLulusan(AcademicUnit $prodi, Kurikulum $kurikulum): void
    {
        if ($kurikulum->profilLulusan()->exists()) {
            return;
        }

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

    protected function aktifkanKurikulum(Kurikulum $kurikulum): Kurikulum
    {
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
            ['kode' => 'CPL-SIM-01', 'deskripsi' => 'Mampu merancang pembelajaran matematika', 'domain' => ['kognitif', 'psikomotorik']],
            ['kode' => 'CPL-SIM-02', 'deskripsi' => 'Berkomitmen pada etika pendidikan', 'domain' => ['afektif']],
        ] as $row) {
            $cpl = Cpl::query()->firstOrCreate(
                ['kurikulum_id' => $kurikulum->id, 'kode' => $row['kode']],
                ['id' => (string) Str::uuid(), 'academic_unit_id' => $prodi->id, ...$row],
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
    protected function buatBokDanPivot(Kurikulum $kurikulum, array $cplIds, ?int $jumlah = null): array
    {
        $map = [];
        $bokDefs = [
            ['kode' => 'BOK-SIM-01', 'nama' => 'Aljabar & Kalkulus'],
            ['kode' => 'BOK-SIM-02', 'nama' => 'Pedagogik & Evaluasi'],
            ['kode' => 'BOK-SIM-03', 'nama' => 'Statistika & Analisis'],
        ];

        foreach (array_slice($bokDefs, 0, $jumlah ?? count($bokDefs), true) as $index => $def) {
            $bok = Bok::query()->firstOrCreate(
                ['kurikulum_id' => $kurikulum->id, 'kode' => $def['kode']],
                [
                    'id' => (string) Str::uuid(),
                    'academic_unit_id' => $kurikulum->academic_unit_id,
                    'nama' => $def['nama'],
                    'deskripsi' => 'BoK simulasi',
                ],
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
        Kurikulum $kurikulum,
        array $cplBokMap,
        string $kodeMk,
        string $namaMk,
        User $dosen,
        User $koordinator,
        int $semesterKe,
    ): ?KelasMk {
        $cplBokId = array_values($cplBokMap)[crc32($kodeMk) % count($cplBokMap)];

        $mk = Mk::query()->firstOrCreate(
            ['kurikulum_id' => $kurikulum->id, 'nama' => $namaMk],
            [
                'id' => (string) Str::uuid(),
                'academic_unit_id' => $unit->id,
                'state' => 'penilaian',
                'koordinator_mk_id' => $koordinator->id,
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
            ['mk_id' => $mk->id, 'kurikulum_id' => $kurikulum->id, 'kode' => $kodeMk],
            [
                'id' => (string) Str::uuid(),
                'academic_unit_id' => $unit->id,
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

        $this->berlakukanCpmk($cpmk);

        $cplMk = CplMk::query()->where('mk_id', $mk->id)->firstOrFail();

        $mkCpmk = MkCpmk::query()->firstOrCreate(
            ['cpl_mk_id' => $cplMk->id, 'cpmk_id' => $cpmk->id],
            ['id' => (string) Str::uuid(), 'bobot' => 100],
        );

        $subcpmkIds = [];
        foreach (['A', 'B'] as $suffix) {
            $sub = $this->buatSubcpmk($mkCpmk, 'SUB-'.$kodeMk.'-'.$suffix, 'Sub-CPMK simulasi '.$suffix, 50);
            $subcpmkIds[] = $sub->id;
        }

        $uts = Evaluasi::query()->where('kode', 'uts')->firstOrFail();
        $uas = Evaluasi::query()->where('kode', 'uas')->firstOrFail();
        $quiz = Evaluasi::query()->where('kode', 'quiz')->firstOrFail();
        $tugas = Evaluasi::query()->where('kode', 'tugas')->firstOrFail();

        $komponenList = [
            $this->buatKomponen($mk, $uts, 'UTS', 30),
            $this->buatKomponen($mk, $uas, 'UAS', 40),
            $this->buatKomponen($mk, $quiz, 'Quiz', 15),
            $this->buatKomponen($mk, $tugas, 'Tugas', 15),
        ];

        $skpIds = [];
        foreach ($subcpmkIds as $subcpmkId) {
            foreach ($komponenList as $komponen) {
                // Bobot pivot dibagi rata di antara kedua Sub-CPMK yang
                // sama-sama dipetakan ke komponen ini (bobot komponen ÷ 2),
                // meniru SubcpmkAsesmenPemetaanService::redistribusiBobotMerata().
                $skp = SubcpmkKomponenPenilaian::query()->firstOrCreate(
                    [
                        'subcpmk_id' => $subcpmkId,
                        'komponen_penilaian_id' => $komponen->id,
                        'semester_id' => $this->semester->id,
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'bobot' => round(
                            $komponen->bobotUntukSemester((string) $this->semester->id) / count($subcpmkIds),
                            2,
                        ),
                    ],
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

    /**
     * Berlakukan CPMK pada semester simulasi. Tanpa lampiran ini CPMK tidak
     * berlaku di mana pun, sehingga tidak muncul di daftar maupun kalkulasi.
     */
    protected function berlakukanCpmk(Cpmk $cpmk): void
    {
        CpmkSemester::query()->firstOrCreate([
            'cpmk_id' => $cpmk->id,
            'semester_id' => $this->semester->id,
        ], ['id' => (string) Str::uuid()]);
    }

    /**
     * Sub-CPMK beserta lampiran semesternya. Identitasnya (mk_cpmk, kode);
     * berlakunya dan bobotnya milik pasangan (sub-cpmk, semester).
     */
    protected function buatSubcpmk(MkCpmk $mkCpmk, string $kode, string $deskripsi, float $bobot): Subcpmk
    {
        $subcpmk = Subcpmk::query()->firstOrCreate(
            ['mk_cpmk_id' => $mkCpmk->id, 'kode' => $kode],
            ['id' => (string) Str::uuid(), 'deskripsi' => $deskripsi],
        );

        SubcpmkSemester::query()->updateOrCreate(
            ['subcpmk_id' => $subcpmk->id, 'semester_id' => $this->semester->id],
            ['id' => (string) Str::uuid(), 'bobot' => $bobot],
        );

        return $subcpmk;
    }

    protected function buatKomponen(Mk $mk, Evaluasi $evaluasi, string $nama, float $bobot): KomponenPenilaian
    {
        $komponen = KomponenPenilaian::query()->firstOrCreate(
            ['mk_id' => $mk->id, 'kode' => $nama],
            [
                'id' => (string) Str::uuid(),
                'evaluasi_id' => $evaluasi->id,
                'nama' => $nama,
            ],
        );

        // Berlakunya asesmen pada semester ini, beserta bobotnya, ada di pivot.
        KomponenPenilaianSemester::query()->updateOrCreate(
            [
                'komponen_penilaian_id' => $komponen->id,
                'semester_id' => $this->semester->id,
            ],
            ['id' => (string) Str::uuid(), 'bobot' => $bobot],
        );

        return $komponen;
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
