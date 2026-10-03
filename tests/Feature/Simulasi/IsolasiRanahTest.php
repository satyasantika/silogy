<?php

use App\Models\User;
use App\Modules\Audit\Models\Activity;
use App\Modules\CPL\Models\Cpl;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\Simulasi\Services\SimulasiService;
use App\Modules\Simulasi\Support\AkunSimulasi;
use App\Modules\Simulasi\Support\Ranah;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new AcademicUnitSeeder)->run();
    (new RolePermissionSeeder)->run();
    (new SemesterSeeder)->run();
    (new EvaluasiSeeder)->run();
});

function akunSandbox(object $jalan, string $kunci): User
{
    return Ranah::sebagai($jalan->id, fn () => User::query()
        ->where('username', AkunSimulasi::username($kunci, $jalan->kode()))->firstOrFail());
}

/**
 * Model yang terpagar, dengan cara menghitung barisnya di ranah mana pun.
 * Daftar ini sama dengan yang dipasangi BerRanahSimulasi.
 *
 * @return list<class-string>
 */
function modelTerpagar(): array
{
    return [
        AcademicUnit::class, User::class, Mahasiswa::class, Kurikulum::class,
        Cpl::class, Mk::class, MkUnit::class, KelasMk::class,
    ];
}

it('akun inti tidak melihat satu pun baris sandbox', function () {
    $jalan = app(SimulasiService::class)->buat()->jalan;

    $super = User::query()->where('username', 'superadmin')->firstOrFail();
    $this->actingAs($super);

    foreach (modelTerpagar() as $kelas) {
        $total = $kelas::withoutGlobalScopes()->count();
        $terlihat = $kelas::query()->count();
        $milikSandbox = Ranah::sebagai($jalan->id, fn () => $kelas::query()->count());

        expect($milikSandbox)->toBeGreaterThan(0, "$kelas kosong di sandbox")
            ->and($terlihat)->toBe($total - $milikSandbox, "$kelas bocor ke akun inti");
    }
});

it('akun inti tidak menemukan akun sandbox lewat find, where, maupun pencarian username', function () {
    $jalan = app(SimulasiService::class)->buat()->jalan;
    $akun = akunSandbox($jalan, 'sim-dosen');

    $this->actingAs(User::query()->where('username', 'superadmin')->firstOrFail());

    expect(User::query()->find($akun->id))->toBeNull()
        ->and(User::query()->where('username', $akun->username)->exists())->toBeFalse()
        ->and(User::query()->where('username', 'like', 'sim-%')->count())->toBe(0)
        ->and(AcademicUnit::query()->where('code', 'like', 'SIM-%')->count())->toBe(0);
});

it('akun sandbox tidak melihat data inti', function () {
    $jalan = app(SimulasiService::class)->buat()->jalan;
    $nyata = User::query()->where('username', 'superadmin')->firstOrFail();
    $unitNyata = AcademicUnit::query()->where('code', 'not like', 'SIM-%')->count();

    expect($unitNyata)->toBeGreaterThan(0);

    $this->actingAs(akunSandbox($jalan, 'sim-adminuniv'));

    expect(User::query()->find($nyata->id))->toBeNull()
        ->and(AcademicUnit::query()->where('code', 'not like', 'SIM-%')->count())->toBe(0)
        ->and(User::query()->where('sandbox_id', '!=', $jalan->id)->orWhereNull('sandbox_id')->count())->toBe(0);
});

it('akun sandbox satu tidak melihat sandbox lain', function () {
    $simulasi = app(SimulasiService::class);
    $a = $simulasi->buat()->jalan;
    $b = $simulasi->buat()->jalan;

    $this->actingAs(akunSandbox($a, 'sim-rektor'));

    foreach (modelTerpagar() as $kelas) {
        $milikB = Ranah::sebagai($b->id, fn () => $kelas::query()->count());
        $terlihat = $kelas::query()->count();
        $milikA = Ranah::sebagai($a->id, fn () => $kelas::query()->count());

        expect($milikB)->toBeGreaterThan(0)
            ->and($terlihat)->toBe($milikA, "$kelas bocor antarsandbox");
    }
});

it('log aktivitas pelaku sandbox tidak tampil bagi akun inti', function () {
    $jalan = app(SimulasiService::class)->buat()->jalan;
    $akun = akunSandbox($jalan, 'sim-dosen');

    activity()->causedBy($akun)->log('aksi di sandbox');
    activity()->causedBy(User::query()->where('username', 'superadmin')->firstOrFail())->log('aksi nyata');

    $this->actingAs(User::query()->where('username', 'superadmin')->firstOrFail());

    $deskripsi = Activity::query()->pluck('description')->all();

    expect($deskripsi)->toContain('aksi nyata')->not->toContain('aksi di sandbox');
});

it('baris baru yang dibuat akun sandbox otomatis menjadi milik sandbox itu', function () {
    $jalan = app(SimulasiService::class)->buat()->jalan;

    $this->actingAs(akunSandbox($jalan, 'sim-adminprodi'));

    $prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $baru = Mahasiswa::factory()->create(['academic_unit_id' => $prodi->id]);

    expect($baru->fresh()->sandbox_id)->toBe($jalan->id);
});

it('proses tanpa pengguna (artisan, queue) tidak disaring', function () {
    app(SimulasiService::class)->buat();

    expect(Ranah::aktif())->toBeNull()
        ->and(User::query()->where('username', 'like', 'sim-%')->count())->toBeGreaterThan(0);
});

it('Ranah::sebagai menimpa pengguna yang sedang masuk lalu memulihkannya', function () {
    $jalan = app(SimulasiService::class)->buat()->jalan;
    $this->actingAs(User::query()->where('username', 'superadmin')->firstOrFail());

    expect(Ranah::aktif())->toBe([null]);

    Ranah::sebagai($jalan->id, fn () => expect(Ranah::aktif())->toBe([$jalan->id]));

    expect(Ranah::aktif())->toBe([null]);
});
