<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\CPL\Models\Cpl;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kalkulasi\Models\HasilCplUnit;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Models\Cpmk;
use App\Modules\Penilaian\Models\Evaluasi;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use App\Modules\Simulasi\Exceptions\KapasitasSandboxPenuhException;
use App\Modules\Simulasi\Exceptions\SemesterAktifTidakAdaException;
use App\Modules\Simulasi\Models\SimulasiArtefak;
use App\Modules\Simulasi\Models\SimulasiJalan;
use App\Modules\Simulasi\Services\SimulasiService;
use App\Modules\Simulasi\Support\AkunSimulasi;
use App\Modules\Simulasi\Support\Ranah;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * Cap jari seluruh tabel data. Sengaja membandingkan ISI baris, bukan sekadar
 * jumlahnya: pembongkaran yang menghapus satu baris nyata lalu kebetulan
 * menyisakan jumlah yang sama tetap harus ketahuan.
 *
 * @return array<string, string>
 */
function capJariBasisData(): array
{
    $lewati = ['simulasi_jalan', 'simulasi_artefak', 'simulasi_pengaturan', 'migrations', 'sessions',
        'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'activity_log'];

    $hasil = [];

    foreach (Schema::getTableListing() as $tabel) {
        $tabel = str_contains($tabel, '.') ? substr((string) strrchr($tabel, '.'), 1) : $tabel;

        if (in_array($tabel, $lewati, true)) {
            continue;
        }

        $kolom = Schema::getColumnListing($tabel);

        if ($kolom === []) {
            continue;
        }

        $kunci = in_array('id', $kolom, true) ? 'id' : $kolom[0];

        $cap = DB::table($tabel)->orderBy($kunci)->get()
            ->map(fn ($baris) => sha1((string) json_encode($baris)))
            ->sort()
            ->implode('');

        $hasil[$tabel] = sha1($cap);
    }

    return $hasil;
}

function siapkanInfrastrukturNyata(): void
{
    (new AcademicUnitSeeder)->run();
    (new RolePermissionSeeder)->run();
    (new SemesterSeeder)->run();
    (new EvaluasiSeeder)->run();
}

it('membangun rantai OBE lengkap di dalam pohon unit sandbox', function () {
    siapkanInfrastrukturNyata();

    $hasil = app(SimulasiService::class)->buat();
    $kode = $hasil->jalan->kode();

    $prodiSim = Ranah::sebagai($hasil->jalan->id, fn () => AcademicUnit::query()->where('code', 'SIM-PRODI-'.$kode)->firstOrFail());

    Ranah::sebagai($hasil->jalan->id, function () use ($hasil, $kode, $prodiSim): void {
        expect($hasil->jalan->status)->toBe(SimulasiJalan::STATUS_SELESAI)
            ->and(AcademicUnit::query()->where('code', 'SIM-UNIV-'.$kode)->exists())->toBeTrue()
            ->and(AcademicUnit::query()->where('code', 'SIM-FAK-'.$kode)->exists())->toBeTrue()
            ->and($prodiSim->parent->code)->toBe('SIM-FAK-'.$kode)
            ->and(Kurikulum::query()->where('academic_unit_id', $prodiSim->id)->where('is_active', true)->exists())->toBeTrue()
            ->and(Cpmk::query()->count())->toBeGreaterThan(0)
            ->and(NilaiMahasiswa::query()->count())->toBeGreaterThan(0)
            ->and(HasilCplUnit::query()->where('academic_unit_id', $prodiSim->id)->count())->toBeGreaterThan(0);
    });
});

it('membuat 18 akun per sandbox, semuanya di unit sandbox itu dan berdomain simulasi', function () {
    siapkanInfrastrukturNyata();

    $jalan = app(SimulasiService::class)->buat()->jalan;

    Ranah::sebagai($jalan->id, function () use ($jalan): void {
        $idUnit = AcademicUnit::query()->pluck('id');
        expect(AkunSimulasi::akun())->toHaveCount(18);

        foreach (array_keys(AkunSimulasi::akun()) as $kunci) {
            $user = User::query()->where('username', AkunSimulasi::username($kunci, $jalan->kode()))->firstOrFail();

            expect($user->email)->toEndWith(AkunSimulasi::DOMAIN)
                ->and($user->sandbox_id)->toBe($jalan->id)
                ->and($user->academicUnitUsers)->toHaveCount(1);

            foreach ($user->academicUnitUsers as $pivot) {
                expect($idUnit)->toContain($pivot->academic_unit_id);
            }
        }
    });
});

it('tidak membuat akun Super Admin dan tidak menyetel sandi yang dikenal', function () {
    siapkanInfrastrukturNyata();

    $jalan = app(SimulasiService::class)->buat()->jalan;

    Ranah::sebagai($jalan->id, function () use ($jalan): void {
        expect(User::query()->where('username', 'like', 'sim-superadmin%')->exists())->toBeFalse();

        $user = User::query()->where('username', AkunSimulasi::username('sim-dosen', $jalan->kode()))->firstOrFail();
        expect(Hash::check('siliwangi', $user->password))->toBeFalse();
    });
});

it('tidak pernah mendaftarkan mahasiswa nyata ke kelas sandbox', function () {
    siapkanInfrastrukturNyata();

    $prodiNyata = AcademicUnit::query()
        ->where('type', 'study_program')
        ->where('code', 'not like', 'SIM-%')
        ->firstOrFail();

    $mahasiswaNyata = Mahasiswa::factory()->count(5)->create([
        'academic_unit_id' => $prodiNyata->id,
    ])->pluck('id');

    app(SimulasiService::class)->buat();

    $pesertaSimulasi = DB::table('kelas_mk_mahasiswa')->pluck('mahasiswa_id');

    expect($pesertaSimulasi->intersect($mahasiswaNyata))->toBeEmpty();
});

it('membangun dua sandbox berdampingan tanpa bentrok kolom unik', function () {
    siapkanInfrastrukturNyata();
    $simulasi = app(SimulasiService::class);

    $a = $simulasi->buat()->jalan;
    $b = $simulasi->buat()->jalan;

    expect($a->kode())->not->toBe($b->kode())
        ->and(User::withoutGlobalScopes()->where('sandbox_id', $a->id)->count())->toBe(18)
        ->and(User::withoutGlobalScopes()->where('sandbox_id', $b->id)->count())->toBe(18)
        ->and(Mahasiswa::withoutGlobalScopes()->where('sandbox_id', $a->id)->count())->toBe(30)
        ->and(Mahasiswa::withoutGlobalScopes()->where('sandbox_id', $b->id)->count())->toBe(30);
});

it('menolak membangun melebihi batas sandbox', function () {
    siapkanInfrastrukturNyata();
    config()->set('simulasi.maks_sandbox', 1);

    $simulasi = app(SimulasiService::class);
    $simulasi->buat();

    expect(fn () => $simulasi->buat())->toThrow(KapasitasSandboxPenuhException::class);
});

it('menolak berjalan tanpa semester, karena semester tidak pernah bisa dihapus lagi', function () {
    (new AcademicUnitSeeder)->run();
    (new RolePermissionSeeder)->run();
    (new EvaluasiSeeder)->run();

    Semester::query()->delete();

    expect(fn () => app(SimulasiService::class)->buat())
        ->toThrow(SemesterAktifTidakAdaException::class);
});

it('membuang sandbox yang gagal dibangun seketika, tanpa sisa', function () {
    (new AcademicUnitSeeder)->run();
    (new RolePermissionSeeder)->run();
    (new EvaluasiSeeder)->run();
    Semester::query()->delete();

    $sebelum = capJariBasisData();

    try {
        app(SimulasiService::class)->buat();
    } catch (SemesterAktifTidakAdaException) {
    }

    expect(capJariBasisData())->toBe($sebelum)
        ->and(SimulasiJalan::query()->masihAda()->count())->toBe(0);
});

it('mengembalikan basis data persis seperti semula setelah dihapus', function () {
    siapkanInfrastrukturNyata();

    $prodiNyata = AcademicUnit::query()
        ->where('type', 'study_program')
        ->where('code', 'not like', 'SIM-%')
        ->firstOrFail();

    Mahasiswa::factory()->count(3)->create(['academic_unit_id' => $prodiNyata->id]);

    $kurikulumNyata = Kurikulum::query()->create([
        'academic_unit_id' => $prodiNyata->id,
        'nama' => 'Kurikulum Asli',
        'kode' => 'ASLI-2025',
        'tahun' => 2025,
        'target_capaian_lulusan' => 75,
        'is_active' => false,
    ]);

    Cpl::query()->create([
        'kurikulum_id' => $kurikulumNyata->id,
        'academic_unit_id' => $prodiNyata->id,
        'kode' => 'CPL-ASLI-01',
        'deskripsi' => 'CPL milik data nyata',
        'domain' => ['kognitif'],
    ]);

    $sebelum = capJariBasisData();

    $simulasi = app(SimulasiService::class);
    $jalan = $simulasi->buat()->jalan;
    $simulasi->hapus($jalan);

    expect(capJariBasisData())->toBe($sebelum);
});

it('tidak menghapus peran, izin, semester, master evaluasi, maupun unit nyata', function () {
    siapkanInfrastrukturNyata();

    $unitNyata = AcademicUnit::query()->where('code', 'not like', 'SIM-%')->pluck('id');
    $jumlahPeran = Role::query()->count();
    $jumlahIzin = Permission::query()->count();
    $jumlahSemester = Semester::query()->count();
    $jumlahEvaluasi = Evaluasi::query()->count();

    $simulasi = app(SimulasiService::class);
    $jalan = $simulasi->buat()->jalan;
    $simulasi->hapus($jalan);

    expect(Role::query()->count())->toBe($jumlahPeran)
        ->and(Permission::query()->count())->toBe($jumlahIzin)
        ->and(Semester::query()->count())->toBe($jumlahSemester)
        ->and(Evaluasi::query()->count())->toBe($jumlahEvaluasi)
        ->and(AcademicUnit::query()->whereIn('id', $unitNyata)->count())->toBe($unitNyata->count())
        ->and(AcademicUnit::withoutGlobalScopes()->where('code', 'like', 'SIM-%')->count())->toBe(0);
});

it('hanya mengakui akun yang benar-benar dibuatnya sebagai milik sandbox', function () {
    siapkanInfrastrukturNyata();

    $simulasi = app(SimulasiService::class);
    $jalan = $simulasi->buat()->jalan;

    $akunSandbox = Ranah::sebagai($jalan->id, fn () => User::query()
        ->where('username', AkunSimulasi::username('sim-timkur', $jalan->kode()))->firstOrFail());
    $akunNyata = User::query()->where('username', 'timkur')->firstOrFail();

    expect($simulasi->memiliki($jalan, $akunSandbox))->toBeTrue()
        ->and($simulasi->memiliki($jalan, $akunNyata))->toBeFalse();
});

it('membersihkan buku besar setelah pembongkaran', function () {
    siapkanInfrastrukturNyata();

    $simulasi = app(SimulasiService::class);
    $jalan = $simulasi->buat()->jalan;

    expect(SimulasiArtefak::query()->where('simulasi_jalan_id', $jalan->id)->count())->toBeGreaterThan(0);

    $simulasi->hapus($jalan);

    expect(SimulasiArtefak::query()->where('simulasi_jalan_id', $jalan->id)->count())->toBe(0)
        ->and($jalan->fresh()->status)->toBe(SimulasiJalan::STATUS_DIBONGKAR)
        ->and($simulasi->daftar())->toHaveCount(0);
});

it('bisa dibangun dan dibongkar berkali-kali tanpa sisa', function () {
    siapkanInfrastrukturNyata();

    $simulasi = app(SimulasiService::class);
    $simulasi->hapus($simulasi->buat()->jalan);

    $sebelum = capJariBasisData();

    $simulasi->hapus($simulasi->buat()->jalan);

    expect(capJariBasisData())->toBe($sebelum)
        ->and(KelasMk::withoutGlobalScopes()->count())->toBe(0);
});

it('hapusSemua membongkar seluruh sandbox tanpa menyentuh data inti', function () {
    siapkanInfrastrukturNyata();
    $sebelum = capJariBasisData();

    $simulasi = app(SimulasiService::class);
    $simulasi->buat();
    $simulasi->buat();

    expect($simulasi->hapusSemua())->toBe(2)
        ->and(capJariBasisData())->toBe($sebelum);
});

// ── Kolam & pembersihan ──────────────────────────────────────────────────

it('mengklaim sandbox dari kolam sebelum membangun yang baru', function () {
    siapkanInfrastrukturNyata();
    config()->set('simulasi.kolam_siap', 1);

    $simulasi = app(SimulasiService::class);
    expect($simulasi->isiKolam())->toBe(1);

    $siap = SimulasiJalan::query()->siap()->firstOrFail();
    $hash = SimulasiService::hashPengunjung('pengunjung-a');

    $klaim = $simulasi->klaim($hash);

    expect($klaim->id)->toBe($siap->id)
        ->and($klaim->pengunjung)->toBe($hash)
        ->and(SimulasiJalan::query()->masihAda()->count())->toBe(1);
});

it('memberi pengunjung yang sama sandbox yang sama, dan pengunjung lain sandbox lain', function () {
    siapkanInfrastrukturNyata();
    $simulasi = app(SimulasiService::class);

    $a1 = $simulasi->klaim(SimulasiService::hashPengunjung('a'));
    $a2 = $simulasi->klaim(SimulasiService::hashPengunjung('a'));
    $b = $simulasi->klaim(SimulasiService::hashPengunjung('b'));

    expect($a2->id)->toBe($a1->id)
        ->and($b->id)->not->toBe($a1->id);
});

it('mengembalikan null saat kapasitas penuh, bukan melempar ke pengunjung', function () {
    siapkanInfrastrukturNyata();
    config()->set('simulasi.maks_sandbox', 1);
    $simulasi = app(SimulasiService::class);

    $simulasi->klaim(SimulasiService::hashPengunjung('a'));

    expect($simulasi->klaim(SimulasiService::hashPengunjung('b')))->toBeNull();
});

it('tidak membuat kolam melebihi kapasitas', function () {
    siapkanInfrastrukturNyata();
    config()->set('simulasi.kolam_siap', 5);
    config()->set('simulasi.maks_sandbox', 2);

    expect(app(SimulasiService::class)->isiKolam())->toBe(2);
});

it('membuang sandbox yang tak aktif melewati batas umur dan menyisakan kolam', function () {
    siapkanInfrastrukturNyata();
    config()->set('simulasi.umur_menit', 60);
    $simulasi = app(SimulasiService::class);

    $usang = $simulasi->klaim(SimulasiService::hashPengunjung('usang'));
    $segar = $simulasi->klaim(SimulasiService::hashPengunjung('segar'));
    $kolam = $simulasi->buat()->jalan;

    $usang->forceFill(['terakhir_aktif_pada' => now()->subMinutes(61)])->save();

    expect($simulasi->bersihkanKedaluwarsa())->toBe(1)
        ->and($usang->fresh()->status)->toBe(SimulasiJalan::STATUS_DIBONGKAR)
        ->and($segar->fresh()->status)->toBe(SimulasiJalan::STATUS_SELESAI)
        ->and($kolam->fresh()->status)->toBe(SimulasiJalan::STATUS_SELESAI);
});

it('sakelar mode latihan global dan bawaannya tertutup', function () {
    siapkanInfrastrukturNyata();
    $simulasi = app(SimulasiService::class);

    expect($simulasi->cobaPeranTerbuka())->toBeFalse();

    $simulasi->aturCobaPeran(true);
    expect($simulasi->cobaPeranTerbuka())->toBeTrue();

    config()->set('simulasi.izinkan_coba_peran', false);
    expect($simulasi->cobaPeranTerbuka())->toBeFalse();
});

it('pemutus keras di konfigurasi mengalahkan sakelar Super Admin', function () {
    $simulasi = app(SimulasiService::class);
    $simulasi->aturCobaPeran(true);

    expect($simulasi->cobaPeranTerbuka())->toBeTrue();

    config()->set('simulasi.izinkan_coba_peran', false);

    expect($simulasi->cobaPeranTerbuka())->toBeFalse()
        ->and($simulasi->cobaPeranDilarangInstans())->toBeTrue();
});

it('sakelar mode latihan bisa ditutup kembali kapan saja dan tidak ikut terhapus bersama sandbox', function () {
    siapkanInfrastrukturNyata();
    $simulasi = app(SimulasiService::class);
    $simulasi->aturCobaPeran(true);
    $simulasi->buat();
    $simulasi->hapusSemua();

    expect($simulasi->cobaPeranTerbuka())->toBeTrue();

    $simulasi->aturCobaPeran(false);

    expect($simulasi->cobaPeranTerbuka())->toBeFalse();
});
