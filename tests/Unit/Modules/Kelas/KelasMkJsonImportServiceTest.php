<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Kelas\Services\KelasMkJsonImportService;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SemesterSeeder::class);

    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->semester = Semester::query()->where('kode', '20252')->firstOrFail();

    $this->koordinator = User::factory()->create(['nidn' => null]);
    $this->dosen = User::factory()->create(['nidn' => '0412026601']);

    $this->mk = Mk::factory()->forAcademicUnit($this->prodi)->create([
        'nama' => 'Ekonomi SDA dan SDM',
        'koordinator_mk_id' => $this->koordinator->id,
    ]);
    $this->mkUnit = MkUnit::factory()->forMk($this->mk)->forAcademicUnit($this->prodi)->create([
        'kode' => 'KP92552012',
    ]);

    $this->mahasiswa = Mahasiswa::factory()->create([
        'nim' => '259255111003',
        'academic_unit_id' => $this->prodi->id,
    ]);

    $this->service = new KelasMkJsonImportService;
});

function payloadDasarKelasMkJson(object $context, array $overrides = []): array
{
    return array_replace_recursive([
        'tahun_akademik' => 20252,
        'kode_prodi' => $context->prodi->code,
        'data' => [
            [
                'kode_mk' => 'KP92552012',
                'nama_mk' => 'Ekonomi SDA dan SDM',
                'kelas' => 'A',
                'sks' => 3,
                'dosen_pengampu' => [
                    'nama' => 'RINA NURYATI',
                    'nidn' => '0412026601',
                    'nuptk' => '4544744645230132',
                ],
                'peserta' => [
                    [
                        'npm' => 259255111003,
                        'nama' => 'RIFKI MUHAMAD IMADUDIN',
                        'kode_prodi' => $context->prodi->code,
                        'nilai_akhir' => 85,
                        'grade' => 'A',
                        'komponen_evaluasi' => [
                            ['nomor_urut' => 1, 'nama' => '', 'bobot_evaluasi' => 0, 'nilai' => 0, 'nilai_akhir' => 0],
                        ],
                    ],
                ],
            ],
        ],
    ], $overrides);
}

it('membuat kelas mk baru, mengisi koordinator dari mk, mencocokkan dosen via nidn, dan mendaftarkan peserta via npm', function () {
    $hasil = $this->service->import(payloadDasarKelasMkJson($this));

    $kelas = KelasMk::query()
        ->where('mk_unit_id', $this->mkUnit->id)
        ->where('semester_id', $this->semester->id)
        ->where('kode_kelas', 'A')
        ->first();

    expect($kelas)->not->toBeNull()
        ->and($kelas->dosen_pengampu_id)->toBe($this->dosen->id)
        ->and($kelas->koordinator_mk_id)->toBe($this->koordinator->id);

    expect(KelasMkMahasiswa::query()
        ->where('kelas_mk_id', $kelas->id)
        ->where('mahasiswa_id', $this->mahasiswa->id)
        ->exists())->toBeTrue();

    expect($hasil['kelas_dibuat'])->toBe(1)
        ->and($hasil['kelas_diperbarui'])->toBe(0)
        ->and($hasil['peserta_terdaftar'])->toBe(1)
        ->and($hasil['peserta_sudah_terdaftar'])->toBe(0)
        ->and($hasil['errors'])->toBe([]);

    // Nilai/grade/komponen_evaluasi sengaja diabaikan — tidak ada field terkait yang terisi.
    expect((float) ($kelas->mahasiswas()->first()?->pivot?->nilai_angka ?? 0))->toBe(0.0);
});

it('mengimpor ulang payload yang sama bersifat idempoten (kelas diperbarui, peserta sudah terdaftar)', function () {
    $this->service->import(payloadDasarKelasMkJson($this));
    $hasil = $this->service->import(payloadDasarKelasMkJson($this));

    expect(KelasMk::query()->count())->toBe(1)
        ->and(KelasMkMahasiswa::query()->count())->toBe(1)
        ->and($hasil['kelas_dibuat'])->toBe(0)
        ->and($hasil['kelas_diperbarui'])->toBe(1)
        ->and($hasil['peserta_terdaftar'])->toBe(0)
        ->and($hasil['peserta_sudah_terdaftar'])->toBe(1);
});

it('memindahkan mahasiswa dari kelas A ke kelas D pada pull berikutnya menghapus pivot kelas lama beserta nilainya', function () {
    $this->service->import(payloadDasarKelasMkJson($this));

    $kelasA = KelasMk::query()->where('kode_kelas', 'A')->firstOrFail();

    KelasMkMahasiswa::query()
        ->where('kelas_mk_id', $kelasA->id)
        ->where('mahasiswa_id', $this->mahasiswa->id)
        ->update(['nilai_angka' => 88, 'nilai_huruf' => 'A']);

    $payloadPindah = payloadDasarKelasMkJson($this);
    $payloadPindah['data'][0]['peserta'] = [];

    $barisKelasD = payloadDasarKelasMkJson($this)['data'][0];
    $barisKelasD['kelas'] = 'D';
    $payloadPindah['data'][] = $barisKelasD;

    $hasil = $this->service->import($payloadPindah);

    $kelasD = KelasMk::query()->where('kode_kelas', 'D')->firstOrFail();

    expect(KelasMkMahasiswa::query()->where('kelas_mk_id', $kelasA->id)->count())->toBe(0)
        ->and(KelasMkMahasiswa::query()
            ->where('kelas_mk_id', $kelasD->id)
            ->where('mahasiswa_id', $this->mahasiswa->id)
            ->exists())->toBeTrue()
        ->and(KelasMkMahasiswa::query()->where('mahasiswa_id', $this->mahasiswa->id)->count())->toBe(1)
        ->and($hasil['peserta_dihapus'])->toBe(1)
        ->and($hasil['peserta_terdaftar'])->toBe(1);
});

