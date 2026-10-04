<?php

use App\Models\User;
use App\Modules\Auth\Support\ActiveRole;
use App\Modules\CPL\Models\Cpl;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Support\AcademicUnitTerpilih;
use App\Modules\Kalkulasi\Models\HasilCplUnit;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kurikulum\Filament\Resources\KurikulumResource\Pages\CreateKurikulum;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\Panduan\Support\PeranPanduan;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Models\KomponenPenilaianSemester;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use App\Modules\Simulasi\Exceptions\KapasitasSandboxPenuhException;
use App\Modules\Simulasi\Models\SimulasiJalan;
use App\Modules\Simulasi\Services\PembangunSimulasi;
use App\Modules\Simulasi\Services\SimulasiService;
use App\Modules\Simulasi\Support\AkunSimulasi;
use App\Modules\Simulasi\Support\PengaturanSimulasi;
use App\Modules\Simulasi\Support\Ranah;
use App\Modules\Simulasi\Support\SesiTab;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('simulasi.izinkan_coba_peran', true);
    RateLimiter::clear('panduan-coba-peran');

    (new AcademicUnitSeeder)->run();
    (new RolePermissionSeeder)->run();
    (new SemesterSeeder)->run();
    (new EvaluasiSeeder)->run();

    app(SimulasiService::class)->aturCobaPeran(true);
});

/** Cacah entitas OBE di dalam ranah sebuah sandbox. */
function isiSandbox(SimulasiJalan $jalan): array
{
    return Ranah::sebagai((string) $jalan->getKey(), fn () => [
        'unit' => AcademicUnit::query()->count(),
        'kurikulum' => Kurikulum::query()->count(),
        'cpl' => Cpl::query()->count(),
        'mk' => Mk::query()->count(),
        'penawaran' => MkUnit::query()->count(),
        'kelas' => KelasMk::query()->count(),
        'nilai' => NilaiMahasiswa::query()->count(),
        'mahasiswa' => Mahasiswa::query()->count(),
        'akun' => User::query()->count(),
    ]);
}

// ── Ruang simulasi (contoh kosong) ───────────────────────────────────────

it('ruang berisi satu prodi, satu kurikulum, satu MK kosong milik Koordinator, 30 mahasiswa, dan sebuah token', function () {
    $jalan = app(SimulasiService::class)->buat(mode: SimulasiJalan::MODE_KOSONG)->jalan;
    $isi = isiSandbox($jalan);

    expect($jalan->mode)->toBe('kosong')
        ->and($jalan->bersama)->toBeFalse()
        ->and($jalan->pin)->toMatch('/^[A-HJ-KM-NP-Z2-9]{6}$/')
        ->and($isi['akun'])->toBe(6)
        ->and($isi['mahasiswa'])->toBe(30)
        ->and($isi['kurikulum'])->toBe(1)
        ->and($isi['mk'])->toBe(1)
        ->and($isi['cpl'])->toBe(0)
        ->and($isi['penawaran'])->toBe(0)
        ->and($isi['kelas'])->toBe(0)
        ->and($isi['nilai'])->toBe(0);

    Ranah::sebagai((string) $jalan->getKey(), function () use ($jalan): void {
        $korma = User::query()->where('username', AkunSimulasi::username('sim-korma', $jalan->kode()))->firstOrFail();

        expect(Mk::query()->firstOrFail()->koordinator_mk_id)->toBe($korma->id)
            ->and(Kurikulum::query()->firstOrFail()->is_active)->toBeTrue();
    });
});

it('token ruang unik dan tidak memuat huruf yang mudah tertukar', function () {
    $service = app(SimulasiService::class);
    $pin = collect(range(1, 12))->map(fn () => $service->buatPin());

    expect($pin->unique()->count())->toBe(12)
        ->and($pin->every(fn ($p) => preg_match('/^[A-HJ-KM-NP-Z2-9]{6}$/', $p) === 1))->toBeTrue();
});

