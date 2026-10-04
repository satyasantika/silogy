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
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\Panduan\Support\PeranPanduan;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use App\Modules\Simulasi\Models\SimulasiJalan;
use App\Modules\Simulasi\Services\SimulasiService;
use App\Modules\Simulasi\Support\AkunSimulasi;
use App\Modules\Simulasi\Support\Ranah;
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

// ── Contoh kosong ────────────────────────────────────────────────────────

it('contoh kosong berisi satu prodi, satu kurikulum, dan satu MK kosong milik Koordinator', function () {
    $jalan = app(SimulasiService::class)->buat(mode: SimulasiJalan::MODE_KOSONG)->jalan;
    $isi = isiSandbox($jalan);

    expect($jalan->mode)->toBe('kosong')
        ->and($jalan->bersama)->toBeFalse()
        ->and($jalan->jumlah_mk)->toBe(0)
        ->and($isi['akun'])->toBe(6)
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

        // Seluruh akun hanya bertugas di prodi; induk tidak diurus siapa pun.
        $unitAkun = DB::table('academic_unit_users')
            ->whereIn('user_id', User::query()->pluck('id'))->pluck('academic_unit_id')->unique()->all();

        expect($unitAkun)->toBe([$prodi->id])
            ->and(Kurikulum::query()->whereIn('academic_unit_id', [$fakultas->id, $universitas->id])->count())->toBe(0)
            ->and(Mk::query()->whereIn('academic_unit_id', [$fakultas->id, $universitas->id])->count())->toBe(0)
            ->and(HasilCplUnit::query()->whereIn('academic_unit_id', [$fakultas->id, $universitas->id])->count())->toBe(0);
    });
})->with([SimulasiJalan::MODE_KOSONG, SimulasiJalan::MODE_TERISI]);

it('contoh kosong dibangun tanpa MK universitas atau fakultas dan tanpa adaptasi lintas unit', function () {
    $jalan = app(SimulasiService::class)->buat(mode: SimulasiJalan::MODE_KOSONG)->jalan;

    $kode = Ranah::sebagai((string) $jalan->getKey(), fn () => MkUnit::query()->pluck('kode')->all());

    expect($kode)->toBe([]);
});

// ── Contoh terisi ────────────────────────────────────────────────────────

it('contoh terisi memuat kurikulum sampai nilai, hanya di prodi', function () {
    $jalan = app(SimulasiService::class)->buat(mode: SimulasiJalan::MODE_TERISI)->jalan;
    $isi = isiSandbox($jalan);

    expect($jalan->mode)->toBe('terisi')
        ->and($isi['akun'])->toBe(6)
        ->and($isi['kurikulum'])->toBe(1)
        ->and($isi['cpl'])->toBeGreaterThan(0)
        ->and($isi['mk'])->toBe(6)
        ->and($isi['kelas'])->toBe(6)
        ->and($isi['nilai'])->toBeGreaterThan(0);

    $kode = Ranah::sebagai((string) $jalan->getKey(), fn () => MkUnit::query()->pluck('kode')->all());

    expect($kode)->not->toContain('UNV101')->not->toContain('FAK101');
});

it('jumlah MK pada contoh terisi dapat ditentukan dan dipaksa ke rentang 1 sampai 6', function (int $minta, int $hasil) {
    $jalan = app(SimulasiService::class)->buat(jumlahMk: $minta)->jalan;

    expect($jalan->jumlah_mk)->toBe($hasil)
        ->and(isiSandbox($jalan)['mk'])->toBe($hasil);
})->with([[1, 1], [2, 2], [4, 4], [99, 6], [0, 1]]);

it('mode yang tak dikenal jatuh ke contoh terisi', function () {
    $jalan = app(SimulasiService::class)->buat(mode: 'ngawur', jumlahMk: 1)->jalan;

    expect($jalan->mode)->toBe('terisi');
});

it('hanya contoh terisi yang bisa dibagi; contoh kosong tetap milik satu pengunjung', function () {
    $service = app(SimulasiService::class);

    $terisi = $service->buat(mode: 'terisi', jumlahMk: 1, bersama: true)->jalan;
    $kosong = $service->buat(mode: 'kosong', bersama: true)->jalan;

    expect($terisi->bersama)->toBeTrue()
        ->and($kosong->bersama)->toBeFalse();
});

it('sandbox lama tanpa kolom mode dan bersama terbaca sebagai contoh terisi enam MK milik pengunjung', function () {
    $jalan = SimulasiJalan::query()->create(['status' => 'selesai', 'mulai_pada' => now()]);

    expect($jalan->fresh()->mode)->toBe('terisi')
        ->and($jalan->fresh()->jumlah_mk)->toBe(6)
        ->and($jalan->fresh()->bersama)->toBeFalse();
});

