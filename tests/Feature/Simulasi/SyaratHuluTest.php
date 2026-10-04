<?php

use App\Models\User;
use App\Modules\Auth\Support\ActiveRole;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Support\MkTerpilih;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Simulasi\Models\SimulasiJalan;
use App\Modules\Simulasi\Services\SimulasiService;
use App\Modules\Simulasi\Support\AkunSimulasi;
use App\Modules\Simulasi\Support\Ranah;
use App\Modules\Simulasi\Support\SyaratHulu;
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

/** Kunci aturan => [akun dasar, peran aktif] yang melihat halaman itu. */
const SYARAT_PEMAKAI = [
    'profil' => ['sim-timkur', 'Tim Kurikulum'],
    'cpl' => ['sim-timkur', 'Tim Kurikulum'],
    'bok' => ['sim-timkur', 'Tim Kurikulum'],
    'mk' => ['sim-timkur', 'Tim Kurikulum'],
    'penawaran' => ['sim-timkur', 'Tim Kurikulum'],
    'kelas' => ['sim-adminprodi', 'Admin'],
    'koordinator' => ['sim-korma', 'Koordinator Mata Kuliah'],
    'cpmk' => ['sim-korma', 'Koordinator Mata Kuliah'],
    'subcpmk' => ['sim-korma', 'Koordinator Mata Kuliah'],
    'asesmen' => ['sim-korma', 'Koordinator Mata Kuliah'],
    'peserta' => ['sim-korma', 'Koordinator Mata Kuliah'],
    'laporan' => ['sim-korma', 'Koordinator Mata Kuliah'],
    'dosen' => ['sim-dosen', 'Dosen Pengampu'],
];

function syaratMasuk(SimulasiJalan $jalan, string $dasar, string $peran): User
{
    $id = (string) $jalan->getKey();
    $user = Ranah::sebagai($id, fn () => User::query()
        ->where('username', AkunSimulasi::username($dasar, $jalan->kode()))->firstOrFail());

    test()->actingAs($user);
    ActiveRole::set($peran);

    return $user;
}

function syaratHitung(SimulasiJalan $jalan, string $kunci): array
{
    [$dasar, $peran] = SYARAT_PEMAKAI[$kunci];
    $user = syaratMasuk($jalan, $dasar, $peran);

    return SyaratHulu::untuk($kunci, $user);
}

it('di contoh kosong Dosen diberi tahu bahwa kelas belum ada dan siapa yang menanganinya', function () {
    $kosong = app(SimulasiService::class)->buat(mode: SimulasiJalan::MODE_KOSONG)->jalan;

    $butir = syaratHitung($kosong, 'dosen');

    expect($butir)->not->toBeEmpty()
        ->and($butir[0]['nama'])->toBeIn(['Tim Kurikulum', 'Admin Program Studi']);
});

it('di contoh terisi tidak ada satu pun keterangan syarat di halaman mana pun', function (string $kunci) {
    $terisi = app(SimulasiService::class)->buat(mode: SimulasiJalan::MODE_TERISI, jumlahMk: 1)->jalan;

    [$dasar, $peran] = SYARAT_PEMAKAI[$kunci];
    $user = syaratMasuk($terisi, $dasar, $peran);

    // Koordinator MK memilih MK-nya dulu, sebagaimana pengunjung sungguhan.
    if ($peran === 'Koordinator Mata Kuliah') {
        $mkId = Ranah::sebagai((string) $terisi->getKey(), fn () => Mk::query()
            ->where('koordinator_mk_id', $user->id)->value('id'));
        MkTerpilih::set($mkId);
    }

    expect(SyaratHulu::untuk($kunci, $user))->toBe([]);
})->with(array_keys(SYARAT_PEMAKAI));

it('keterangan dihitung dari sandbox yang sedang dilihat, bukan dari sandbox lain', function () {
    $service = app(SimulasiService::class);
    $kosong = $service->buat(mode: SimulasiJalan::MODE_KOSONG)->jalan;
    // Sandbox terisi ikut ada: bila ada kebocoran lintas sandbox, keterangan di bawah akan hilang.
    $service->buat(mode: SimulasiJalan::MODE_TERISI, jumlahMk: 1);

    foreach (['profil', 'cpl', 'bok', 'mk', 'penawaran', 'kelas', 'koordinator', 'cpmk', 'dosen'] as $kunci) {
        expect(syaratHitung($kosong, $kunci))->not->toBeEmpty("halaman {$kunci} di contoh kosong seharusnya berketerangan");
    }
});

it('rantai hulu ke hilir: tiap peran diberi tahu penangan yang benar pada contoh kosong', function () {
    $kosong = app(SimulasiService::class)->buat(mode: SimulasiJalan::MODE_KOSONG)->jalan;
    $id = (string) $kosong->getKey();

    // 1. Belum ada kurikulum → Tim Kurikulum.
    expect(syaratHitung($kosong, 'profil')[0]['nama'])->toBe('Tim Kurikulum')
        ->and(syaratHitung($kosong, 'cpl')[0]['judul'])->toBe('Kurikulum belum dibuat');

    // 2. Koordinator MK tanpa MK → menunggu Tim Kurikulum.
    $korma = syaratHitung($kosong, 'cpmk');
    expect($korma[0]['nama'])->toBe('Tim Kurikulum')
        ->and($korma[0]['judul'])->toBe('Belum ada mata kuliah');

    // 3. Setelah ada MK tanpa koordinator → Koordinator tetap menunggu penetapan namanya.
    $unit = Ranah::sebagai($id, fn () => AcademicUnit::query()->where('type', 'study_program')->firstOrFail());
    Ranah::sebagai($id, function () use ($id, $unit): void {
        Mk::query()->create([
            'academic_unit_id' => $unit->id,
            'sandbox_id' => $id,
            'kode' => 'LAT101',
            'nama' => 'MK Latihan',
            'sks_teori' => 2,
            'sks_praktik' => 0,
            'sks_lapangan' => 0,
            'sks' => 2,
            'jenis' => 'wajib',
            'is_active' => true,
        ]);
    });

    $setelahMk = syaratHitung($kosong, 'cpmk');
    expect($setelahMk[0]['judul'])->toBe('Anda belum ditetapkan sebagai Koordinator MK')
        ->and($setelahMk[0]['nama'])->toBe('Tim Kurikulum')
        ->and(syaratHitung($kosong, 'mk'))->not->toBeEmpty();
});