it('prodi simulasi selalu punya induk, dan induknya wadah kosong tanpa akun maupun data akademik', function (string $mode) {
    $jalan = app(SimulasiService::class)->buat(mode: $mode)->jalan;

    Ranah::sebagai((string) $jalan->getKey(), function (): void {
        $prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
        $fakultas = $prodi->parent;
        $universitas = $fakultas?->parent;

        expect($fakultas)->not->toBeNull()
            ->and($fakultas->type)->toBe('faculty')
            ->and($universitas)->not->toBeNull()
            ->and($universitas->type)->toBe('university')
            ->and($universitas->parent_id)->toBeNull();

        $unitAkun = DB::table('academic_unit_users')
            ->whereIn('user_id', User::query()->pluck('id'))->pluck('academic_unit_id')->unique()->all();

        expect($unitAkun)->toBe([$prodi->id])
            ->and(Kurikulum::query()->whereIn('academic_unit_id', [$fakultas->id, $universitas->id])->count())->toBe(0)
            ->and(Mk::query()->whereIn('academic_unit_id', [$fakultas->id, $universitas->id])->count())->toBe(0)
            ->and(HasilCplUnit::query()->whereIn('academic_unit_id', [$fakultas->id, $universitas->id])->count())->toBe(0);
    });
})->with([SimulasiJalan::MODE_KOSONG, SimulasiJalan::MODE_TERISI]);

it('ruang dibangun tanpa MK universitas atau fakultas dan tanpa adaptasi lintas unit', function () {
    $jalan = app(SimulasiService::class)->buat(mode: SimulasiJalan::MODE_KOSONG)->jalan;

    expect(Ranah::sebagai((string) $jalan->getKey(), fn () => MkUnit::query()->pluck('kode')->all()))->toBe([]);
});

it('dua ruang berdampingan tidak bentrok kolom unik dan bertoken berbeda', function () {
    $service = app(SimulasiService::class);
    $a = $service->buat(mode: 'kosong')->jalan;
    $b = $service->buat(mode: 'kosong')->jalan;

    expect(isiSandbox($a)['akun'])->toBe(6)
        ->and(isiSandbox($b)['akun'])->toBe(6)
        ->and($a->pin)->not->toBe($b->pin);
});

it('ruang dibongkar tuntas tanpa sisa', function () {
    $service = app(SimulasiService::class);
    $jalan = $service->buat(mode: 'kosong')->jalan;

    $service->hapus($jalan);

    expect($jalan->fresh()->status)->toBe(SimulasiJalan::STATUS_DIBONGKAR)
        ->and(DB::table('users')->where('username', 'like', 'sim-%')->count())->toBe(0)
        ->and(DB::table('academic_units')->where('code', 'like', 'SIM-%')->count())->toBe(0);
});

// ── Contoh terisi (ditanam di Panduan) ───────────────────────────────────

it('contoh terisi hanya satu MK dengan rantai OBE lengkap: 2 CPL, 2 CPMK, 4 Sub-CPMK, 4 asesmen, 10 mahasiswa', function () {
    $jalan = app(SimulasiService::class)->buat(mode: SimulasiJalan::MODE_TERISI, bersama: true)->jalan;
    $isi = isiSandbox($jalan);

    expect($jalan->mode)->toBe('terisi')
        ->and($jalan->bersama)->toBeTrue()
        ->and($jalan->pin)->toBeNull()
        ->and($jalan->ringkasan['versi'])->toBe(PembangunSimulasi::VERSI_CONTOH)
        ->and($isi['akun'])->toBe(6)
        ->and($isi['mahasiswa'])->toBe(10)
        ->and($isi['kurikulum'])->toBe(1)
        ->and($isi['cpl'])->toBe(2)
        ->and($isi['mk'])->toBe(1)
        ->and($isi['penawaran'])->toBe(1)
        ->and($isi['kelas'])->toBe(1)
        ->and($isi['nilai'])->toBe(160);

    Ranah::sebagai((string) $jalan->getKey(), function (): void {
        $semester = HasilCplUnit::query()->value('semester_id');

        expect(Cpmk::query()->count())->toBe(2)
            ->and(Subcpmk::query()->count())->toBe(4)
            ->and(KomponenPenilaian::query()->count())->toBe(4)
            // Total bobot asesmen wajib 100% (aturan SyaratHulu).
            ->and((float) KomponenPenilaianSemester::query()->where('semester_id', $semester)->sum('bobot'))->toBe(100.0)
            // KEDUA CPL punya hasil analisis; CPL kedua dulu kosong karena hanya satu CPL yang terpetakan.
            ->and(HasilCplUnit::query()->count())->toBe(2)
            ->and(HasilCplUnit::query()->pluck('jumlah_mahasiswa')->all())->toBe([10, 10]);
    });
});