// ── Klaim: terisi bersama, kosong per pengunjung ─────────────────────────

it('semua pengunjung mendapat SATU salinan contoh terisi yang sama, tanpa memakai kapasitas tambahan', function () {
    $service = app(SimulasiService::class);

    $a = $service->klaim(SimulasiService::hashPengunjung(str_repeat('a', 40)), 'terisi');
    $b = $service->klaim(SimulasiService::hashPengunjung(str_repeat('b', 40)), 'terisi');
    $c = $service->klaim(SimulasiService::hashPengunjung(str_repeat('c', 40)), 'terisi');

    expect($a->bersama)->toBeTrue()
        ->and($b->getKey())->toBe($a->getKey())
        ->and($c->getKey())->toBe($a->getKey())
        ->and($a->pengunjung)->toBeNull()
        ->and(SimulasiJalan::query()->masihAda()->count())->toBe(1);
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

it('salinan bersama tidak dibuang karena tak ada aktivitas dan tidak pernah masuk kolam', function () {
    config()->set('simulasi.umur_menit', 1);
    $service = app(SimulasiService::class);
    $bersama = $service->contohTerisi();
    $bersama->forceFill(['mulai_pada' => now()->subDays(3), 'terakhir_aktif_pada' => now()->subDays(3)])->save();

    expect($service->bersihkanKedaluwarsa())->toBe(0)
        ->and($bersama->fresh()->status)->toBe(SimulasiJalan::STATUS_SELESAI)
        ->and(SimulasiJalan::query()->siap()->count())->toBe(0);
});

it('satu pengunjung memegang contoh kosong miliknya sendiri, dan contoh terisi bersama tidak menimpanya', function () {
    $service = app(SimulasiService::class);
    $hash = SimulasiService::hashPengunjung(str_repeat('p', 40));

    $terisi = $service->klaim($hash, 'terisi');
    $kosong = $service->klaim($hash, 'kosong');

    expect($terisi->getKey())->not->toBe($kosong->getKey())
        ->and($terisi->bersama)->toBeTrue()
        ->and($kosong->mode)->toBe('kosong')
        ->and($kosong->pengunjung)->toBe($hash)
        ->and($service->klaim($hash, 'terisi')->getKey())->toBe($terisi->getKey())
        ->and($service->klaim($hash, 'kosong')->getKey())->toBe($kosong->getKey());
});

it('dua pengunjung mendapat dua contoh kosong yang berbeda', function () {
    $service = app(SimulasiService::class);

    $a = $service->klaim(SimulasiService::hashPengunjung(str_repeat('a', 40)), 'kosong');
    $b = $service->klaim(SimulasiService::hashPengunjung(str_repeat('b', 40)), 'kosong');

    expect($a->getKey())->not->toBe($b->getKey());
});

it('klaim contoh kosong mengambil dari kolam bila ada', function () {
    $service = app(SimulasiService::class);
    $kolam = $service->buat(mode: 'kosong')->jalan;

    $dapat = $service->klaim(SimulasiService::hashPengunjung(str_repeat('q', 40)), 'kosong');

    expect($dapat->getKey())->toBe($kolam->getKey())
        ->and(SimulasiJalan::query()->masihAda()->count())->toBe(1);
});

it('kolam hanya berisi contoh kosong dan sebanyak targetnya', function () {
    config()->set('simulasi.kolam_siap_kosong', 2);

    app(SimulasiService::class)->isiKolam();

    expect(SimulasiJalan::query()->siap('kosong')->count())->toBe(2)
        ->and(SimulasiJalan::query()->siap('terisi')->count())->toBe(0)
        ->and(SimulasiJalan::query()->bersama()->count())->toBe(0);
});

it('kolam yang targetnya nol tidak membangun apa pun', function () {
    config()->set('simulasi.kolam_siap_kosong', 0);

    expect(app(SimulasiService::class)->isiKolam())->toBe(0)
        ->and(SimulasiJalan::query()->count())->toBe(0);
});

it('dua contoh kosong berdampingan tidak bentrok kolom unik', function () {
    $service = app(SimulasiService::class);
    $a = $service->buat(mode: 'kosong')->jalan;
    $b = $service->buat(mode: 'kosong')->jalan;

    expect(isiSandbox($a)['akun'])->toBe(6)
        ->and(isiSandbox($b)['akun'])->toBe(6);
});

it('sandbox kosong dibongkar tuntas tanpa sisa', function () {
    $service = app(SimulasiService::class);
    $jalan = $service->buat(mode: 'kosong')->jalan;

    $service->hapus($jalan);

    expect($jalan->fresh()->status)->toBe(SimulasiJalan::STATUS_DIBONGKAR)
        ->and(DB::table('users')->where('username', 'like', 'sim-%')->count())->toBe(0)
        ->and(DB::table('academic_units')->where('code', 'like', 'SIM-%')->count())->toBe(0);
});

it('batas kapasitas berlaku untuk jumlah sandbox, bukan per jenis', function () {
    config()->set('simulasi.kolam_siap_kosong', 3);
    config()->set('simulasi.maks_sandbox', 2);

    expect(app(SimulasiService::class)->isiKolam())->toBe(2)
        ->and(SimulasiJalan::query()->masihAda()->count())->toBe(2);
});

it('sandbox kosong milik pengunjung yang akunnya tidak lengkap dibongkar dan diganti, bukan dipakai', function () {
    $service = app(SimulasiService::class);
    $hash = SimulasiService::hashPengunjung(str_repeat('r', 40));

    $lama = $service->klaim($hash, 'kosong');
    Ranah::sebagai((string) $lama->getKey(), fn () => User::query()
        ->where('username', AkunSimulasi::username('sim-timkur', $lama->kode()))->delete());

    expect($service->akunLengkap($lama))->toBeFalse();

    $baru = $service->klaim($hash, 'kosong');

    expect($baru->getKey())->not->toBe($lama->getKey())
        ->and($service->akunLengkap($baru))->toBeTrue()
        ->and($lama->fresh()->status)->toBe(SimulasiJalan::STATUS_DIBONGKAR);
});

it('sandbox kolam yang akunnya tidak lengkap dilewati dan tidak sampai ke pengunjung', function () {
    $service = app(SimulasiService::class);
    $rusak = $service->buat(mode: 'kosong')->jalan;
    Ranah::sebagai((string) $rusak->getKey(), fn () => User::query()
        ->where('username', AkunSimulasi::username('sim-auditor', $rusak->kode()))->delete());
    $sehat = $service->buat(mode: 'kosong')->jalan;

    $dapat = $service->klaim(SimulasiService::hashPengunjung(str_repeat('s', 40)), 'kosong');

    expect($dapat->getKey())->toBe($sehat->getKey())
        ->and($rusak->fresh()->status)->toBe(SimulasiJalan::STATUS_DIBONGKAR);
});

// ── Pemaksaan di sisi server (tombol Coba) ───────────────────────────────

it('Pimpinan dan Auditor tidak punya pilihan mode', function (string $slug) {
    expect(PeranPanduan::punyaPilihanMode($slug))->toBeFalse()
        ->and(PeranPanduan::modeUntuk($slug, 'kosong'))->toBe('terisi');
})->with(['pimpinan', 'auditor-mutu']);

it('peran pengisi bebas memilih mode', function (string $slug) {
    expect(PeranPanduan::punyaPilihanMode($slug))->toBeTrue()
        ->and(PeranPanduan::modeUntuk($slug, 'kosong'))->toBe('kosong')
        ->and(PeranPanduan::modeUntuk($slug, 'terisi'))->toBe('terisi')
        ->and(PeranPanduan::modeUntuk($slug, 'ngawur'))->toBe('terisi');
})->with(['admin-unit', 'tim-kurikulum', 'koordinator-mk', 'dosen-pengampu']);

it('permintaan POST memaksa Pimpinan ke contoh terisi bersama walau meminta kosong', function () {
    $this->withCookie('silogy_pengunjung', str_repeat('x', 40))
        ->post(route('panduan.coba', ['peran' => 'pimpinan']), ['level' => 'prodi', 'mode' => 'kosong'])
        ->assertRedirect();

    $jalan = SimulasiJalan::query()->masihAda()->get();

    expect($jalan)->toHaveCount(1)
        ->and($jalan->first()->mode)->toBe('terisi')
        ->and($jalan->first()->bersama)->toBeTrue()
        ->and($jalan->first()->pengunjung)->toBeNull();
});

it('permintaan POST Tim Kurikulum dengan mode kosong membuat contoh kosong milik pengunjung itu', function () {
    $this->withCookie('silogy_pengunjung', str_repeat('x', 40))
        ->post(route('panduan.coba', ['peran' => 'tim-kurikulum']), ['level' => 'prodi', 'mode' => 'kosong'])
        ->assertRedirect();

    $jalan = SimulasiJalan::query()->whereNotNull('pengunjung')->get();

    expect($jalan)->toHaveCount(1)
        ->and($jalan->first()->mode)->toBe('kosong')
        ->and(isiSandbox($jalan->first())['cpl'])->toBe(0);
});

it('tanpa parameter mode, tombol lama tetap menghasilkan contoh terisi bersama', function () {
    $this->withCookie('silogy_pengunjung', str_repeat('x', 40))
        ->post(route('panduan.coba', ['peran' => 'tim-kurikulum']), ['level' => 'prodi'])
        ->assertRedirect();

    expect(SimulasiJalan::query()->masihAda()->value('mode'))->toBe('terisi')
        ->and(SimulasiJalan::query()->masihAda()->value('bersama'))->toBeTrue();
});

it('dua mode dari pengunjung yang sama menghasilkan satu contoh bersama dan satu contoh kosong pribadi', function () {
    foreach (['terisi', 'kosong'] as $mode) {
        $this->withCookie('silogy_pengunjung', str_repeat('x', 40))
            ->post(route('panduan.coba', ['peran' => 'tim-kurikulum']), ['level' => 'prodi', 'mode' => $mode])
            ->assertRedirect();
    }

    expect(SimulasiJalan::query()->masihAda()->get()->map(fn ($j) => $j->mode.':'.($j->bersama ? 'bersama' : 'pribadi'))->sort()->values()->all())
        ->toBe(['kosong:pribadi', 'terisi:bersama']);
});

it('simulasi di tingkat Universitas dan Fakultas ditolak dengan pesan, tanpa membangun apa pun', function (string $level) {
    $this->from('/panduan')
        ->post(route('panduan.coba', ['peran' => 'tim-kurikulum']), ['level' => $level, 'mode' => 'kosong'])
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

it('halaman peran menampilkan dua tombol untuk peran pengisi dan satu untuk peran baca di tingkat prodi', function () {
    $this->get(route('panduan.peran', ['peran' => 'tim-kurikulum']))
        ->assertSee('Lihat contoh terisi')
        ->assertSee('Coba mengisi sendiri');

    $this->get(route('panduan.peran', ['peran' => 'pimpinan']))
        ->assertSee('Lihat contoh terisi')
        ->assertDontSee('Coba mengisi sendiri');
});

it('halaman peran di tingkat Universitas dan Fakultas tetap memuat panduan tanpa tombol coba', function (string $level) {
    $this->get(route('panduan.peran', ['peran' => 'tim-kurikulum', 'level' => $level]))
        ->assertOk()
        ->assertSee('difokuskan pada tingkat')
        ->assertDontSee('Coba mengisi sendiri')
        ->assertDontSee('Lihat contoh terisi');
})->with(['univ', 'fak']);

it('halaman level prodi menampilkan pilihan sesuai jenis peran', function () {
    $html = $this->get(route('panduan.level', ['level' => 'prodi']))->getContent();

    expect(substr_count($html, 'Coba mengisi sendiri'))->toBe(4)
        ->and(substr_count($html, 'Lihat contoh terisi'))->toBe(6);
});

it('halaman level universitas dan fakultas tetap menampilkan panduan tetapi tanpa tombol coba', function (string $level) {
    $html = $this->get(route('panduan.level', ['level' => $level]))->assertOk()->getContent();

    expect(substr_count($html, 'Baca panduan'))->toBe(6)
        ->and($html)->not->toContain('Coba mengisi sendiri')
        ->and($html)->not->toContain('Lihat contoh terisi')
        ->and($html)->toContain('Simulasi di tingkat Program Studi');
})->with(['univ', 'fak']);

// ── Isolasi ──────────────────────────────────────────────────────────────

it('contoh kosong tetap terisolasi dari data inti dan dari sandbox lain', function () {
    $service = app(SimulasiService::class);
    $a = $service->buat(mode: 'kosong')->jalan;
    $b = $service->buat(mode: 'terisi', jumlahMk: 1)->jalan;

    $inti = User::query()->where('username', 'adminprodi')->firstOrFail();
    $this->actingAs($inti);

    expect(User::query()->where('username', 'like', 'sim-%')->count())->toBe(0)
        ->and(isiSandbox($a)['akun'])->toBe(6)
        ->and(Ranah::sebagai((string) $a->getKey(), fn () => Cpl::query()->count()))->toBe(0)
        ->and(Ranah::sebagai((string) $b->getKey(), fn () => Cpl::query()->count()))->toBeGreaterThan(0);
});

it('di contoh kosong Tim Kurikulum benar-benar dapat menambah kurikulum, dan hasilnya tetap di sandbox', function () {
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

    // Dari sisi data inti, kurikulum latihan itu tidak ada.
    auth()->logout();
    $this->actingAs(User::query()->where('username', 'timkur')->firstOrFail());

    expect(Kurikulum::query()->where('nama', 'Kurikulum Latihan Saya')->count())->toBe(0);
});
