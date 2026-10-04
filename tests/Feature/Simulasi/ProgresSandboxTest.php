<?php

use App\Models\User;
use App\Modules\Simulasi\Filament\Pages\PusatSimulasi;
use App\Modules\Simulasi\Models\SimulasiJalan;
use App\Modules\Simulasi\Services\PembangunSimulasi;
use App\Modules\Simulasi\Services\SimulasiService;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    (new AcademicUnitSeeder)->run();
    (new RolePermissionSeeder)->run();
    (new SemesterSeeder)->run();
    (new EvaluasiSeeder)->run();
});

it('daftar tahap resmi sama persis dengan laporan pembangun untuk kedua mode', function (string $mode) {
    $dilaporkan = [];
    $svc = app(SimulasiService::class);
    $jalan = $svc->buat(lapor: function (string $langkah) use (&$dilaporkan): void {
        $dilaporkan[] = $langkah;
    }, mode: $mode)->jalan;

    $resmi = array_column(PembangunSimulasi::tahap($mode), 'label');

    expect($dilaporkan)->toBe($resmi);

    $p = $svc->progres($jalan);
    expect($p['selesai'])->toBeTrue()
        ->and($p['persen'])->toBe(100)
        ->and($p['tahap_selesai'])->toBe(count($resmi))
        ->and(collect($p['tahap'])->every(fn ($t) => $t['state'] === 'selesai'))->toBeTrue();
})->with([SimulasiJalan::MODE_KOSONG, SimulasiJalan::MODE_TERISI]);

it('progres menampilkan tahap berjalan dan menunggu selama pembangunan', function () {
    $svc = app(SimulasiService::class);
    $jalan = $svc->mulai(mode: SimulasiJalan::MODE_KOSONG);

    $p = $svc->progres($jalan);
    expect($p['berjalan'])->toBeTrue()->and($p['persen'])->toBe(0)
        ->and(collect($p['tahap'])->pluck('state')->unique()->all())->toBe(['menunggu']);

    $mid = null;
    $svc->selesaikan($jalan, function (string $langkah) use ($svc, $jalan, &$mid): void {
        if ($langkah === 'Membuat akun simulasi') {
            $mid = $svc->progres($jalan);
        }
    });

    expect($mid['tahap'][3]['state'])->toBe('berjalan')
        ->and($mid['tahap'][2]['state'])->toBe('selesai')
        ->and($mid['tahap'][4]['state'])->toBe('menunggu')
        ->and($mid['persen'])->toBeGreaterThan(0)->toBeLessThan(100);
});

it('kegagalan tercatat di progres beserta sebabnya', function () {
    $svc = app(SimulasiService::class);
    $jalan = $svc->mulai(mode: SimulasiJalan::MODE_KOSONG);

    expect(fn () => $svc->selesaikan($jalan, function (string $langkah): void {
        if ($langkah === 'Membuat akun simulasi') {
            throw new RuntimeException('galat buatan untuk uji');
        }
    }))->toThrow(RuntimeException::class);

    $p = $svc->progres($jalan->fresh());
    expect($p['gagal'])->toBeTrue()
        ->and($p['galat'])->toBe('galat buatan untuk uji')
        ->and(collect($p['tahap'])->pluck('state')->contains('gagal'))->toBeTrue();
});

it('tombol Siapkan Ruang berganti ke modal progres yang memuat tahap, token, dan hasil', function () {
    $this->actingAs(User::query()->where('username', 'superadmin')->firstOrFail());

    Livewire::test(PusatSimulasi::class)
        ->callAction('siapkan', ['jumlah' => 1])
        ->assertSee('Menyiapkan ruang')
        ->assertSee('Membuat akun simulasi')
        ->assertSee('Ruang siap dipakai')
        ->assertSee('100%');

    $ruang = SimulasiJalan::query()->masihAda()->get();

    expect($ruang)->toHaveCount(1)
        ->and($ruang->first()->pin)->toMatch('/^[A-HJ-KM-NP-Z2-9]{6}$/');
});

it('layar progres tidak dapat dibuka oleh non Super Admin', function () {
    $svc = app(SimulasiService::class);
    $jalan = $svc->mulai(mode: SimulasiJalan::MODE_KOSONG);

    $this->actingAs(User::query()->where('username', 'adminprodi')->firstOrFail());

    expect(PusatSimulasi::canAccess())->toBeFalse()
        ->and(fn () => (new PusatSimulasi)->mount())->toThrow(HttpException::class);

    Livewire::test(PusatSimulasi::class)->assertForbidden();
});