it('tahap pembangunan contoh terisi memecah rantai OBE menjadi satu baris per ruas', function () {
    $label = array_column(PembangunSimulasi::tahap(SimulasiJalan::MODE_TERISI), 'label');

    expect($label)->toContain('Membuat kurikulum', 'Membuat profil lulusan', 'Membuat CPL', 'Mengisi nilai mahasiswa')
        ->and(collect($label)->filter(fn ($l) => str_contains($l, 'BoK'))->count())->toBe(1)
        ->and(count($label))->toBeGreaterThan(count(PembangunSimulasi::tahap(SimulasiJalan::MODE_KOSONG)));
});

it('mode yang tak dikenal jatuh ke contoh terisi', function () {
    $jalan = app(SimulasiService::class)->buat(mode: 'ngawur', bersama: true)->jalan;

    expect($jalan->mode)->toBe('terisi');
});

it('hanya contoh terisi yang bisa dibagi; ruang latihan selalu punya token sendiri', function () {
    $service = app(SimulasiService::class);

    $terisi = $service->buat(mode: 'terisi', bersama: true)->jalan;
    $kosong = $service->buat(mode: 'kosong', bersama: true)->jalan;

    expect($terisi->bersama)->toBeTrue()
        ->and($terisi->pin)->toBeNull()
        ->and($kosong->bersama)->toBeFalse()
        ->and($kosong->pin)->not->toBeNull();
});

it('contoh terisi dibangun sekali bila belum ada, lalu dipakai ulang tanpa membangun lagi', function () {
    $service = app(SimulasiService::class);

    expect(SimulasiJalan::query()->count())->toBe(0);

    $pertama = $service->contohTerisi();
    $kedua = $service->contohTerisi();

    expect($pertama)->not->toBeNull()
        ->and($kedua->getKey())->toBe($pertama->getKey())
        ->and(SimulasiJalan::query()->bersama()->count())->toBe(1);
});

it('contoh terisi tidak memakai kapasitas ruang dan tidak dihitung sebagai ruang', function () {
    $service = app(SimulasiService::class);
    PengaturanSimulasi::atur('kapasitas', 1);

    $service->contohTerisi();
    $ruang = $service->buat(mode: 'kosong')->jalan;

    expect($service->jumlahRuang())->toBe(1)
        ->and($ruang->status)->toBe(SimulasiJalan::STATUS_SELESAI)
        ->and(fn () => $service->buat(mode: 'kosong'))->toThrow(KapasitasSandboxPenuhException::class);
});

it('contoh terisi yang sedang dibangun tidak dibangun kembar; pengunjung diminta mencoba lagi', function () {
    $service = app(SimulasiService::class);
    $sedang = $service->mulai(mode: 'terisi', bersama: true);

    expect($service->contohTerisiSedangDibangun())->toBeTrue()
        ->and($service->contohTerisi())->toBeNull()
        ->and(SimulasiJalan::query()->bersama()->count())->toBe(1)
        ->and($sedang->fresh()->status)->toBe(SimulasiJalan::STATUS_BERJALAN);
});

it('bangun ulang membuat salinan baru lalu membuang yang lama, tanpa pernah meninggalkan pengunjung tanpa contoh', function () {
    $service = app(SimulasiService::class);
    $lama = $service->contohTerisi();

    $baru = $service->bangunUlangContohTerisi();

    expect($baru->getKey())->not->toBe($lama->getKey())
        ->and($baru->status)->toBe(SimulasiJalan::STATUS_SELESAI)
        ->and($lama->fresh()->status)->toBe(SimulasiJalan::STATUS_DIBONGKAR)
        ->and(SimulasiJalan::query()->bersama()->masihAda()->count())->toBe(1)
        ->and($service->contohTerisi()->getKey())->toBe($baru->getKey());
});