it('melaporkan nidn dan kode_mk yang tidak dikenali tanpa membatalkan seluruh impor', function () {
    $payload = payloadDasarKelasMkJson($this);
    $payload['data'][0]['dosen_pengampu']['nidn'] = '9999999999';
    $payload['data'][] = [
        'kode_mk' => 'TIDAK-ADA',
        'kelas' => 'A',
        'peserta' => [],
    ];

    $hasil = $this->service->import($payload);

    expect($hasil['kelas_dibuat'])->toBe(1)
        ->and($hasil['peserta_terdaftar'])->toBe(1)
        ->and($hasil['errors'])->toHaveCount(2);

    $kelas = KelasMk::query()->where('mk_unit_id', $this->mkUnit->id)->first();
    expect($kelas->dosen_pengampu_id)->toBeNull();
});

it('membuat mahasiswa baru otomatis bila npm belum ada pada tabel mahasiswa', function () {
    $payload = payloadDasarKelasMkJson($this);
    $payload['data'][0]['peserta'][] = [
        'npm' => 259255222004,
        'nama' => 'Mahasiswa Baru Dari Sintesys',
    ];

    $hasil = $this->service->import($payload);

    expect($hasil['errors'])->toBe([])
        ->and($hasil['mahasiswa_dibuat'])->toBe(1)
        ->and($hasil['peserta_terdaftar'])->toBe(2);

    $mahasiswaBaru = Mahasiswa::query()->where('nim', '259255222004')->first();

    expect($mahasiswaBaru)->not->toBeNull()
        ->and($mahasiswaBaru->nama)->toBe('Mahasiswa Baru Dari Sintesys')
        ->and($mahasiswaBaru->academic_unit_id)->toBe($this->prodi->id);

    $kelas = KelasMk::query()->where('mk_unit_id', $this->mkUnit->id)->first();

    expect(KelasMkMahasiswa::query()
        ->where('kelas_mk_id', $kelas->id)
        ->where('mahasiswa_id', $mahasiswaBaru->id)
        ->exists())->toBeTrue();
});

it('menolak npm yang nim-nya sudah terdaftar pada prodi lain, tidak membuat duplikat', function () {
    $prodiLain = AcademicUnit::factory()->create(['type' => 'study_program', 'code' => 'LAIN02']);
    $mahasiswaProdiLain = Mahasiswa::factory()->create([
        'nim' => '999000111222',
        'academic_unit_id' => $prodiLain->id,
    ]);

    $payload = payloadDasarKelasMkJson($this);
    $payload['data'][0]['peserta'][] = ['npm' => '999000111222'];

    $hasil = $this->service->import($payload);

    expect($hasil['errors'])->toHaveCount(1)
        ->and($hasil['mahasiswa_dibuat'])->toBe(0);

    expect(Mahasiswa::query()->where('nim', '999000111222')->count())->toBe(1)
        ->and(Mahasiswa::query()->where('nim', '999000111222')->first()->id)->toBe($mahasiswaProdiLain->id);
});

it('menolak semester atau kode prodi yang tidak dikenal', function () {
    $payload = payloadDasarKelasMkJson($this, ['tahun_akademik' => '99999']);

    expect(fn () => $this->service->import($payload))
        ->toThrow(ValidationException::class);

    $payload2 = payloadDasarKelasMkJson($this, ['kode_prodi' => 'TIDAK-ADA']);

    expect(fn () => $this->service->import($payload2))
        ->toThrow(ValidationException::class);
});

it('menolak impor ke prodi di luar cakupan akses yang diberikan', function () {
    $payload = payloadDasarKelasMkJson($this);

    expect(fn () => $this->service->import($payload, collect(['id-lain-yang-tidak-terkait'])))
        ->toThrow(ValidationException::class);
});

it('memanggil callback progres persis sebanyak hitungTotalUnit dan tetap berjalan walau ada baris tak dikenal', function () {
    $payload = payloadDasarKelasMkJson($this);
    $payload['data'][0]['peserta'][] = ['npm' => 999999999999];
    $payload['data'][] = ['kode_mk' => 'TIDAK-ADA', 'kelas' => 'A', 'peserta' => []];

    $totalUnit = $this->service->hitungTotalUnit($payload);
    $dipanggil = 0;

    $this->service->import($payload, null, function () use (&$dipanggil): void {
        $dipanggil++;
    });

    expect($totalUnit)->toBe(4)
        ->and($dipanggil)->toBe($totalUnit);
});
