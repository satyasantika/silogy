<?php

namespace App\Modules\Simulasi\Services;

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Models\AcademicUnitUser;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\PerubahanCpmkRequest;
use App\Modules\MK\Services\PerubahanCpmkService;
use App\Modules\Penilaian\Models\Evaluasi;
use App\Modules\Simulasi\Exceptions\SemesterAktifTidakAdaException;
use App\Modules\Simulasi\Models\SimulasiJalan;
use App\Modules\Simulasi\Support\AkunSimulasi;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\Support\SimulasiAkademikBuilder;
use Illuminate\Support\Facades\Hash;

/**
 * Membangun seluruh data simulasi di dalam pohon unitnya sendiri.
 *
 * Yang DIMILIKI simulasi: pohon unit SIM-*, akun sim-*, mahasiswa prodi
 * simulasi, dan seluruh rantai OBE di atasnya.
 *
 * Yang DIPINJAM (dipakai, tidak pernah dihapus): peran, izin, master evaluasi,
 * dan semester. Seluruh foreign key ke `semesters` bersifat RESTRICT, jadi
 * semester yang terlanjur dibuat simulasi tidak akan pernah bisa dibongkar
 * dengan aman — karena itu simulasi menolak jalan bila tak ada semester aktif.
 */
class PembangunSimulasi
{
    public const JUMLAH_MAHASISWA = 30;

    /**
     * @param  callable(string): void|null  $lapor
     * @return array<string, mixed>
     */
    public function bangun(SimulasiJalan $jalan, ?callable $lapor = null): array
    {
        $lapor ??= static fn (string $langkah): null => null;

        $lapor('Menyiapkan peran dan izin');
        RolePermissionSeeder::seedPeranDanIzin();

        $lapor('Menyiapkan master evaluasi');
        $this->pastikanEvaluasi();

        $semester = $this->semesterAktif();
        $jalan->forceFill(['semester_id' => $semester->getKey()])->save();

        $lapor('Membangun pohon unit simulasi');
        $unit = $this->buatUnit();

        $lapor('Membuat akun simulasi');
        $akun = $this->buatAkun($unit);

        $lapor('Membuat mahasiswa simulasi');
        $this->buatMahasiswa($unit['prodi']);

        $builder = new SimulasiAkademikBuilder(
            semester: $semester,
            timkur: $akun['sim-timkur'],
            korma: $akun['sim-korma'],
            dosenProdi: $akun['sim-dosen'],
        );

        $lapor('Membangun rantai OBE prodi simulasi');
        $builder->seedProdi($unit['prodi']);

        $lapor('Membangun MK tingkat universitas');
        $builder->seedMkUnitRingkas([
            'unit' => $unit['univ'],
            'kode' => 'UNV101',
            'nama' => 'Pendidikan Pancasila (Simulasi)',
            'dosen' => $akun['sim-dosenuniv'],
            'koordinator' => $akun['sim-korma'],
            'prodi_sumber' => $unit['prodi'],
        ]);

        $lapor('Membangun MK tingkat fakultas');
        $builder->seedMkUnitRingkas([
            'unit' => $unit['fak'],
            'kode' => 'FAK101',
            'nama' => 'Metodologi Penelitian Pendidikan (Simulasi)',
            'dosen' => $akun['sim-dosenfak'],
            'koordinator' => $akun['sim-korma'],
            'prodi_sumber' => $unit['prodi'],
        ]);

        $lapor('Menyiapkan adaptasi lintas unit');
        $builder->seedAdaptasiLintasUnit($unit['prodi'], $unit['univ'], 'UNV101', 'CPL-ADAPT-UNIV', 'BOK-ADAPT-UNIV');
        $builder->seedAdaptasiLintasUnit($unit['prodi'], $unit['fak'], 'FAK101', 'CPL-ADAPT-FAK', 'BOK-ADAPT-FAK');

        $lapor('Mengajukan satu usulan perubahan CPMK');
        $this->buatUsulanPerubahanCpmk($unit['prodi'], $semester, $akun['sim-korma']);

        return ['unit' => $unit, 'akun' => $akun, 'semester' => $semester];
    }

    public function semesterAktif(): Semester
    {
        $semester = Semester::query()->where('status_aktif', true)->first()
            ?? Semester::query()->orderByDesc('kode')->first();

        if ($semester === null) {
            throw SemesterAktifTidakAdaException::buat();
        }

        return $semester;
    }