it('perawatan membangun contoh yang belum ada, membiarkan yang terbaru, dan membangun ulang yang berbentuk lama', function () {
    $service = app(SimulasiService::class);

    expect($service->rawatContohTerisi())->toBe('dibangun');

    $ada = $service->contohTerisiSiap();
    expect($service->rawatContohTerisi())->toBe('ada')
        ->and($service->contohTerisiSiap()->getKey())->toBe($ada->getKey());

    // Tiru salinan dari versi kode sebelumnya.
    $ada->forceFill(['ringkasan' => ['versi' => 1]])->save();

    expect($service->contohTerisiUsang())->toBeTrue()
        ->and($service->rawatContohTerisi())->toBe('dibangun ulang')
        ->and($service->contohTerisiSiap()->getKey())->not->toBe($ada->getKey())
        ->and($service->contohTerisiUsang())->toBeFalse();
});

it('salinan bersama tidak pernah dibuang karena umur, dan Hapus Semua melewatinya', function () {
    $service = app(SimulasiService::class);
    $bersama = $service->contohTerisi();
    $bersama->forceFill(['mulai_pada' => now()->subDays(90), 'selesai_pada' => now()->subDays(90), 'terakhir_aktif_pada' => now()->subDays(90)])->save();
    $ruang = $service->buat(mode: 'kosong')->jalan;

    expect($service->bersihkanKedaluwarsa())->toBe(0)
        ->and($service->hapusSemua())->toBe(1)
        ->and($ruang->fresh()->status)->toBe(SimulasiJalan::STATUS_DIBONGKAR)
        ->and($bersama->fresh()->status)->toBe(SimulasiJalan::STATUS_SELESAI);
});

it('ruang yang tak dipakai melewati umur yang diatur dibuang, yang masih segar tidak', function () {
    $service = app(SimulasiService::class);
    PengaturanSimulasi::atur('umur_hari', 2);
    $usang = $service->buat(mode: 'kosong')->jalan;
    $segar = $service->buat(mode: 'kosong')->jalan;
    $usang->forceFill(['selesai_pada' => now()->subDays(3), 'terakhir_aktif_pada' => now()->subDays(3)])->save();

    expect($service->bersihkanKedaluwarsa())->toBe(1)
        ->and($usang->fresh()->status)->toBe(SimulasiJalan::STATUS_DIBONGKAR)
        ->and($segar->fresh()->status)->toBe(SimulasiJalan::STATUS_SELESAI);
});

it('sandbox peninggalan versi lama dibongkar otomatis; ruang bertoken, contoh bersama, dan data inti utuh', function () {
    $service = app(SimulasiService::class);
    $bersama = $service->contohTerisi();
    $ruang = $service->buat(mode: 'kosong')->jalan;
    $inti = fn (): array => [
        AcademicUnit::query()->count(), Kurikulum::query()->count(), Cpl::query()->count(),
        Mk::query()->count(), KelasMk::query()->count(), User::query()->count(),
    ];
    $sebelum = $inti();

    // Sandbox per pengunjung versi lama: terisi, tanpa token, tanpa penanda versi.
    $lama = $service->buat(mode: 'terisi')->jalan;
    $lama->forceFill(['pengunjung' => str_repeat('a', 64), 'ringkasan' => ['cacah' => []]])->save();
    // Ruang kosong lama tanpa token.
    $kosongLama = $service->buat(mode: 'kosong')->jalan;
    $kosongLama->forceFill(['pin' => null, 'ringkasan' => null])->save();
    // Yang gagal di tengah jalan tetap dibersihkan.
    $gagalLama = $service->buat(mode: 'kosong')->jalan;
    $gagalLama->forceFill(['pin' => null, 'status' => SimulasiJalan::STATUS_GAGAL, 'ringkasan' => null])->save();

    expect($service->bersihkanKedaluwarsa())->toBe(3)
        ->and($lama->fresh()->status)->toBe(SimulasiJalan::STATUS_DIBONGKAR)
        ->and($kosongLama->fresh()->status)->toBe(SimulasiJalan::STATUS_DIBONGKAR)
        ->and($gagalLama->fresh()->status)->toBe(SimulasiJalan::STATUS_DIBONGKAR)
        ->and($ruang->fresh()->status)->toBe(SimulasiJalan::STATUS_SELESAI)
        ->and($bersama->fresh()->status)->toBe(SimulasiJalan::STATUS_SELESAI)
        ->and($service->bersihkanKedaluwarsa())->toBe(0);

    // Data inti (di luar ranah simulasi) tidak berubah oleh pembongkaran.
    expect($inti())->toBe($sebelum);
});

