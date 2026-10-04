<?php

use App\Models\User;
use App\Modules\Auth\Support\ActiveRole;
use App\Modules\CPL\Models\Cpl;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Support\AcademicUnitTerpilih;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kurikulum\Filament\Resources\KurikulumResource\Pages\CreateKurikulum;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Models\Mk;
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
        'kurikulum' => Kurikulum::query()->count(),
        'cpl' => Cpl::query()->count(),
        'mk' => Mk::query()->count(),
        'kelas' => KelasMk::query()->count(),
        'nilai' => NilaiMahasiswa::query()->count(),
        'mahasiswa' => Mahasiswa::query()->count(),
        'akun' => User::query()->count(),
    ]);
}

it('contoh kosong hanya berisi unit, akun, dan mahasiswa — tanpa kurikulum sampai nilai', function () {
    $jalan = app(SimulasiService::class)->buat(mode: SimulasiJalan::MODE_KOSONG)->jalan;
    $isi = isiSandbox($jalan);

    expect($jalan->mode)->toBe('kosong')
        ->and($jalan->jumlah_mk)->toBe(0)
        ->and($isi['akun'])->toBe(18)
        ->and($isi['mahasiswa'])->toBeGreaterThan(0)
        ->and($isi['kurikulum'])->toBe(0)
        ->and($isi['cpl'])->toBe(0)
        ->and($isi['mk'])->toBe(0)
        ->and($isi['kelas'])->toBe(0)
        ->and($isi['nilai'])->toBe(0);
});

it('contoh terisi memuat kurikulum sampai nilai', function () {
    $jalan = app(SimulasiService::class)->buat(mode: SimulasiJalan::MODE_TERISI)->jalan;
    $isi = isiSandbox($jalan);

    expect($jalan->mode)->toBe('terisi')
        ->and($isi['kurikulum'])->toBeGreaterThan(0)
        ->and($isi['cpl'])->toBeGreaterThan(0)
        ->and($isi['mk'])->toBeGreaterThan(0)
        ->and($isi['kelas'])->toBeGreaterThan(0)
        ->and($isi['nilai'])->toBeGreaterThan(0);
});

it('Super Admin menentukan jumlah MK pada contoh terisi', function (int $jumlah) {
    $jalan = app(SimulasiService::class)->buat(jumlahMk: $jumlah)->jalan;

    $mkProdi = Ranah::sebagai((string) $jalan->getKey(), fn () => Mk::query()
        ->where('nama', 'not like', '%(Simulasi)%')->count());

    expect($jalan->jumlah_mk)->toBe($jumlah)
        ->and($mkProdi)->toBe($jumlah);
})->with([1, 2, 4]);

it('jumlah MK di luar batas dipaksa ke rentang 1 sampai 6', function () {
    $service = app(SimulasiService::class);

    expect($service->buat(jumlahMk: 99)->jalan->jumlah_mk)->toBe(6)
        ->and($service->buat(jumlahMk: 0)->jalan->jumlah_mk)->toBe(1);
});

it('mode yang tak dikenal jatuh ke contoh terisi', function () {
    $jalan = app(SimulasiService::class)->buat(mode: 'ngawur')->jalan;

    expect($jalan->mode)->toBe('terisi');
});

it('satu pengunjung memegang satu sandbox per mode dan keduanya tidak saling menimpa', function () {
    $service = app(SimulasiService::class);
    $hash = SimulasiService::hashPengunjung(str_repeat('p', 40));

    $terisi = $service->klaim($hash, 'terisi');
    $kosong = $service->klaim($hash, 'kosong');

    expect($terisi->getKey())->not->toBe($kosong->getKey())
        ->and($terisi->mode)->toBe('terisi')
        ->and($kosong->mode)->toBe('kosong')
        ->and($service->klaim($hash, 'terisi')->getKey())->toBe($terisi->getKey())
        ->and($service->klaim($hash, 'kosong')->getKey())->toBe($kosong->getKey());
});

it('klaim mengambil dari kolam yang bermode sama, bukan mode lain', function () {
    $service = app(SimulasiService::class);
    $kolamTerisi = $service->buat(mode: 'terisi')->jalan;
    $kolamKosong = $service->buat(mode: 'kosong')->jalan;

    $dapat = $service->klaim(SimulasiService::hashPengunjung(str_repeat('q', 40)), 'kosong');

    expect($dapat->getKey())->toBe($kolamKosong->getKey())
        ->and($kolamTerisi->fresh()->pengunjung)->toBeNull();
});

