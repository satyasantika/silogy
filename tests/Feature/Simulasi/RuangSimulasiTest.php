<?php

use App\Models\User;
use App\Modules\Simulasi\Models\SimulasiJalan;
use App\Modules\Simulasi\Models\SimulasiPeranTerisi;
use App\Modules\Simulasi\Services\RuangSimulasi;
use App\Modules\Simulasi\Services\SimulasiService;
use App\Modules\Simulasi\Support\AkunSimulasi;
use App\Modules\Simulasi\Support\HanyaBaca;
use App\Modules\Simulasi\Support\PengaturanSimulasi;
use App\Modules\Simulasi\Support\Ranah;
use App\Modules\Simulasi\Support\SesiTab;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

const PERAN_RUANG = ['admin-unit', 'tim-kurikulum', 'koordinator-mk', 'dosen-pengampu', 'pimpinan', 'auditor-mutu'];

beforeEach(function () {
    Cache::flush();
    RateLimiter::clear('ruang-token-salah:127.0.0.1');
    config()->set('simulasi.izinkan_coba_peran', true);

    (new AcademicUnitSeeder)->run();
    (new RolePermissionSeeder)->run();
    (new SemesterSeeder)->run();
    (new EvaluasiSeeder)->run();

    app(SimulasiService::class)->aturCobaPeran(true);
});

function ruangBaru(): SimulasiJalan
{
    return app(SimulasiService::class)->buat(mode: 'kosong')->jalan;
}

/** Memilih peran lewat HTTP; mengembalikan token tab dari tujuan pengalihan. */
function pilihPeran(SimulasiJalan $ruang, string $peran): ?string
{
    $respons = test()->post(route('simulasi.ruang.masuk', ['pin' => $ruang->pin, 'peran' => $peran]));
    $respons->assertRedirect();

    return preg_match('#/s/([a-z0-9]{24})/simulasi/masuk#', (string) $respons->headers->get('Location'), $m) === 1 ? $m[1] : null;
}

function terisiDi(SimulasiJalan $ruang): array
{
    return collect(app(RuangSimulasi::class)->daftarPeran($ruang))->filter(fn ($p) => $p['terisi'])->keys()->sort()->values()->all();
}

// ── Masuk dengan token ───────────────────────────────────────────────────

it('menampilkan form token di /ruang dan menerima token tanpa membedakan huruf besar kecil atau pemisah', function () {
    $ruang = ruangBaru();

    $this->get(route('simulasi.ruang'))->assertOk()->assertSee('Token ruang');

    foreach ([$ruang->pin, strtolower($ruang->pin), substr($ruang->pin, 0, 3).'-'.substr($ruang->pin, 3), ' '.$ruang->pin.' '] as $isian) {
        $this->post(route('simulasi.ruang.periksa'), ['pin' => $isian])
            ->assertRedirect(route('simulasi.ruang.tampil', ['pin' => $ruang->pin]));
    }
});

it('menolak token yang tidak ada dengan pesan, bukan galat', function () {
    ruangBaru();

    $this->from(route('simulasi.ruang'))
        ->post(route('simulasi.ruang.periksa'), ['pin' => 'ZZZZZZ'])
        ->assertRedirect(route('simulasi.ruang'))
        ->assertSessionHas('panduan_galat', fn ($p) => str_contains((string) $p, 'Token ruang tidak ditemukan'));
});

it('token milik contoh terisi bersama atau ruang yang sudah dibongkar tidak bisa dipakai', function () {
    $service = app(SimulasiService::class);
    $ruang = ruangBaru();
    $pin = $ruang->pin;
    $service->hapus($ruang);

    $this->post(route('simulasi.ruang.periksa'), ['pin' => $pin])
        ->assertSessionHas('panduan_galat');

    expect(app(RuangSimulasi::class)->cari($service->contohTerisi()->pin ?? 'XXXXXX'))->toBeNull();
});

it('membatasi tebakan token yang salah per IP, tanpa menghitung token yang benar', function () {
    PengaturanSimulasi::atur('batas_token_salah', 5);
    $ruang = ruangBaru();

    for ($i = 0; $i < 20; $i++) {
        $this->post(route('simulasi.ruang.periksa'), ['pin' => $ruang->pin])->assertRedirect(route('simulasi.ruang.tampil', ['pin' => $ruang->pin]));
    }

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('simulasi.ruang.periksa'), ['pin' => 'ZZZZZZ']);
    }

    $this->post(route('simulasi.ruang.periksa'), ['pin' => 'ZZZZZZ'])->assertStatus(429);
    $this->post(route('simulasi.ruang.periksa'), ['pin' => $ruang->pin])->assertStatus(429);
});

