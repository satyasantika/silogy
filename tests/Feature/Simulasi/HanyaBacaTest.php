<?php

use App\Models\User;
use App\Modules\Auth\Support\ActiveRole;
use App\Modules\CPL\Filament\Resources\CplResource\Pages\ListCpls;
use App\Modules\CPL\Models\Cpl;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\MK\Models\Mk;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use App\Modules\Simulasi\Models\SimulasiJalan;
use App\Modules\Simulasi\Services\SimulasiService;
use App\Modules\Simulasi\Support\AkunSimulasi;
use App\Modules\Simulasi\Support\HanyaBaca;
use App\Modules\Simulasi\Support\Ranah;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('simulasi.izinkan_coba_peran', true);
    Cache::flush();

    (new AcademicUnitSeeder)->run();
    (new RolePermissionSeeder)->run();
    (new SemesterSeeder)->run();
    (new EvaluasiSeeder)->run();
});

/** Contoh terisi bersama (1 MK agar cepat) dan akun yang dipilih di dalamnya. */
function bersamaDenganAkun(string $dasar = 'sim-timkur', string $peran = 'Tim Kurikulum'): array
{
    $jalan = app(SimulasiService::class)->buat(mode: 'terisi', bersama: true)->jalan;
    $id = (string) $jalan->getKey();

    $user = Ranah::sebagai($id, fn () => User::query()
        ->where('username', AkunSimulasi::username($dasar, $jalan->kode()))->firstOrFail());

    test()->actingAs($user);
    ActiveRole::set($peran);

    return [$jalan, $user];
}

it('menolak UPDATE, INSERT, dan DELETE data akademik dari akun contoh bersama, dan datanya tidak berubah', function () {
    [$jalan] = bersamaDenganAkun();
    $id = (string) $jalan->getKey();
    $sebelum = Ranah::sebagai($id, fn () => [Cpl::query()->count(), Cpl::query()->value('deskripsi')]);

    $ubah = fn () => Cpl::query()->firstOrFail()->update(['deskripsi' => 'DIRETAS']);
    $hapus = fn () => Cpl::query()->firstOrFail()->delete();
    $tambah = fn () => DB::table('mk')->insert(['id' => (string) str()->uuid(), 'nama' => 'X']);

    expect($ubah)->toThrow(HttpException::class, 'hanya dapat dibaca')
        ->and($hapus)->toThrow(HttpException::class)
        ->and($tambah)->toThrow(HttpException::class);

    expect(Ranah::sebagai($id, fn () => [Cpl::query()->count(), Cpl::query()->value('deskripsi')]))->toBe($sebelum);
});

it('menolak penulisan massal dan DDL dari akun contoh bersama', function () {
    bersamaDenganAkun();

    expect(fn () => NilaiMahasiswa::query()->update(['nilai' => 0]))->toThrow(HttpException::class)
        ->and(fn () => NilaiMahasiswa::query()->delete())->toThrow(HttpException::class)
        ->and(fn () => DB::statement('drop table kurikulums'))->toThrow(HttpException::class)
        ->and(fn () => DB::statement('alter table mk add column x int'))->toThrow(HttpException::class);
});

it('tetap membolehkan membaca, dan menulis sesi dan cache yang bukan data akademik', function () {
    [$jalan] = bersamaDenganAkun();

    expect(Ranah::sebagai((string) $jalan->getKey(), fn () => Kurikulum::query()->count()))->toBe(1);

    DB::table('sessions')->insert(['id' => 'sesi-uji', 'payload' => 'x', 'last_activity' => time()]);
    DB::table('cache')->insert(['key' => 'k', 'value' => 'v', 'expiration' => time() + 60]);

    expect(DB::table('sessions')->where('id', 'sesi-uji')->exists())->toBeTrue();
});

it('Gate menolak kemampuan menulis tetapi tidak kemampuan melihat', function () {
    [, $user] = bersamaDenganAkun();

    foreach (['create', 'update', 'delete', 'inputNilai'] as $kemampuan) {
        expect(Gate::forUser($user)->allows($kemampuan, Mk::class))->toBeFalse("$kemampuan seharusnya ditolak");
    }

    expect(Gate::forUser($user)->allows('viewAny', Mk::class))->toBeTrue();
});

