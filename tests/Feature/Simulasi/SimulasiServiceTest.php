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
use App\Modules\Simulasi\Exceptions\SemesterAktifTidakAdaException;
use App\Modules\Simulasi\Exceptions\SimulasiSudahAdaException;
use App\Modules\Simulasi\Models\SimulasiArtefak;
use App\Modules\Simulasi\Models\SimulasiJalan;
use App\Modules\Simulasi\Services\SimulasiService;
use App\Modules\Simulasi\Support\AkunSimulasi;
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
    $lewati = ['simulasi_jalan', 'simulasi_artefak', 'migrations', 'sessions',
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

it('membangun rantai OBE lengkap di dalam pohon unit simulasi', function () {
    siapkanInfrastrukturNyata();

    $hasil = app(SimulasiService::class)->buat();

    $prodiSim = AcademicUnit::query()->where('code', 'SIM-PRODI')->firstOrFail();

    expect($hasil->jalan->status)->toBe(SimulasiJalan::STATUS_SELESAI)
        ->and(AcademicUnit::query()->where('code', 'SIM-UNIV')->exists())->toBeTrue()
        ->and(AcademicUnit::query()->where('code', 'SIM-FAK')->exists())->toBeTrue()
        ->and($prodiSim->parent->code)->toBe('SIM-FAK')
        ->and(Kurikulum::query()->where('academic_unit_id', $prodiSim->id)->where('is_active', true)->exists())->toBeTrue()
        ->and(Cpmk::query()->count())->toBeGreaterThan(0)
        ->and(NilaiMahasiswa::query()->count())->toBeGreaterThan(0)
        ->and(HasilCplUnit::query()->where('academic_unit_id', $prodiSim->id)->count())->toBeGreaterThan(0);
});

it('menempatkan seluruh akun simulasi di unit simulasi, bukan unit nyata', function () {
    siapkanInfrastrukturNyata();

    app(SimulasiService::class)->buat();

    $idUnitSim = AcademicUnit::query()->where('code', 'like', 'SIM-%')->pluck('id');

    foreach (array_keys(AkunSimulasi::akun()) as $username) {
        $user = User::query()->where('username', $username)->firstOrFail();

        expect($user->email)->toEndWith(AkunSimulasi::DOMAIN);

        foreach ($user->academicUnitUsers as $pivot) {
            expect($idUnitSim)->toContain($pivot->academic_unit_id);
        }
    }
});

it('tidak pernah mendaftarkan mahasiswa nyata ke kelas simulasi', function () {
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

it('menolak membangun simulasi kedua', function () {
    siapkanInfrastrukturNyata();

    $simulasi = app(SimulasiService::class);
    $simulasi->buat();

    expect(fn () => $simulasi->buat())->toThrow(SimulasiSudahAdaException::class);
});

it('menolak berjalan tanpa semester, karena semester tidak pernah bisa dihapus lagi', function () {
    (new AcademicUnitSeeder)->run();
    (new RolePermissionSeeder)->run();
    (new EvaluasiSeeder)->run();

    Semester::query()->delete();

    expect(fn () => app(SimulasiService::class)->buat())
        ->toThrow(SemesterAktifTidakAdaException::class);
});

it('mengembalikan basis data persis seperti semula setelah dihapus', function () {
    siapkanInfrastrukturNyata();

    // Data NYATA yang harus selamat, dibuat sebelum simulasi menyentuh apa pun.
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
    $simulasi->buat();
    $simulasi->hapus();

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
    $simulasi->buat();
    $simulasi->hapus();

    expect(Role::query()->count())->toBe($jumlahPeran)
        ->and(Permission::query()->count())->toBe($jumlahIzin)
        ->and(Semester::query()->count())->toBe($jumlahSemester)
        ->and(Evaluasi::query()->count())->toBe($jumlahEvaluasi)
        ->and(AcademicUnit::query()->whereIn('id', $unitNyata)->count())->toBe($unitNyata->count())
        ->and(AcademicUnit::query()->where('code', 'like', 'SIM-%')->count())->toBe(0);
});

it('tidak menyentuh kata sandi akun nyata yang memakai username akun demo', function () {
    (new AcademicUnitSeeder)->run();

    // Seorang dosen sungguhan yang kebetulan memakai username "dosen".
    $nyata = User::factory()->create([
        'username' => 'dosen',
        'email' => 'dosen.sungguhan@unsil.ac.id',
        'password' => Hash::make('sandi-pribadi-saya'),
    ]);

    (new RolePermissionSeeder)->run();

    expect(Hash::check('sandi-pribadi-saya', $nyata->fresh()->password))->toBeTrue();
});

it('hanya mengakui akun yang benar-benar dibuatnya sebagai milik simulasi', function () {
    siapkanInfrastrukturNyata();

    $jalan = app(SimulasiService::class)->buat()->jalan;
    $simulasi = app(SimulasiService::class);

    $akunSimulasi = User::query()->where('username', 'sim-timkur')->firstOrFail();
    $akunNyata = User::query()->where('username', 'timkur')->firstOrFail();

    expect($simulasi->memiliki($jalan, $akunSimulasi))->toBeTrue()
        ->and($simulasi->memiliki($jalan, $akunNyata))->toBeFalse();
});

it('membersihkan buku besar setelah pembongkaran', function () {
    siapkanInfrastrukturNyata();

    $simulasi = app(SimulasiService::class);
    $jalan = $simulasi->buat()->jalan;

    expect(SimulasiArtefak::query()->where('simulasi_jalan_id', $jalan->id)->count())->toBeGreaterThan(0);

    $simulasi->hapus();

    expect(SimulasiArtefak::query()->where('simulasi_jalan_id', $jalan->id)->count())->toBe(0)
        ->and($jalan->fresh()->status)->toBe(SimulasiJalan::STATUS_DIBONGKAR)
        ->and($simulasi->aktif())->toBeNull();
});

it('bisa dibangun ulang berkali-kali tanpa sisa', function () {
    siapkanInfrastrukturNyata();

    $simulasi = app(SimulasiService::class);
    $simulasi->buat();
    $simulasi->hapus();

    $sebelum = capJariBasisData();

    $simulasi->bangunUlang();
    $simulasi->hapus();

    expect(capJariBasisData())->toBe($sebelum)
        ->and(KelasMk::query()->count())->toBe(0);
});