it('halaman ruang dan pemilihan peran tertutup rapat (404) saat mode latihan ditutup', function () {
    $ruang = ruangBaru();
    app(SimulasiService::class)->aturCobaPeran(false);

    $this->get(route('simulasi.ruang'))->assertNotFound();
    $this->post(route('simulasi.ruang.periksa'), ['pin' => $ruang->pin])->assertNotFound();
    $this->get(route('simulasi.ruang.tampil', ['pin' => $ruang->pin]))->assertNotFound();
    $this->post(route('simulasi.ruang.masuk', ['pin' => $ruang->pin, 'peran' => 'dosen-pengampu']))->assertNotFound();
});

// ── Satu peserta per peran ───────────────────────────────────────────────

it('halaman ruang menampilkan enam peran, dan peran yang terisi dinonaktifkan', function () {
    $ruang = ruangBaru();

    $kosong = $this->get(route('simulasi.ruang.tampil', ['pin' => $ruang->pin]))->assertOk()->getContent();
    expect(substr_count($kosong, 'aria-disabled="true"'))->toBe(0)
        ->and(substr_count($kosong, 'data-masuk'))->toBe(6);

    pilihPeran($ruang, 'dosen-pengampu');

    $html = $this->get(route('simulasi.ruang.tampil', ['pin' => $ruang->pin]))->assertOk()->getContent();

    // Tepat satu tombol mati ("Sudah terisi") dan lima tombol masuk; yang mati milik Dosen Pengampu.
    expect(substr_count($html, 'aria-disabled="true"'))->toBe(1)
        ->and(substr_count($html, 'data-masuk'))->toBe(5)
        ->and($html)->toContain('data-peran="dosen-pengampu" data-terisi="1"')
        ->and($html)->toContain('Sudah terisi')
        ->and(terisiDi($ruang))->toBe(['dosen-pengampu']);
});

it('peran yang sudah dipegang tidak bisa dipilih peserta kedua, tetapi peran lain tetap bisa', function () {
    $ruang = ruangBaru();

    $pertama = pilihPeran($ruang, 'tim-kurikulum');
    expect($pertama)->not->toBeNull();

    $this->post(route('simulasi.ruang.masuk', ['pin' => $ruang->pin, 'peran' => 'tim-kurikulum']))
        ->assertRedirect(route('simulasi.ruang.tampil', ['pin' => $ruang->pin]))
        ->assertSessionHas('panduan_galat', fn ($p) => str_contains((string) $p, 'diambil peserta lain'));

    $lain = pilihPeran($ruang, 'dosen-pengampu');

    expect($lain)->not->toBeNull()->not->toBe($pertama)
        ->and(terisiDi($ruang))->toBe(['dosen-pengampu', 'tim-kurikulum']);
});

it('keenam peran bisa diisi enam peserta berbeda, dan peserta ketujuh tak mendapat satu pun', function () {
    $ruang = ruangBaru();

    foreach (PERAN_RUANG as $peran) {
        expect(pilihPeran($ruang, $peran))->not->toBeNull();
    }

    expect(terisiDi($ruang))->toHaveCount(6);

    foreach (PERAN_RUANG as $peran) {
        $this->post(route('simulasi.ruang.masuk', ['pin' => $ruang->pin, 'peran' => $peran]))->assertSessionHas('panduan_galat');
    }

    expect(SimulasiPeranTerisi::query()->count())->toBe(6);
});

it('kunci unik di basis data menjadi pengaman terakhir bila dua peserta mengambil peran yang sama serentak', function () {
    $ruang = ruangBaru();

    SimulasiPeranTerisi::query()->create([
        'simulasi_jalan_id' => $ruang->getKey(), 'peran' => 'pimpinan', 'tab_token' => 'a', 'terakhir_aktif_pada' => now(),
    ]);

    expect(fn () => SimulasiPeranTerisi::query()->create([
        'simulasi_jalan_id' => $ruang->getKey(), 'peran' => 'pimpinan', 'tab_token' => 'b', 'terakhir_aktif_pada' => now(),
    ]))->toThrow(UniqueConstraintViolationException::class);

    // Lewat layanan: penawar kedua kalah, pemegang pertama tetap utuh.
    expect(app(RuangSimulasi::class)->ambilPeran($ruang, 'pimpinan', 'c'))->toBeFalse()
        ->and(SimulasiPeranTerisi::query()->where('peran', 'pimpinan')->value('tab_token'))->toBe('a');
});

it('peran yang sama di dua ruang berbeda tidak saling mengunci', function () {
    $a = ruangBaru();
    $b = ruangBaru();

    expect(pilihPeran($a, 'koordinator-mk'))->not->toBeNull()
        ->and(pilihPeran($b, 'koordinator-mk'))->not->toBeNull()
        ->and(terisiDi($a))->toBe(['koordinator-mk'])
        ->and(terisiDi($b))->toBe(['koordinator-mk']);
});

