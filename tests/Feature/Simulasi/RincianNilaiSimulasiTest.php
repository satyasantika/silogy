<?php

use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use App\Modules\Penilaian\Models\RincianNilaiKelas;
use App\Modules\Penilaian\Models\ValidasiNilaiKelas;
use App\Modules\Penilaian\Services\DataLaporanKelasService;
use App\Modules\Penilaian\Services\KetercapaianSubcpmkService;
use App\Modules\Penilaian\Services\RincianNilaiService;
use App\Modules\Simulasi\Models\SimulasiJalan;
use App\Modules\Simulasi\Services\SimulasiService;
use App\Modules\Simulasi\Support\Ranah;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('simulasi.izinkan_coba_peran', true);
    Cache::flush();

    (new AcademicUnitSeeder)->run();
    (new RolePermissionSeeder)->run();
    (new SemesterSeeder)->run();
    (new EvaluasiSeeder)->run();
});

it('contoh terisi: tepat satu asesmen dirinci dan ada Sub-CPMK yang terukur terpisah', function () {
    $jalan = app(SimulasiService::class)->buat(mode: SimulasiJalan::MODE_TERISI, bersama: true)->jalan;

    Ranah::sebagai((string) $jalan->getKey(), function (): void {
        $kelas = KelasMk::query()->firstOrFail();
        $rincian = app(RincianNilaiService::class)->rincianUntukKelas($kelas);

        expect($rincian)->toHaveCount(1);

        $baris = app(KetercapaianSubcpmkService::class)
            ->untukKelas($kelas, $rincian, app(DataLaporanKelasService::class)->muat($kelas)['targetCapaianLulusan']);

        expect(collect($baris)->where('dasar', 'terpisah')->count())->toBeGreaterThanOrEqual(1);
    });
});

it('contoh terisi: tetap tervalidasi dan tidak ada model tak dikenal', function () {
    $hasil = app(SimulasiService::class)->buat(mode: SimulasiJalan::MODE_TERISI, bersama: true);

    expect($hasil->takDikenal)->toBe([]);

    Ranah::sebagai((string) $hasil->jalan->getKey(), function (): void {
        $laporan = app(DataLaporanKelasService::class);
        $kelas = KelasMk::query()->firstOrFail();
        $baris = ValidasiNilaiKelas::query()->where('kelas_mk_id', $kelas->id)->firstOrFail();

        expect($baris->gugur_at)->toBeNull()
            ->and($baris->sidik)->toBe($laporan->sidik($laporan->muat($kelas)));
    });
});

it('contoh terisi: asesmen yang tak dirinci bernilai sama di semua pivotnya untuk tiap mahasiswa', function () {
    $jalan = app(SimulasiService::class)->buat(mode: SimulasiJalan::MODE_TERISI, bersama: true)->jalan;

    Ranah::sebagai((string) $jalan->getKey(), function (): void {
        $kelas = KelasMk::query()->firstOrFail();
        $dirinci = RincianNilaiKelas::query()->where('kelas_mk_id', $kelas->id)->pluck('komponen_penilaian_id')->all();
        $kmmIds = KelasMkMahasiswa::query()->where('kelas_mk_id', $kelas->id)->pluck('id');

        $nilai = NilaiMahasiswa::query()
            ->whereIn('kelas_mk_mahasiswa_id', $kmmIds)
            ->with('subcpmkKomponen')
            ->get()
            ->reject(fn ($n): bool => in_array($n->subcpmkKomponen->komponen_penilaian_id, $dirinci, true))
            ->groupBy(fn ($n): string => $n->kelas_mk_mahasiswa_id.'|'.$n->subcpmkKomponen->komponen_penilaian_id);

        expect($nilai)->not->toBeEmpty();

        foreach ($nilai as $kelompok) {
            expect($kelompok->count())->toBeGreaterThan(1)
                ->and($kelompok->pluck('nilai')->unique()->count())->toBe(1);
        }
    });
});

it('membongkar sandbox menghapus baris rincian nilai kelasnya', function () {
    $jalan = app(SimulasiService::class)->buat(mode: SimulasiJalan::MODE_TERISI)->jalan;
    $kelasId = Ranah::sebagai((string) $jalan->getKey(), fn () => KelasMk::query()->value('id'));

    expect(RincianNilaiKelas::query()->where('kelas_mk_id', $kelasId)->count())->toBe(1);

    app(SimulasiService::class)->hapus($jalan);

    expect(RincianNilaiKelas::query()->where('kelas_mk_id', $kelasId)->count())->toBe(0);
});
