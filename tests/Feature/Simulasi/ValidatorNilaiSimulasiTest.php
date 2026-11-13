<?php

use App\Models\User;
use App\Modules\Auth\Support\ActiveRole;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Penilaian\Filament\Pages\InputNilai;
use App\Modules\Penilaian\Filament\Pages\ValidasiNilai;
use App\Modules\Penilaian\Models\ValidasiNilaiKelas;
use App\Modules\Penilaian\Policies\ValidasiNilaiPolicy;
use App\Modules\Penilaian\Support\ValidatorNilaiScope;
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
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('simulasi.izinkan_coba_peran', true);
    Cache::flush();
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    (new AcademicUnitSeeder)->run();
    (new RolePermissionSeeder)->run();
    (new SemesterSeeder)->run();
    (new EvaluasiSeeder)->run();
});

/** Sandbox terisi (bersama atau pribadi) dan akun yang dipilih di dalamnya, sudah login. */
function sandboxValidator(bool $bersama, string $dasar = 'sim-validator', string $peran = 'Validator Nilai'): array
{
    $jalan = app(SimulasiService::class)->buat(mode: 'terisi', bersama: $bersama)->jalan;
    $user = Ranah::sebagai((string) $jalan->getKey(), fn () => User::query()
        ->where('username', AkunSimulasi::username($dasar, $jalan->kode()))->firstOrFail());

    test()->actingAs($user);
    ActiveRole::set($peran);

    return [$jalan, $user];
}

it('contoh terisi: laporan kelas tampil tervalidasi tanpa penulisan', function () {
    [$jalan, $dosen] = sandboxValidator(true, 'sim-dosen', 'Dosen Pengampu');

    $kelas = KelasMk::query()->where('dosen_pengampu_id', $dosen->id)->get()
        ->first(fn (KelasMk $k): bool => $k->penugasanSelesai());
    $validator = Ranah::sebagai((string) $jalan->getKey(), fn () => User::query()
        ->where('username', AkunSimulasi::username('sim-validator', $jalan->kode()))->firstOrFail());

    expect(HanyaBaca::aktif())->toBeTrue();

    Livewire::test(InputNilai::class, ['kelas_mk_id' => $kelas->id])
        ->set('kelasMkId', $kelas->id)
        ->call('loadMatrix')
        ->assertSet('statusValidasi', 'tervalidasi');

    $baris = ValidasiNilaiKelas::query()->where('kelas_mk_id', $kelas->id)->firstOrFail();

    expect($baris->validator_id)->toBe($validator->id)
        ->and($baris->gugur_at)->toBeNull()
        ->and($validator->hasRole('Validator Nilai'))->toBeTrue();
});

it('hanya-baca: aksi validasi dan batalkan ditolak', function () {
    [, $validator] = sandboxValidator(true);
    $kelas = KelasMk::query()->get()->first(fn (KelasMk $k): bool => $k->penugasanSelesai());

    expect(HanyaBaca::aktif($validator))->toBeTrue()
        ->and(ValidatorNilaiScope::kelasMkQuery($validator)->whereKey($kelas->id)->exists())->toBeTrue()
        ->and(Gate::forUser($validator)->allows('lihatValidasiNilai', $kelas))->toBeTrue()
        ->and(Gate::forUser($validator)->denies('validasiNilai', $kelas))->toBeTrue()
        ->and(Gate::forUser($validator)->denies('batalkanValidasiNilai', $kelas))->toBeTrue();

    Livewire::test(ValidasiNilai::class)
        ->call('bukaKelas', $kelas->id)
        ->assertSet('statusValidasi', 'tervalidasi')
        ->assertActionHidden('batalkanValidasi');

    expect(ValidasiNilaiKelas::query()->where('kelas_mk_id', $kelas->id)->exists())->toBeTrue();
});

it('sandbox pribadi: sim-validator bisa membatalkan lalu memvalidasi ulang kelas prodi simulasi', function () {
    [, $validator] = sandboxValidator(false);
    $kelas = KelasMk::query()->get()->first(fn (KelasMk $k): bool => $k->penugasanSelesai());

    expect(HanyaBaca::aktif($validator))->toBeFalse();

    Livewire::test(ValidasiNilai::class)
        ->call('bukaKelas', $kelas->id)
        ->assertSet('statusValidasi', 'tervalidasi')
        ->callAction('batalkanValidasi')
        ->assertSet('statusValidasi', 'belum')
        ->callAction('validasi')
        ->assertSet('statusValidasi', 'tervalidasi');

    $baris = ValidasiNilaiKelas::query()->where('kelas_mk_id', $kelas->id)->firstOrFail();

    expect($baris->validator_id)->toBe($validator->id);
});

it('baris validasi terisolasi antarsandbox dan ikut terhapus bersama sandbox', function () {
    [$a, $validatorA] = sandboxValidator(false);
    $b = app(SimulasiService::class)->buat(mode: 'terisi')->jalan;
    $idA = (string) $a->getKey();
    $idB = (string) $b->getKey();

    $kelasA = Ranah::sebagai($idA, fn () => KelasMk::query()->pluck('id')->all());
    $kelasB = Ranah::sebagai($idB, fn () => KelasMk::query()->pluck('id')->all());

    // (a) cakupan validator A tidak menjangkau kelas sandbox B.
    $terlihat = ValidatorNilaiScope::kelasMkQuery($validatorA)->pluck('kelas_mk.id')->all();

    expect($kelasA)->not->toBeEmpty()->and($kelasB)->not->toBeEmpty()
        ->and($terlihat)->toEqualCanonicalizing($kelasA)
        ->and(array_intersect($terlihat, $kelasB))->toBe([]);

    expect(ValidasiNilaiKelas::query()->whereIn('kelas_mk_id', $kelasA)->count())->toBe(count($kelasA))
        ->and(ValidasiNilaiKelas::query()->whereIn('kelas_mk_id', $kelasB)->count())->toBe(count($kelasB));

    // (b) membongkar sandbox A menghapus barisnya lewat cascade kelas_mk; sandbox B utuh.
    app(SimulasiService::class)->hapus($a);

    expect($a->fresh()->status)->toBe(SimulasiJalan::STATUS_DIBONGKAR)
        ->and(ValidasiNilaiKelas::query()->whereIn('kelas_mk_id', $kelasA)->count())->toBe(0)
        ->and(ValidasiNilaiKelas::query()->whereIn('kelas_mk_id', $kelasB)->count())->toBe(count($kelasB));
});

it('hanya-baca: tombol Validasi tidak tampil walau kelas belum divalidasi', function () {
    [$jalan, $validator] = sandboxValidator(true);
    $kelas = KelasMk::query()->get()->first(fn (KelasMk $k): bool => $k->penugasanSelesai());

    // Proses sistem (di bawah Ranah::sebagai) boleh menulis; jadikan kelas belum divalidasi.
    Ranah::sebagai((string) $jalan->getKey(), fn () => ValidasiNilaiKelas::query()->where('kelas_mk_id', $kelas->id)->delete());

    expect(HanyaBaca::aktif($validator))->toBeTrue()
        ->and(app(ValidasiNilaiPolicy::class)->alasanTidakBisaValidasi($validator, $kelas))->toBeNull();

    Livewire::test(ValidasiNilai::class)
        ->call('bukaKelas', $kelas->id)
        ->assertSet('statusValidasi', 'belum')
        ->assertActionHidden('validasi')
        ->assertActionHidden('batalkanValidasi');
});