it('endpoint status melaporkan peran terisi untuk penyegaran otomatis', function () {
    $ruang = ruangBaru();
    pilihPeran($ruang, 'admin-unit');

    $json = $this->getJson(route('simulasi.ruang.status', ['pin' => $ruang->pin]))->assertOk()->json('terisi');

    expect($json['admin-unit'])->toBeTrue()
        ->and($json['dosen-pengampu'])->toBeFalse();
});

it('peran yang tak dikenal atau di luar enam peran ditolak 404 dan tidak mencatat apa pun', function () {
    $ruang = ruangBaru();

    $this->post(route('simulasi.ruang.masuk', ['pin' => $ruang->pin, 'peran' => 'super-admin']))->assertNotFound();
    $this->post(route('simulasi.ruang.masuk', ['pin' => $ruang->pin, 'peran' => 'ngawur']))->assertNotFound();

    expect(SimulasiPeranTerisi::query()->count())->toBe(0);
});

// ── Masuk ke akun yang benar, keluar melepas peran ───────────────────────

it('tiap peran mendarat di akun ruang itu sendiri, bukan akun inti maupun akun ruang lain', function () {
    $ruang = ruangBaru();
    $lain = ruangBaru();

    $diharapkan = [
        'admin-unit' => 'sim-adminprodi', 'tim-kurikulum' => 'sim-timkur', 'koordinator-mk' => 'sim-korma',
        'dosen-pengampu' => 'sim-dosen', 'pimpinan' => 'sim-kaprodi', 'auditor-mutu' => 'sim-auditor',
    ];

    foreach ($diharapkan as $slug => $kunci) {
        $token = pilihPeran($ruang, $slug);

        URL::forceRootUrl(null);
        URL::useAssetOrigin(null);
        config(['session.cookie' => 'laravel_session', 'session.path' => '/']);
        app('session')->forgetDrivers();
        auth()->logout();

        $this->get('http://localhost/s/'.$token.'/simulasi/masuk')->assertRedirect();

        expect(auth()->user()->username)->toBe(AkunSimulasi::username($kunci, $ruang->kode()))
            ->and(auth()->user()->username)->not->toBe(AkunSimulasi::username($kunci, $lain->kode()))
            ->and(auth()->user()->sandbox_id)->toBe($ruang->id);
        auth()->logout();
    }
});

it('menutup tab tanpa menekan Keluar tidak melepas peran, tetapi sewa yang habis membuka peran itu lagi', function () {
    $ruang = ruangBaru();
    $tabLama = pilihPeran($ruang, 'dosen-pengampu');

    expect(terisiDi($ruang))->toBe(['dosen-pengampu']);

    SimulasiPeranTerisi::query()->update(['terakhir_aktif_pada' => now()->subMinutes(PengaturanSimulasi::ambil('sewa_menit') + 1)]);

    expect(terisiDi($ruang))->toBe([]);

    $tabBaru = pilihPeran($ruang, 'dosen-pengampu');

    expect($tabBaru)->not->toBe($tabLama)
        ->and(terisiDi($ruang))->toBe(['dosen-pengampu'])
        ->and(app(RuangSimulasi::class)->masihPemegang($tabLama))->toBeFalse()
        ->and(app(RuangSimulasi::class)->masihPemegang($tabBaru))->toBeTrue();
});

it('tab yang perannya sudah direbut mendapat 404, dan tab pemegang baru tetap hidup', function () {
    $ruang = ruangBaru();
    $lama = pilihPeran($ruang, 'dosen-pengampu');
    SimulasiPeranTerisi::query()->update(['terakhir_aktif_pada' => now()->subHour()]);
    $baru = pilihPeran($ruang, 'dosen-pengampu');

    URL::forceRootUrl(null);
    URL::useAssetOrigin(null);
    $this->get('http://localhost/s/'.$lama.'/simulasi/masuk')->assertNotFound();

    URL::forceRootUrl(null);
    URL::useAssetOrigin(null);
    $this->get('http://localhost/s/'.$baru.'/simulasi/masuk')->assertRedirect();
});

it('keluar dari peran melepasnya, menghapus catatan tab, dan mengembalikan ke halaman ruang', function () {
    $ruang = ruangBaru();
    $token = pilihPeran($ruang, 'tim-kurikulum');

    expect(terisiDi($ruang))->toBe(['tim-kurikulum']);

    $kembali = null;

    // Peniruan Keluar: pada tab ruang, KeluarAction memanggil lepasTabSaatIni().
    request()->attributes->set('sandbox_tab', ['ruang' => true, 'token' => $token, 'jalan' => (string) $ruang->getKey()]);
    $kembali = app(RuangSimulasi::class)->lepasTabSaatIni();

    expect($kembali)->toContain('/ruang/'.$ruang->pin)
        ->and($kembali)->not->toContain('/s/')
        ->and(terisiDi($ruang))->toBe([])
        ->and(SesiTab::cari($token))->toBeNull();

    // Tombol peran itu kembali bisa dipilih.
    $html = $this->get(route('simulasi.ruang.tampil', ['pin' => $ruang->pin]))->getContent();
    expect(substr_count($html, 'aria-disabled="true"'))->toBe(0)
        ->and(substr_count($html, 'data-masuk'))->toBe(6);

    expect(pilihPeran($ruang, 'tim-kurikulum'))->not->toBeNull();
});