it('kolam diisi untuk kedua mode sesuai targetnya masing-masing', function () {
    config()->set('simulasi.kolam_siap', 1);
    config()->set('simulasi.kolam_siap_kosong', 2);

    app(SimulasiService::class)->isiKolam();

    expect(SimulasiJalan::query()->siap('terisi')->count())->toBe(1)
        ->and(SimulasiJalan::query()->siap('kosong')->count())->toBe(2);
});

it('kolam yang targetnya nol untuk satu mode tidak membangun mode itu', function () {
    config()->set('simulasi.kolam_siap', 0);
    config()->set('simulasi.kolam_siap_kosong', 1);

    app(SimulasiService::class)->isiKolam();

    expect(SimulasiJalan::query()->siap('terisi')->count())->toBe(0)
        ->and(SimulasiJalan::query()->siap('kosong')->count())->toBe(1);
});

it('dua sandbox kosong berdampingan tidak bentrok kolom unik', function () {
    $service = app(SimulasiService::class);
    $a = $service->buat(mode: 'kosong')->jalan;
    $b = $service->buat(mode: 'kosong')->jalan;

    expect(isiSandbox($a)['akun'])->toBe(18)
        ->and(isiSandbox($b)['akun'])->toBe(18);
});

it('sandbox kosong dibongkar tuntas tanpa sisa', function () {
    $service = app(SimulasiService::class);
    $jalan = $service->buat(mode: 'kosong')->jalan;

    $service->hapus($jalan);

    expect($jalan->fresh()->status)->toBe(SimulasiJalan::STATUS_DIBONGKAR)
        ->and(DB::table('users')->where('username', 'like', 'sim-%')->count())->toBe(0)
        ->and(DB::table('academic_units')->where('code', 'like', 'SIM-%')->count())->toBe(0);
});

it('sandbox lama tanpa kolom mode terbaca sebagai contoh terisi enam MK', function () {
    $jalan = SimulasiJalan::query()->create(['status' => 'selesai', 'mulai_pada' => now()]);

    expect($jalan->fresh()->mode)->toBe('terisi')
        ->and($jalan->fresh()->jumlah_mk)->toBe(6);
});

// ── Pemaksaan di sisi server ─────────────────────────────────────────────

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

it('permintaan POST memaksa Pimpinan ke contoh terisi walau meminta kosong', function () {
    $this->withCookie('silogy_pengunjung', str_repeat('x', 40))
        ->post(route('panduan.coba', ['peran' => 'pimpinan']), ['level' => 'prodi', 'mode' => 'kosong'])
        ->assertRedirect();

    $jalan = SimulasiJalan::query()->whereNotNull('pengunjung')->get();

    expect($jalan)->toHaveCount(1)
        ->and($jalan->first()->mode)->toBe('terisi');
});

it('permintaan POST Tim Kurikulum dengan mode kosong membuat sandbox kosong', function () {
    $this->withCookie('silogy_pengunjung', str_repeat('x', 40))
        ->post(route('panduan.coba', ['peran' => 'tim-kurikulum']), ['level' => 'prodi', 'mode' => 'kosong'])
        ->assertRedirect();

    $jalan = SimulasiJalan::query()->whereNotNull('pengunjung')->get();

    expect($jalan)->toHaveCount(1)
        ->and($jalan->first()->mode)->toBe('kosong')
        ->and(isiSandbox($jalan->first())['kurikulum'])->toBe(0);
});

it('tanpa parameter mode, tombol lama tetap menghasilkan contoh terisi', function () {
    $this->withCookie('silogy_pengunjung', str_repeat('x', 40))
        ->post(route('panduan.coba', ['peran' => 'tim-kurikulum']), ['level' => 'prodi'])
        ->assertRedirect();

    expect(SimulasiJalan::query()->whereNotNull('pengunjung')->value('mode'))->toBe('terisi');
});

