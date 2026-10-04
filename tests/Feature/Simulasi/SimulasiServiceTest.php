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
use App\Modules\Simulasi\Support\PengaturanSimulasi;
use App\Modules\Simulasi\Support\Ranah;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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

it('membangun rantai OBE lengkap di prodi sandbox, dengan induk kosong di atasnya', function () {
    siapkanInfrastrukturNyata();

    $hasil = app(SimulasiService::class)->buat();
    $kode = $hasil->jalan->kode();

    $prodiSim = Ranah::sebagai($hasil->jalan->id, fn () => AcademicUnit::query()->where('code', 'SIM-PRODI-'.$kode)->firstOrFail());

    Ranah::sebagai($hasil->jalan->id, function () use ($hasil, $kode, $prodiSim): void {
        expect($hasil->jalan->status)->toBe(SimulasiJalan::STATUS_SELESAI)
            ->and(AcademicUnit::query()->where('code', 'SIM-UNIV-'.$kode)->exists())->toBeTrue()
            ->and(AcademicUnit::query()->where('code', 'SIM-FAK-'.$kode)->exists())->toBeTrue()
            ->and($prodiSim->parent->code)->toBe('SIM-FAK-'.$kode)
            ->and(Kurikulum::query()->where('academic_unit_id', '!=', $prodiSim->id)->count())->toBe(0)
            ->and(Kurikulum::query()->where('academic_unit_id', $prodiSim->id)->where('is_active', true)->exists())->toBeTrue()
            ->and(Cpmk::query()->count())->toBeGreaterThan(0)
            ->and(NilaiMahasiswa::query()->count())->toBeGreaterThan(0)
            ->and(HasilCplUnit::query()->where('academic_unit_id', $prodiSim->id)->count())->toBeGreaterThan(0);
    });
});

it('membuat 6 akun per sandbox, semuanya di prodi sandbox itu dan berdomain simulasi', function () {
    siapkanInfrastrukturNyata();

    $jalan = app(SimulasiService::class)->buat()->jalan;

    Ranah::sebagai($jalan->id, function () use ($jalan): void {
        $idUnit = AcademicUnit::query()->pluck('id');
        expect(AkunSimulasi::akun())->toHaveCount(6);

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

    $a = $simulasi->buat(mode: 'kosong')->jalan;
    $b = $simulasi->buat(mode: 'kosong')->jalan;

    expect($a->kode())->not->toBe($b->kode())
        ->and(User::withoutGlobalScopes()->where('sandbox_id', $a->id)->count())->toBe(6)
        ->and(User::withoutGlobalScopes()->where('sandbox_id', $b->id)->count())->toBe(6)
        ->and(Mahasiswa::withoutGlobalScopes()->where('sandbox_id', $a->id)->count())->toBe(30)
        ->and(Mahasiswa::withoutGlobalScopes()->where('sandbox_id', $b->id)->count())->toBe(30);
});

it('menolak membangun melebihi batas sandbox', function () {
    siapkanInfrastrukturNyata();
    PengaturanSimulasi::atur('kapasitas', 1);

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

it('membangun beberapa ruang, masing-masing bertoken unik, dan menghitungnya sebagai ruang', function () {
    siapkanInfrastrukturNyata();
    $simulasi = app(SimulasiService::class);

    $ruang = collect(range(1, 3))->map(fn () => $simulasi->buat(mode: 'kosong')->jalan);

    expect($ruang->pluck('pin')->unique()->count())->toBe(3)
        ->and($ruang->every(fn ($r) => $r->status === SimulasiJalan::STATUS_SELESAI))->toBeTrue()
        ->and($simulasi->jumlahRuang())->toBe(3);
});

it('menolak membangun ruang melebihi kapasitas yang diatur di basis data', function () {
    siapkanInfrastrukturNyata();
    PengaturanSimulasi::atur('kapasitas', 2);
    $simulasi = app(SimulasiService::class);
    $simulasi->buat(mode: 'kosong');
    $simulasi->buat(mode: 'kosong');

    expect(fn () => $simulasi->buat(mode: 'kosong'))->toThrow(KapasitasSandboxPenuhException::class)
        ->and($simulasi->jumlahRuang())->toBe(2);
});

it('kapasitas bawaan 200 dan dapat diubah Super Admin tanpa menyentuh .env', function () {
    expect(PengaturanSimulasi::ambil('kapasitas'))->toBe(200)
        ->and(PengaturanSimulasi::ambil('batas_coba'))->toBe(60);

    PengaturanSimulasi::atur('kapasitas', 350);

    expect(PengaturanSimulasi::ambil('kapasitas'))->toBe(350);
});

it('membuang ruang yang tak dipakai melewati umur yang diatur dan menyisakan yang segar', function () {
    siapkanInfrastrukturNyata();
    PengaturanSimulasi::atur('umur_hari', 1);
    $simulasi = app(SimulasiService::class);

    $usang = $simulasi->buat(mode: 'kosong')->jalan;
    $segar = $simulasi->buat(mode: 'kosong')->jalan;
    $usang->forceFill(['terakhir_aktif_pada' => now()->subDays(2), 'selesai_pada' => now()->subDays(2)])->save();

    expect($simulasi->bersihkanKedaluwarsa())->toBe(1)
        ->and($usang->fresh()->status)->toBe(SimulasiJalan::STATUS_DIBONGKAR)
        ->and($segar->fresh()->status)->toBe(SimulasiJalan::STATUS_SELESAI);
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

it('tahap peran dan master evaluasi diserialkan lewat kunci agar sandbox serentak tidak bentrok kunci ganda', function () {
    siapkanInfrastrukturNyata();

    // Menahan kunci seolah-olah pembangun lain sedang bekerja: pembangun kedua harus menunggu, bukan menyisipkan ganda.
    $kunci = Cache::lock('simulasi:siapkan-global', 5);
    expect($kunci->get())->toBeTrue();
    $kunci->release();

    $a = app(SimulasiService::class)->buat(mode: 'kosong')->jalan;
    $b = app(SimulasiService::class)->buat(mode: 'kosong')->jalan;

    expect(Spatie\Permission\Models\Role::query()->where('name', 'Tim Kurikulum')->count())->toBe(1)
        ->and($a->fresh()->status)->toBe(SimulasiJalan::STATUS_SELESAI)
        ->and($b->fresh()->status)->toBe(SimulasiJalan::STATUS_SELESAI)
        ->and(Cache::lock('simulasi:siapkan-global', 5)->get())->toBeTrue();
});