it('keluar pada tab bukan-ruang (contoh terisi) tidak melepas apa pun dan tidak mengubah tujuan', function () {
    $ruang = ruangBaru();
    pilihPeran($ruang, 'tim-kurikulum');

    request()->attributes->set('sandbox_tab', ['ruang' => false, 'token' => 'x', 'jalan' => (string) $ruang->getKey()]);

    expect(app(RuangSimulasi::class)->lepasTabSaatIni())->toBeNull()
        ->and(terisiDi($ruang))->toBe(['tim-kurikulum']);
});

// ── Super Admin ──────────────────────────────────────────────────────────

it('Super Admin dapat melepas satu peran, dan tab pemegangnya terputus', function () {
    $ruang = ruangBaru();
    $tab = pilihPeran($ruang, 'pimpinan');
    pilihPeran($ruang, 'dosen-pengampu');

    expect(app(RuangSimulasi::class)->lepasPeran((string) $ruang->getKey(), 'pimpinan'))->toBe(1)
        ->and(terisiDi($ruang))->toBe(['dosen-pengampu'])
        ->and(app(RuangSimulasi::class)->masihPemegang($tab))->toBeFalse();
});

it('ganti token membuat token lama tak berlaku untuk peserta baru, tetapi pemegang peran tidak terganggu', function () {
    $service = app(SimulasiService::class);
    $ruang = ruangBaru();
    $lama = $ruang->pin;
    $tab = pilihPeran($ruang, 'koordinator-mk');

    $baru = $service->gantiPin($ruang);

    expect($baru)->not->toBe($lama)
        ->and($ruang->fresh()->pin)->toBe($baru)
        ->and(app(RuangSimulasi::class)->cari($lama))->toBeNull()
        ->and(app(RuangSimulasi::class)->cari($baru)?->getKey())->toBe($ruang->getKey())
        ->and(app(RuangSimulasi::class)->masihPemegang($tab))->toBeTrue();
});

it('menghapus ruang melepas semua peran di dalamnya dan tidak menyentuh ruang lain', function () {
    $service = app(SimulasiService::class);
    $a = ruangBaru();
    $b = ruangBaru();
    pilihPeran($a, 'dosen-pengampu');
    pilihPeran($b, 'dosen-pengampu');

    $service->hapus($a);

    expect(SimulasiPeranTerisi::query()->where('simulasi_jalan_id', $a->getKey())->count())->toBe(0)
        ->and(terisiDi($b))->toBe(['dosen-pengampu']);
});

it('tabel ruang terisi hanya bisa ditulis lewat jalur sistem, bukan data akademik', function () {
    $ruang = ruangBaru();

    expect(HanyaBaca::TABEL_BOLEH)->toContain('simulasi_peran_terisi');
    expect(Ranah::sebagai((string) $ruang->getKey(), fn () => User::query()->count()))->toBe(6);
});

it('tab ruang tetap login pada permintaan berikutnya, bukan dilempar ke halaman login', function () {
    $ruang = ruangBaru();
    $token = pilihPeran($ruang, 'tim-kurikulum');
    $buka = function (string $path) use ($token) {
        URL::forceRootUrl(null);
        URL::useAssetOrigin(null);
        config(['session.cookie' => 'laravel_session', 'session.path' => '/']);
        app('session')->forgetDrivers();

        return test()->get('http://localhost/s/'.$token.$path);
    };

    $buka('/simulasi/masuk')->assertRedirect("http://localhost/s/$token/dashboard");

    // Beberapa permintaan beruntun, termasuk yang menyentuh sewa peran (sekali per menit).
    foreach (['/dashboard', '/dashboard'] as $path) {
        $respons = $buka($path);

        expect($respons->getStatusCode())->toBe(200, 'tab ruang kehilangan sesinya pada '.$path)
            ->and((string) $respons->headers->get('Location'))->not->toContain('/login');
    }
});

it('pemeriksaan sewa peran berjalan setelah cookie sesi tab dipasang', function () {
    $sumber = file_get_contents(base_path('app/Modules/Simulasi/Http/Middleware/SesiSandboxTab.php'));

    expect(strpos($sumber, 'masihPemegang('))->toBeGreaterThan(strpos($sumber, '$this->pisahkanSesi('));
});
