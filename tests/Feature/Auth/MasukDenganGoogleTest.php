<?php

use App\Models\User;
use App\Modules\Auth\Support\MasukDenganGoogle;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);

    config([
        'services.google.aktif' => true,
        'services.google.client_id' => 'id-uji',
        'services.google.client_secret' => 'rahasia-uji',
        'services.google.domain' => 'unsil.ac.id',
        'services.sintesys.endpoint' => 'https://sintesys.test/api',
        'services.sintesys.token' => 'token-uji',
    ]);

    Semester::query()->create([
        'kode' => '20251',
        'nama' => 'Ganjil 2025/2026',
        'tahun_mulai' => 2025,
        'tahun_selesai' => 2026,
        'jenis' => 'ganjil',
        'status_aktif' => true,
    ]);

    Cache::flush();
});

/**
 * Pura-pura Google mengembalikan pengguna ini (tanpa jaringan).
 *
 * @param  array<string, mixed>  $raw
 */
function googleMengembalikan(string $email, array $raw = [], string $id = 'g-1001', string $nama = 'Dosen Uji'): void
{
    $pengguna = (new SocialiteUser)->map(['id' => $id, 'name' => $nama, 'email' => $email]);
    $pengguna->setRaw(array_merge(['email_verified' => true, 'hd' => 'unsil.ac.id'], $raw));

    $penyedia = Mockery::mock(Provider::class);
    $penyedia->shouldReceive('user')->andReturn($pengguna);

    Socialite::shouldReceive('driver')->with('google')->andReturn($penyedia);
}

function sintesysMengampu(array $kelas): void
{
    Http::fake(['sintesys.test/*' => Http::response(['data' => $kelas])]);
}

it('rute Google mati (404) bila belum diaktifkan', function () {
    config(['services.google.aktif' => false]);

    $this->get(route('auth.google.redirect'))->assertNotFound();
    $this->get(route('auth.google.callback'))->assertNotFound();
});

it('rute Google mati (404) bila klien belum terisi', function () {
    config(['services.google.client_secret' => null]);

    $this->get(route('auth.google.redirect'))->assertNotFound();
});

it('tombol Google tampil di halaman login hanya saat aktif', function () {
    $this->get('/login')->assertOk()->assertSee('Masuk dengan akun Google Unsil');

    config(['services.google.aktif' => false]);

    $this->get('/login')->assertOk()->assertDontSee('Masuk dengan akun Google Unsil');
});

it('mengalihkan ke Google dengan petunjuk domain', function () {
    $response = $this->get(route('auth.google.redirect'));

    $response->assertRedirectContains('accounts.google.com');
    expect($response->headers->get('Location'))
        ->toContain('hd=unsil.ac.id')
        ->toContain('client_id=id-uji');
});

it('dosen baru yang terbukti mengampu kelas di Sintesys langsung masuk dengan peran Dosen Pengampu', function () {
    sintesysMengampu([['kode_mk' => 'MK1', 'kelas' => 'A', 'kode_prodi' => '2151', 'peserta' => []]]);
    googleMengembalikan('dosen.baru@unsil.ac.id');

    $this->get(route('auth.google.callback'))->assertRedirect();

    $user = User::query()->where('email', 'dosen.baru@unsil.ac.id')->firstOrFail();

    expect(auth()->id())->toBe($user->id)
        ->and($user->google_id)->toBe('g-1001')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->hasRole('Dosen Pengampu'))->toBeTrue()
        ->and($user->academicUnits()->where('code', '2151')->exists())->toBeTrue();
});

it('akun baru yang tidak mengampu kelas dibuat tanpa peran dan tidak boleh masuk', function () {
    sintesysMengampu([]);
    googleMengembalikan('bukan.dosen@unsil.ac.id');

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('filament.admin.auth.login'))
        ->assertSessionHas('google_error');

    $user = User::query()->where('email', 'bukan.dosen@unsil.ac.id')->firstOrFail();

    expect(auth()->check())->toBeFalse()
        ->and($user->google_id)->toBe('g-1001')
        ->and($user->roles()->exists())->toBeFalse();
});

it('gagal tertutup: Sintesys error tidak memberi peran dan tidak membuat galat', function () {
    Http::fake(['sintesys.test/*' => Http::response('rusak', 500)]);
    googleMengembalikan('dosen.error@unsil.ac.id');

    $this->get(route('auth.google.callback'))->assertSessionHas('google_error');

    $user = User::query()->where('email', 'dosen.error@unsil.ac.id')->firstOrFail();

    expect(auth()->check())->toBeFalse()
        ->and($user->roles()->exists())->toBeFalse();
});

it('gagal tertutup: Sintesys belum dikonfigurasi tidak memberi peran', function () {
    config(['services.sintesys.token' => null]);
    googleMengembalikan('dosen.tanpa.token@unsil.ac.id');

    $this->get(route('auth.google.callback'))->assertSessionHas('google_error');

    expect(User::query()->where('email', 'dosen.tanpa.token@unsil.ac.id')->firstOrFail()->roles()->exists())->toBeFalse();
});