    /**
     * Master evaluasi dipinjam: komponen_penilaian.evaluasi_id bersifat
     * RESTRICT, jadi baris ini tidak boleh ikut terhapus saat pembongkaran.
     */
    protected function pastikanEvaluasi(): void
    {
        $wajib = ['uts', 'uas', 'quiz', 'tugas'];

        $ada = Evaluasi::query()->whereIn('kode', $wajib)->pluck('kode')->all();

        if (count(array_diff($wajib, $ada)) > 0) {
            (new EvaluasiSeeder)->run();
        }
    }

    /**
     * @return array<string, AcademicUnit>
     */
    protected function buatUnit(): array
    {
        $hasil = [];

        foreach (AkunSimulasi::unit() as $kunci => $definisi) {
            $hasil[$kunci] = AcademicUnit::query()->firstOrCreate(
                ['code' => $definisi['code']],
                array_merge($definisi['tambahan'], [
                    'nama' => $definisi['nama'],
                    'type' => $definisi['type'],
                    'parent_id' => $definisi['induk'] === null
                        ? null
                        : $hasil[$definisi['induk']]->getKey(),
                ]),
            );
        }

        return $hasil;
    }

    /**
     * @param  array<string, AcademicUnit>  $unit
     * @return array<string, User>
     */
    protected function buatAkun(array $unit): array
    {
        $hasil = [];

        foreach (AkunSimulasi::akun() as $username => $definisi) {
            $user = User::query()->firstOrCreate(
                ['username' => $username],
                [
                    'email' => AkunSimulasi::surel($username),
                    'nidn' => $definisi['nidn'],
                    'full_name' => $definisi['nama'],
                    'password' => Hash::make(AkunSimulasi::SANDI),
                    'email_verified_at' => now(),
                ],
            );

            // Aman dipanggil pada user mana pun selain user yang sedang login:
            // filter peran-aktif di User::roles() hanya berlaku untuk dirinya
            // sendiri, dan akun sim-* tidak pernah menjadi pemicu.
            $user->syncRoles([$definisi['peran']]);

            if ($definisi['unit'] !== null) {
                AcademicUnitUser::query()->firstOrCreate(
                    [
                        'academic_unit_id' => $unit[$definisi['unit']]->getKey(),
                        'user_id' => $user->getKey(),
                    ],
                    [
                        'status_pimpinan' => $definisi['pimpinan'],
                        'status_tim_kurikulum' => $definisi['tim_kurikulum'],
                        'jabatan' => $definisi['jabatan'],
                    ],
                );
            }

            $hasil[$username] = $user;
        }

        return $hasil;
    }

    /**
     * Satu usulan perubahan CPMK yang menunggu keputusan.
     *
     * Tanpa ini kotak masuk Tim Kurikulum selalu kosong, sehingga alur
     * persetujuan — salah satu bagian paling membingungkan di sistem — tidak
     * bisa ditunjukkan maupun dilatih oleh siapa pun.
     */
    protected function buatUsulanPerubahanCpmk(AcademicUnit $prodi, Semester $semester, User $pengusul): void
    {
        // Sengaja Kalkulus I: itulah MK pertama yang dipilih Koordinator saat
        // membuka daftar, sehingga keadaan "CPMK terkunci menunggu persetujuan"
        // langsung terlihat tanpa perlu berpindah mata kuliah lebih dulu.
        $mk = Mk::query()
            ->where('academic_unit_id', $prodi->getKey())
            ->where('nama', 'Kalkulus I')
            ->first()
            ?? Mk::query()->where('academic_unit_id', $prodi->getKey())->orderBy('nama')->first();

        if ($mk === null) {
            return;
        }

        $sudahAda = PerubahanCpmkRequest::query()
            ->where('mk_id', $mk->getKey())
            ->where('semester_id', $semester->getKey())
            ->exists();

        if ($sudahAda) {
            return;
        }

        app(PerubahanCpmkService::class)->ajukan(
            mk: $mk,
            semesterId: (string) $semester->getKey(),
            pengusul: $pengusul,
            alasan: 'Rumusan CPMK-1 belum mencerminkan kemampuan pemodelan yang diminta CPL-SIM-01. '
                .'Mohon dibuka agar dapat disesuaikan pada semester berjalan.',
        );
    }

    protected function buatMahasiswa(AcademicUnit $prodi): void
    {
        $ada = Mahasiswa::query()->where('academic_unit_id', $prodi->getKey())->count();

        if ($ada >= self::JUMLAH_MAHASISWA) {
            return;
        }

        Mahasiswa::factory()
            ->count(self::JUMLAH_MAHASISWA - $ada)
            ->create(['academic_unit_id' => $prodi->getKey()]);
    }
}