// ── Tombol "Lihat contoh terisi" ─────────────────────────────────────────

it('tombol lihat contoh terisi membuka tab di contoh bersama untuk keenam peran, tanpa membangun ruang', function (string $slug) {
    $respons = $this->post(route('panduan.coba', ['peran' => $slug]), ['level' => 'prodi']);

    $respons->assertRedirect();
    preg_match('#/s/([a-z0-9]{24})/simulasi/masuk#', (string) $respons->headers->get('Location'), $m);
    expect($m)->toHaveCount(2);

    $bersama = SimulasiJalan::query()->bersama()->masihAda()->get();

    expect($bersama)->toHaveCount(1)
        ->and(SimulasiJalan::query()->where('bersama', false)->count())->toBe(0)
        ->and(SesiTab::cari($m[1])['jalan'])->toBe((string) $bersama->first()->getKey())
        ->and(SesiTab::cari($m[1])['ruang'])->toBeFalse();
})->with(['admin-unit', 'tim-kurikulum', 'koordinator-mk', 'dosen-pengampu', 'pimpinan', 'auditor-mutu']);

it('parameter mode di tombol lama diabaikan: selalu contoh terisi bersama', function () {
    $this->post(route('panduan.coba', ['peran' => 'tim-kurikulum']), ['level' => 'prodi', 'mode' => 'kosong'])->assertRedirect();

    expect(SimulasiJalan::query()->masihAda()->get()->map(fn ($j) => $j->mode.':'.($j->bersama ? 'bersama' : 'pribadi'))->all())
        ->toBe(['terisi:bersama']);
});

it('simulasi di tingkat Universitas dan Fakultas ditolak dengan pesan, tanpa membangun apa pun', function (string $level) {
    $this->from('/panduan')
        ->post(route('panduan.coba', ['peran' => 'tim-kurikulum']), ['level' => $level])
        ->assertRedirect('/panduan')
        ->assertSessionHas('panduan_galat', fn ($pesan) => str_contains((string) $pesan, 'Program Studi'));

    expect(SimulasiJalan::query()->count())->toBe(0);
})->with(['univ', 'fak']);

it('tingkat yang tidak dikenal tetap 404', function () {
    $this->post(route('panduan.coba', ['peran' => 'pimpinan']), ['level' => 'dunia'])->assertNotFound();
});

it('akun hanya tersedia untuk tingkat Program Studi', function () {
    expect(array_keys(AkunSimulasi::akun()))->toBe([
        'sim-adminprodi', 'sim-timkur', 'sim-korma', 'sim-dosen', 'sim-kaprodi', 'sim-auditor',
    ]);

    foreach (AkunSimulasi::akunPanduan() as $peta) {
        expect(array_keys($peta))->toBe(['prodi']);
    }

    expect(PeranPanduan::levelBisaDicoba('prodi'))->toBeTrue()
        ->and(PeranPanduan::levelBisaDicoba('fak'))->toBeFalse()
        ->and(PeranPanduan::levelBisaDicoba('univ'))->toBeFalse();
});

// ── Halaman panduan ──────────────────────────────────────────────────────

it('halaman peran menampilkan tombol contoh terisi dan tautan ruang simulasi di tingkat prodi untuk semua peran', function (string $slug) {
    $this->get(route('panduan.peran', ['peran' => $slug]))
        ->assertSee('Lihat contoh terisi')
        ->assertSee('Masuk ruang simulasi')
        ->assertSee(route('simulasi.ruang'), false)
        ->assertDontSee('Coba mengisi sendiri');
})->with(['admin-unit', 'tim-kurikulum', 'koordinator-mk', 'dosen-pengampu', 'pimpinan', 'auditor-mutu']);