it('akun lama ber-peran tertaut lewat surel dan langsung masuk tanpa memanggil Sintesys', function () {
    Http::fake();
    $user = User::factory()->create(['email' => 'lama@unsil.ac.id']);
    $user->assignRole('Admin');
    googleMengembalikan('LAMA@unsil.ac.id');

    $this->get(route('auth.google.callback'))->assertRedirect();

    expect(auth()->id())->toBe($user->id)
        ->and($user->fresh()->google_id)->toBe('g-1001');
    Http::assertNothingSent();
});

it('akun yang sudah tertaut ke Google lain ditolak', function () {
    $user = User::factory()->create(['email' => 'tertaut@unsil.ac.id']);
    $user->assignRole('Admin');
    $user->forceFill(['google_id' => 'g-lain'])->save();
    googleMengembalikan('tertaut@unsil.ac.id', id: 'g-baru');

    $this->get(route('auth.google.callback'))->assertSessionHas('google_error');

    expect(auth()->check())->toBeFalse()
        ->and($user->fresh()->google_id)->toBe('g-lain');
});

it('akun Google tertaut dikenali walau surelnya berubah', function () {
    $user = User::factory()->create(['email' => 'nama.lama@unsil.ac.id']);
    $user->assignRole('Admin');
    $user->forceFill(['google_id' => 'g-1001'])->save();
    googleMengembalikan('nama.baru@unsil.ac.id');

    $this->get(route('auth.google.callback'))->assertRedirect();

    expect(auth()->id())->toBe($user->id);
});

it('menolak surel di luar domain kampus', function () {
    googleMengembalikan('orang@gmail.com', ['hd' => null]);

    $this->get(route('auth.google.callback'))->assertSessionHas('google_error');

    expect(auth()->check())->toBeFalse()
        ->and(User::query()->where('email', 'orang@gmail.com')->exists())->toBeFalse();
});

it('menolak klaim hd yang bukan domain kampus walau surelnya mirip', function () {
    googleMengembalikan('orang@unsil.ac.id', ['hd' => 'lain.example']);

    $this->get(route('auth.google.callback'))->assertSessionHas('google_error');

    expect(auth()->check())->toBeFalse();
});

it('menolak surel Google yang belum terverifikasi', function () {
    googleMengembalikan('belum@unsil.ac.id', ['email_verified' => false]);

    $this->get(route('auth.google.callback'))->assertSessionHas('google_error');

    expect(auth()->check())->toBeFalse()
        ->and(User::query()->where('email', 'belum@unsil.ac.id')->exists())->toBeFalse();
});

it('pembatalan di layar Google kembali ke login dengan pesan', function () {
    $this->get(route('auth.google.callback', ['error' => 'access_denied']))
        ->assertRedirect(route('filament.admin.auth.login'))
        ->assertSessionHas('google_error');
});

it('akun tanpa peran yang kemudian terbukti dosen mendapat peran pada login berikutnya', function () {
    // Panggilan pertama Sintesys: belum ada kelas; panggilan kedua: sudah ada.
    Http::fake(['sintesys.test/*' => Http::sequence()
        ->push(['data' => []])
        ->push(['data' => [['kode_mk' => 'MK2', 'kelas' => 'B', 'kode_prodi' => '2151', 'peserta' => []]]]),
    ]);
    googleMengembalikan('menyusul@unsil.ac.id');

    $this->get(route('auth.google.callback'))->assertSessionHas('google_error');
    expect(auth()->check())->toBeFalse();

    Cache::flush();
    $this->get(route('auth.google.callback'))->assertRedirect();

    expect(auth()->check())->toBeTrue()
        ->and(auth()->user()->hasRole('Dosen Pengampu'))->toBeTrue();
});

it('hanya satu peran yang diberikan otomatis dan tidak pernah peran istimewa', function () {
    sintesysMengampu([['kode_mk' => 'MK1', 'kelas' => 'A', 'kode_prodi' => '2151', 'peserta' => []]]);
    googleMengembalikan('aman@unsil.ac.id');
    $this->get(route('auth.google.callback'));

    $peran = User::query()->where('email', 'aman@unsil.ac.id')->firstOrFail()->getRoleNames()->all();

    expect($peran)->toBe([MasukDenganGoogle::PERAN_DOSEN]);
});

it('unit prodi yang tidak dikenal diabaikan tanpa galat', function () {
    sintesysMengampu([['kode_mk' => 'MK1', 'kelas' => 'A', 'kode_prodi' => '9999', 'peserta' => []]]);
    googleMengembalikan('prodi.asing@unsil.ac.id');

    $this->get(route('auth.google.callback'))->assertRedirect();

    $user = User::query()->where('email', 'prodi.asing@unsil.ac.id')->firstOrFail();

    expect($user->hasRole('Dosen Pengampu'))->toBeTrue()
        ->and(AcademicUnit::query()->where('code', '9999')->exists())->toBeFalse()
        ->and($user->academicUnits()->count())->toBe(0);
});