it('contoh kosong milik pengunjung TIDAK dikunci: penulisan tetap berjalan', function () {
    $jalan = app(SimulasiService::class)->buat(mode: 'kosong')->jalan;
    $id = (string) $jalan->getKey();
    $user = Ranah::sebagai($id, fn () => User::query()
        ->where('username', AkunSimulasi::username('sim-timkur', $jalan->kode()))->firstOrFail());
    $this->actingAs($user);

    expect(HanyaBaca::aktif($user))->toBeFalse();

    Ranah::sebagai($id, fn () => Kurikulum::query()->firstOrFail()->update(['nama' => 'Boleh Diubah']));

    expect(Ranah::sebagai($id, fn () => Kurikulum::query()->value('nama')))->toBe('Boleh Diubah');
});

it('akun data inti tidak terkena pagar hanya-baca', function () {
    $this->actingAs(User::query()->where('username', 'adminprodi')->firstOrFail());

    expect(HanyaBaca::aktif())->toBeFalse();
});

it('proses sistem (pembangun dan pembongkar) tetap bebas menulis walau pengguna aktif ada di contoh bersama', function () {
    [$jalan] = bersamaDenganAkun();

    expect(HanyaBaca::aktif())->toBeTrue();

    Ranah::sebagai((string) $jalan->getKey(), function (): void {
        expect(HanyaBaca::aktif())->toBeFalse();
        Kurikulum::query()->firstOrFail()->update(['nama' => 'Dibangun ulang sistem']);
    });

    app(SimulasiService::class)->hapus($jalan);

    expect($jalan->fresh()->status)->toBe(SimulasiJalan::STATUS_DIBONGKAR);
});

it('halaman contoh terisi memuat keterangan hanya-baca, halaman contoh kosong tidak', function () {
    bersamaDenganAkun();

    expect(HanyaBaca::banner())->toContain('hanya untuk dilihat');

    $kosong = app(SimulasiService::class)->buat(mode: 'kosong')->jalan;
    $user = Ranah::sebagai((string) $kosong->getKey(), fn () => User::query()
        ->where('username', AkunSimulasi::username('sim-timkur', $kosong->kode()))->firstOrFail());
    $this->actingAs($user);

    expect(HanyaBaca::banner())->toBe('');
});

it('permintaan menulis yang lolos policy tetap berujung 403 lewat HTTP, bukan galat 500', function () {
    [, $user] = bersamaDenganAkun('sim-adminprodi', 'Admin Unit');

    $this->actingAs($user)
        ->withSession([ActiveRole::SESSION_KEY => 'Admin Unit'])
        ->get('/kurikulums/create')
        ->assertForbidden();
});

it('tabel CPL contoh bersama tidak menampilkan Ubah, dan hapus massal serta urut ulang dipaksa pun datanya utuh', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    [$jalan] = bersamaDenganAkun();
    $id = (string) $jalan->getKey();
    $semua = Ranah::sebagai($id, fn () => Cpl::query()->get());
    $sebelum = Ranah::sebagai($id, fn () => Cpl::query()->orderBy('kode')->pluck('deskripsi', 'kode')->all());

    $komponen = Livewire::test(ListCpls::class);
    $komponen->assertTableActionHidden('edit', $semua->first());

    foreach ([
        fn () => $komponen->callTableBulkAction('delete', $semua),
        fn () => $komponen->call('reorderTable', $semua->pluck('id')->reverse()->values()->all()),
    ] as $percobaan) {
        try {
            $percobaan();
        } catch (Throwable) {
            // Ditolak di lapis mana pun sama-sama sah; yang dinilai adalah datanya.
        }
    }

    expect(Ranah::sebagai($id, fn () => Cpl::query()->orderBy('kode')->pluck('deskripsi', 'kode')->all()))->toBe($sebelum);
});
