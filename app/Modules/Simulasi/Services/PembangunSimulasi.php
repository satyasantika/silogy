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
use App\Modules\Simulasi\Support\Ranah;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\Support\SimulasiAkademikBuilder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Membangun satu sandbox simulasi utuh di dalam pohon unitnya sendiri.
 *
 * Seluruh pembangunan berjalan di bawah Ranah::sebagai(<id sandbox>): setiap
 * baris akar yang lahir otomatis diberi sandbox_id, dan setiap pencarian
 * (firstOrCreate, where) hanya melihat isi sandbox ini. Itu yang membuat
 * pembangunan deterministik: tidak ada sisa data dari percobaan lain yang
 * bisa terpungut, dan tidak ada data inti yang bisa tersentuh.
 *
 * Yang DIMILIKI sandbox: satu prodi SIM-* beserta dua induknya yang kosong
 * (fakultas dan universitas, hanya agar prodi tidak tanpa induk), akun sim-*,
 * dan, pada contoh terisi, mahasiswa serta seluruh rantai OBE di atasnya.
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
     * Tahap pembangunan sesuai urutan lapor() di bangunDalamRanah(), beserta
     * keterangan untuk layar progres. Kunci = teks yang dilaporkan. Test
     * memastikan daftar ini sama persis dengan laporan yang sebenarnya.
     *
     * @return list<array{label: string, keterangan: string}>
     */
    public static function tahap(string $mode): array
    {
        $dasar = [
            ['label' => 'Menyiapkan peran dan izin', 'keterangan' => 'Memastikan peran dan izin akses tersedia.'],
            ['label' => 'Menyiapkan master evaluasi', 'keterangan' => 'Menyiapkan jenis evaluasi (tugas, UTS, UAS, dan sejenisnya).'],
            ['label' => 'Membangun program studi simulasi', 'keterangan' => 'Membuat Program Studi Simulasi beserta induknya yang kosong.'],
            ['label' => 'Membuat akun simulasi', 'keterangan' => 'Membuat 6 akun (satu per peran) beserta penugasannya.'],
            ['label' => 'Membuat mahasiswa simulasi', 'keterangan' => 'Membuat '.self::JUMLAH_MAHASISWA.' mahasiswa contoh di program studi simulasi.'],
        ];

        if ($mode === SimulasiJalan::MODE_KOSONG) {
            return [...$dasar,
                ['label' => 'Membuat kurikulum dan satu mata kuliah kosong', 'keterangan' => 'Satu kurikulum tanpa isi dan satu mata kuliah yang Koordinatornya sudah ditetapkan.'],
            ];
        }

        return [...$dasar,
            ['label' => 'Membangun rantai OBE prodi simulasi', 'keterangan' => 'Kurikulum, profil lulusan, CPL, BoK, MK prodi, CPMK, Sub-CPMK, asesmen, kelas, sampai nilai.'],
            ['label' => 'Mengajukan satu usulan perubahan CPMK', 'keterangan' => 'Satu usulan dari Koordinator MK untuk ditinjau Tim Kurikulum.'],
        ];
    }

    /**
     * @param  callable(string): void|null  $lapor
     * @return array<string, mixed>
     */
    public function bangun(SimulasiJalan $jalan, ?callable $lapor = null): array
    {
        return Ranah::sebagai((string) $jalan->getKey(), fn (): array => $this->bangunDalamRanah($jalan, $lapor));
    }

    /**
     * @param  callable(string): void|null  $lapor
     * @return array<string, mixed>
     */
    protected function bangunDalamRanah(SimulasiJalan $jalan, ?callable $lapor): array
    {
        $kode = $jalan->kode();
        $lapor ??= static fn (string $langkah): null => null;

        // Peran, izin, dan master evaluasi bersifat global (bukan milik sandbox) dan
        // dibuat dengan firstOrCreate. Dua sandbox yang dibangun bersamaan pada
        // basis data yang belum memilikinya akan sama-sama menyisipkan baris yang
        // sama sehingga salah satunya gagal karena kunci ganda; maka diserialkan.
        $lapor('Menyiapkan peran dan izin');
        Cache::lock('simulasi:siapkan-global', 120)->block(120, static function (): void {
            RolePermissionSeeder::seedPeranDanIzin();
        });

        $lapor('Menyiapkan master evaluasi');
        Cache::lock('simulasi:siapkan-global', 120)->block(120, function (): void {
            $this->pastikanEvaluasi();
        });

        $semester = $this->semesterAktif();
        $jalan->forceFill(['semester_id' => $semester->getKey()])->save();

        $lapor('Membangun program studi simulasi');
        $unit = $this->buatUnit($kode);

        $lapor('Membuat akun simulasi');
        $akun = $this->buatAkun($unit, $kode);

        $lapor('Membuat mahasiswa simulasi');
        $this->buatMahasiswa($unit['prodi'], $kode);

        $builder = new SimulasiAkademikBuilder(
            semester: $semester,
            timkur: $akun['sim-timkur'],
            korma: $akun['sim-korma'],
            dosenProdi: $akun['sim-dosen'],
            jumlahMk: (int) $jalan->jumlah_mk,
        );

        // Contoh kosong: satu kurikulum dan satu MK saja. Mahasiswa tetap ada supaya
        // langkah peserta kelas bisa dilatih; selebihnya diisi pengunjung.
        if ($jalan->kosong()) {
            $lapor('Membuat kurikulum dan satu mata kuliah kosong');
            $builder->seedProdiKosong($unit['prodi']);

            return ['unit' => $unit, 'akun' => $akun, 'semester' => $semester];
        }

        $lapor('Membangun rantai OBE prodi simulasi');
        $builder->seedProdi($unit['prodi'], agregasiInduk: false);

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
    protected function buatUnit(string $kode): array
    {
        $hasil = [];

        foreach (AkunSimulasi::unit($kode) as $kunci => $definisi) {
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
    protected function buatAkun(array $unit, string $kode): array
    {
        $hasil = [];

        // Satu hash untuk seluruh akun sandbox ini: Hash::make berbiaya mahal
        // dan kata sandinya acak serta tak pernah ditampilkan.
        $sandi = Hash::make(Str::random(40));

        foreach (AkunSimulasi::akun() as $kunci => $definisi) {
            $username = AkunSimulasi::username($kunci, $kode);

            $user = User::query()->firstOrCreate(
                ['username' => $username],
                [
                    'email' => AkunSimulasi::surel($username),
                    'nidn' => AkunSimulasi::nidn($kunci, $kode),
                    'full_name' => $definisi['nama'],
                    'password' => $sandi,
                    'email_verified_at' => now(),
                ],
            );

            // Aman dipanggil pada user mana pun selain user yang sedang login:
            // filter peran-aktif di User::roles() hanya berlaku untuk dirinya
            // sendiri, dan akun sim-* tidak pernah menjadi pemicu.
            $user->syncRoles([$definisi['peran']]);

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

            $hasil[$kunci] = $user;
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

    protected function buatMahasiswa(AcademicUnit $prodi, string $kode): void
    {
        $ada = Mahasiswa::query()->where('academic_unit_id', $prodi->getKey())->count();

        if ($ada >= self::JUMLAH_MAHASISWA) {
            return;
        }

        // NIM eksplisit, bukan numerik acak pabrik: kolom nim unik global dan
        // acak 10 digit tidak menjamin bebas bentrok dengan data inti.
        Mahasiswa::factory()
            ->count(self::JUMLAH_MAHASISWA - $ada)
            ->sequence(fn ($urutan) => ['nim' => AkunSimulasi::nim($kode, $ada + $urutan->index + 1)])
            ->create(['academic_unit_id' => $prodi->getKey()]);
    }
}