it('Dosen yang kelasnya belum punya asesmen diberi tahu bahwa Koordinator MK belum menentukan tagihan', function () {
    $terisi = app(SimulasiService::class)->buat(mode: SimulasiJalan::MODE_TERISI, jumlahMk: 1)->jalan;
    $id = (string) $terisi->getKey();

    $dosen = syaratMasuk($terisi, 'sim-dosen', 'Dosen Pengampu');

    // Hapus seluruh asesmen MK yang diampu: meniru Koordinator yang belum menyusunnya.
    Ranah::sebagai($id, function () use ($dosen): void {
        $mkIds = KelasMk::query()->where('dosen_pengampu_id', $dosen->id)->with('mkUnit')->get()->pluck('mkUnit.mk_id');
        KomponenPenilaian::query()->whereIn('mk_id', $mkIds)->delete();
    });

    $butir = SyaratHulu::untuk('dosen', $dosen);

    expect($butir)->not->toBeEmpty()
        ->and($butir[0]['judul'])->toContain('Koordinator MK belum menentukan tagihan penilaian')
        ->and($butir[0]['nama'])->toBe('Koordinator MK')
        ->and($butir[0]['rincian'])->toContain('asesmen');
});

it('akun data inti tidak pernah mendapat keterangan, dan keterangan tidak merusak halaman', function () {
    app(SimulasiService::class)->buat(mode: SimulasiJalan::MODE_KOSONG);

    $this->actingAs(User::query()->where('username', 'adminprodi')->firstOrFail());

    expect(SyaratHulu::render('dosen'))->toBe('')
        ->and(SyaratHulu::render('kunci-tak-dikenal'))->toBe('');
});

it('render menghasilkan kotak keterangan berisi penangan dan langkah untuk akun sandbox', function () {
    $kosong = app(SimulasiService::class)->buat(mode: SimulasiJalan::MODE_KOSONG)->jalan;
    syaratMasuk($kosong, 'sim-dosen', 'Dosen Pengampu');

    $html = SyaratHulu::render('dosen');

    expect($html)->toContain('data-syarat-hulu')
        ->and($html)->toContain('Ditangani oleh')
        ->and($html)->toContain('Caranya:')
        ->and($html)->toContain('menunggu langkah peran lain');
});

it('semua halaman pada peta terdaftar sebagai hook dan setiap kunci punya aturan', function () {
    foreach (SyaratHulu::PETA as $kelas => $kunci) {
        expect(class_exists($kelas))->toBeTrue("{$kelas} tidak ada")
            ->and(array_key_exists($kunci, SYARAT_PEMAKAI))->toBeTrue("{$kunci} tidak diuji");
    }
});

it('kunciDariPath memetakan path (termasuk awalan tab) ke aturan dengan slug terpanjang menang', function () {
    expect(SyaratHulu::kunciDariPath('s/abc123/cpmk'))->toBe('cpmk')
        ->and(SyaratHulu::kunciDariPath('cpls/create'))->toBe('cpl')
        ->and(SyaratHulu::kunciDariPath('penilaian'))->toBe('dosen')
        ->and(SyaratHulu::kunciDariPath('s/abc/penilaian/laporan-koordinator'))->toBe('laporan')
        ->and(SyaratHulu::kunciDariPath('halaman-tak-dikenal'))->toBeNull();
});

it('layar 403 akun sandbox menjelaskan syarat hulu, dan akun inti tidak melihatnya', function () {
    $kosong = app(SimulasiService::class)->buat(mode: SimulasiJalan::MODE_KOSONG)->jalan;

    syaratMasuk($kosong, 'sim-korma', 'Koordinator Mata Kuliah');
    $html = SyaratHulu::renderUntukPenolakan('s/tokenapa/cpmk');

    expect($html)->toContain('data-syarat-hulu')
        ->and($html)->toContain('Tim Kurikulum')
        ->and($html)->toContain('Caranya:');

    // Halaman tak dikenal tetap mendapat penjelasan umum, bukan layar kosong.
    expect(SyaratHulu::renderUntukPenolakan('s/tokenapa/entah-apa'))->toContain('Peran hulu');

    auth()->logout();
    $this->actingAs(User::query()->where('username', 'adminprodi')->firstOrFail());

    expect(SyaratHulu::renderUntukPenolakan('cpmk'))->toBe('');
});

it('halaman 403 benar-benar menampilkan keterangan untuk akun sandbox lewat HTTP', function () {
    $kosong = app(SimulasiService::class)->buat(mode: SimulasiJalan::MODE_KOSONG)->jalan;
    $user = syaratMasuk($kosong, 'sim-korma', 'Koordinator Mata Kuliah');

    $respons = $this->actingAs($user)->withSession([ActiveRole::SESSION_KEY => 'Koordinator Mata Kuliah'])->get('/cpmk');

    $respons->assertForbidden()->assertSee('data-syarat-hulu', false)->assertSee('Ditangani oleh');
});