it('halaman peran di tingkat Universitas dan Fakultas tetap memuat panduan tanpa tombol coba', function (string $level) {
    $this->get(route('panduan.peran', ['peran' => 'tim-kurikulum', 'level' => $level]))
        ->assertOk()
        ->assertSee('difokuskan pada tingkat')
        ->assertDontSee('Masuk ruang simulasi')
        ->assertDontSee('Lihat contoh terisi');
})->with(['univ', 'fak']);

it('halaman level prodi menampilkan tombol contoh terisi untuk setiap peran', function () {
    $html = $this->get(route('panduan.level', ['level' => 'prodi']))->getContent();

    expect(substr_count($html, 'Lihat contoh terisi'))->toBe(6)
        ->and($html)->not->toContain('Coba mengisi sendiri');
});

it('halaman level universitas dan fakultas tetap menampilkan panduan tetapi tanpa tombol coba', function (string $level) {
    $html = $this->get(route('panduan.level', ['level' => $level]))->assertOk()->getContent();

    expect(substr_count($html, 'Baca panduan'))->toBe(6)
        ->and($html)->not->toContain('Lihat contoh terisi')
        ->and($html)->toContain('Simulasi di tingkat Program Studi');
})->with(['univ', 'fak']);

it('tombol lihat contoh terisi dan halaman ruang tertutup rapat saat mode latihan ditutup', function () {
    app(SimulasiService::class)->aturCobaPeran(false);

    $this->post(route('panduan.coba', ['peran' => 'tim-kurikulum']), ['level' => 'prodi'])->assertNotFound();
    $this->get(route('simulasi.ruang'))->assertNotFound();
});

// ── Isolasi ──────────────────────────────────────────────────────────────

it('ruang tetap terisolasi dari data inti dan dari contoh terisi', function () {
    $service = app(SimulasiService::class);
    $a = $service->buat(mode: 'kosong')->jalan;
    $b = $service->buat(mode: 'terisi', bersama: true)->jalan;

    $inti = User::query()->where('username', 'adminprodi')->firstOrFail();
    $this->actingAs($inti);

    expect(User::query()->where('username', 'like', 'sim-%')->count())->toBe(0)
        ->and(isiSandbox($a)['akun'])->toBe(6)
        ->and(Ranah::sebagai((string) $a->getKey(), fn () => Cpl::query()->count()))->toBe(0)
        ->and(Ranah::sebagai((string) $b->getKey(), fn () => Cpl::query()->count()))->toBe(2);
});

it('di ruang Tim Kurikulum benar-benar dapat menambah kurikulum, dan hasilnya tetap di ruang itu', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $jalan = app(SimulasiService::class)->buat(mode: 'kosong')->jalan;
    $id = (string) $jalan->getKey();

    $timkur = Ranah::sebagai($id, fn () => User::query()
        ->where('username', AkunSimulasi::username('sim-timkur', $jalan->kode()))->firstOrFail());
    $prodi = Ranah::sebagai($id, fn () => AcademicUnit::query()->where('type', 'study_program')->firstOrFail());

    $this->actingAs($timkur);
    ActiveRole::set('Tim Kurikulum');
    AcademicUnitTerpilih::set($prodi->id);

    Livewire::test(CreateKurikulum::class)
        ->fillForm([
            'academic_unit_id' => $prodi->id,
            'nama' => 'Kurikulum Latihan Saya',
            'kode' => 'KUR-LATIHAN',
            'tahun' => 2026,
            'target_capaian_lulusan' => 75,
            'is_active' => false,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Kurikulum::query()->where('nama', 'Kurikulum Latihan Saya')->count())->toBe(1);

    auth()->logout();
    $this->actingAs(User::query()->where('username', 'timkur')->firstOrFail());

    expect(Kurikulum::query()->where('nama', 'Kurikulum Latihan Saya')->count())->toBe(0);
});