it('dua mode dari pengunjung yang sama menghasilkan dua sandbox lewat tombol panduan', function () {
    foreach (['terisi', 'kosong'] as $mode) {
        $this->withCookie('silogy_pengunjung', str_repeat('x', 40))
            ->post(route('panduan.coba', ['peran' => 'tim-kurikulum']), ['level' => 'prodi', 'mode' => $mode])
            ->assertRedirect();
    }

    expect(SimulasiJalan::query()->whereNotNull('pengunjung')->pluck('mode')->sort()->values()->all())
        ->toBe(['kosong', 'terisi']);
});

it('halaman peran menampilkan dua tombol untuk peran pengisi dan satu untuk peran baca', function () {
    $this->get(route('panduan.peran', ['peran' => 'tim-kurikulum']))
        ->assertSee('Lihat contoh terisi')
        ->assertSee('Coba mengisi sendiri');

    $this->get(route('panduan.peran', ['peran' => 'pimpinan']))
        ->assertSee('Lihat contoh terisi')
        ->assertDontSee('Coba mengisi sendiri');
});

it('halaman level menampilkan pilihan sesuai jenis peran', function () {
    $html = $this->get(route('panduan.level', ['level' => 'prodi']))->getContent();

    // Empat peran pengisi × dua tombol, ditambah dua peran baca × satu tombol.
    expect(substr_count($html, 'Coba mengisi sendiri'))->toBe(4)
        ->and(substr_count($html, 'Lihat contoh terisi'))->toBe(6);
});

it('contoh kosong tetap terisolasi dari data inti dan dari sandbox lain', function () {
    $service = app(SimulasiService::class);
    $a = $service->buat(mode: 'kosong')->jalan;
    $b = $service->buat(mode: 'terisi')->jalan;

    $inti = User::query()->where('username', 'adminprodi')->firstOrFail();
    $this->actingAs($inti);

    expect(User::query()->where('username', 'like', 'sim-%')->count())->toBe(0)
        ->and(isiSandbox($a)['akun'])->toBe(18)
        ->and(Ranah::sebagai((string) $a->getKey(), fn () => Kurikulum::query()->count()))->toBe(0)
        ->and(Ranah::sebagai((string) $b->getKey(), fn () => Kurikulum::query()->count()))->toBeGreaterThan(0);
});

it('di contoh kosong Tim Kurikulum benar-benar dapat memulai dari kurikulum, dan hasilnya tetap di sandbox', function () {
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

it('batas kapasitas berlaku untuk jumlah sandbox semua mode, bukan per mode', function () {
    config()->set('simulasi.kolam_siap', 3);
    config()->set('simulasi.kolam_siap_kosong', 3);
    config()->set('simulasi.maks_sandbox', 2);

    expect(app(SimulasiService::class)->isiKolam())->toBe(2)
        ->and(SimulasiJalan::query()->masihAda()->count())->toBe(2);
});

it('sandbox milik pengunjung yang akunnya tidak lengkap dibongkar dan diganti, bukan dipakai', function () {
    $service = app(SimulasiService::class);
    $hash = SimulasiService::hashPengunjung(str_repeat('r', 40));

    $lama = $service->klaim($hash, 'terisi');
    Ranah::sebagai((string) $lama->getKey(), fn () => User::query()
        ->where('username', AkunSimulasi::username('sim-timkur', $lama->kode()))->delete());

    expect($service->akunLengkap($lama))->toBeFalse();

    $baru = $service->klaim($hash, 'terisi');

    expect($baru->getKey())->not->toBe($lama->getKey())
        ->and($service->akunLengkap($baru))->toBeTrue()
        ->and($lama->fresh()->status)->toBe(SimulasiJalan::STATUS_DIBONGKAR);
});

it('sandbox kolam yang akunnya tidak lengkap dilewati dan tidak sampai ke pengunjung', function () {
    $service = app(SimulasiService::class);
    $rusak = $service->buat(mode: 'terisi')->jalan;
    Ranah::sebagai((string) $rusak->getKey(), fn () => User::query()
        ->where('username', AkunSimulasi::username('sim-auditor', $rusak->kode()))->delete());
    $sehat = $service->buat(mode: 'terisi')->jalan;

    $dapat = $service->klaim(SimulasiService::hashPengunjung(str_repeat('s', 40)), 'terisi');

    expect($dapat->getKey())->toBe($sehat->getKey())
        ->and($rusak->fresh()->status)->toBe(SimulasiJalan::STATUS_DIBONGKAR);
});
