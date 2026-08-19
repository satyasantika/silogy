<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Services\SetBanyakKelasMkService;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SemesterSeeder::class);

    $this->service = app(SetBanyakKelasMkService::class);
    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->semester = Semester::query()->where('status_aktif', true)->firstOrFail();
    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Uji Set Kelas',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    $this->mkA = Mk::factory()->create(['academic_unit_id' => $this->prodi->id, 'nama' => 'Kalkulus']);
    $this->mkB = Mk::factory()->create(['academic_unit_id' => $this->prodi->id, 'nama' => 'Algoritma']);
    $this->mkUnitA = MkUnit::factory()->forMk($this->mkA)->forAcademicUnit($this->prodi)->create(['kode' => 'SET101']);
    $this->mkUnitB = MkUnit::factory()->forMk($this->mkB)->forAcademicUnit($this->prodi)->create(['kode' => 'SET102']);
});

it('menyusun preview matriks mk sebagai baris dan kelas a b c sebagai kolom', function () {
    $penawaran = [
        [
            'mk_unit_id' => $this->mkUnitA->id,
            'kode_penawaran' => 'SET101',
            'nama_mk' => 'Kalkulus',
            'dipilih' => true,
            'jumlah_kelas' => 2,
        ],
        [
            'mk_unit_id' => $this->mkUnitB->id,
            'kode_penawaran' => 'SET102',
            'nama_mk' => 'Algoritma',
            'dipilih' => true,
            'jumlah_kelas' => 3,
        ],
    ];

    $preview = $this->service->buildPreview($this->semester->id, $this->service->penawaranTerpilih($penawaran));

    expect($preview['kolom_kelas'])->toBe(['A', 'B', 'C'])
        ->and($preview['baris'])->toHaveCount(2)
        ->and($preview['baris'][0]['sel'][0]['status'])->toBe('baru')
        ->and($preview['baris'][0]['sel'][1]['status'])->toBe('baru')
        ->and($preview['baris'][0]['sel'][2]['status'])->toBe('kosong')
        ->and($preview['baris'][1]['sel'][2]['status'])->toBe('baru')
        ->and($preview['ringkasan']['baru'])->toBe(5);
});

it('melewati kelas duplikat saat mode lewati', function () {
    KelasMk::query()->create([
        'mk_unit_id' => $this->mkUnitA->id,
        'semester_id' => $this->semester->id,
        'kode_kelas' => 'A',
    ]);

    $penawaran = [[
        'mk_unit_id' => $this->mkUnitA->id,
        'kode_penawaran' => 'SET101',
        'nama_mk' => 'Kalkulus',
        'dipilih' => true,
        'jumlah_kelas' => 2,
    ]];

    $hasil = $this->service->jalankan($this->semester->id, $penawaran, 'lewati');

    expect($hasil['dibuat'])->toBe(1)
        ->and($hasil['dilewati'])->toBe(1)
        ->and(KelasMk::query()->where('mk_unit_id', $this->mkUnitA->id)->where('semester_id', $this->semester->id)->count())->toBe(2);
});

it('membuat kelas dengan koordinator default dari mk', function () {
    $korma = User::query()->where('username', 'korma')->firstOrFail();
    $this->mkA->update(['koordinator_mk_id' => $korma->id]);

    $penawaran = [[
        'mk_unit_id' => $this->mkUnitA->id,
        'kode_penawaran' => 'SET101',
        'nama_mk' => 'Kalkulus',
        'dipilih' => true,
        'jumlah_kelas' => 1,
    ]];

    $this->service->jalankan($this->semester->id, $penawaran);

    $kelas = KelasMk::query()->where('mk_unit_id', $this->mkUnitA->id)->first();

    expect($kelas?->kode_kelas)->toBe('A')
        ->and($kelas?->koordinator_mk_id)->toBe($korma->id);
});

it('penawaran default state mengikuti kurikulum terpilih', function () {
    $bySemester = $this->service->penawaranDefaultStateBySemester($this->kurikulum->id, 2, collect([$this->prodi->id]));
    $state = $this->service->flattenPenawaranBySemester($bySemester);
    $kalkulus = collect($state)->firstWhere('kode_penawaran', 'SET101');

    expect($state)->toHaveCount(2)
        ->and(collect($state)->pluck('kode_penawaran')->sort()->values()->all())->toBe(['SET101', 'SET102'])
        ->and($kalkulus['jumlah_kelas'])->toBe(2)
        ->and($kalkulus['dipilih'])->toBeFalse()
        ->and($kalkulus['label_mk'])->toBe('Kalkulus (SET101)');
});

it('penawaran default state mengelompokkan mk per semester ke', function () {
    $this->mkUnitA->update(['semester_ke' => 2]);
    $this->mkUnitB->update(['semester_ke' => 5]);

    $bySemester = $this->service->penawaranDefaultStateBySemester($this->kurikulum->id, 1, collect([$this->prodi->id]));

    expect($bySemester['2'])->toHaveCount(1)
        ->and($bySemester['5'])->toHaveCount(1)
        ->and($bySemester['2'][0]['label_mk'])->toBe('Kalkulus (SET101)');
});

it('penawaran default state menyertakan mk prodi bila kurikulum pada fakultas', function () {
    $fakultas = AcademicUnit::query()->where('type', 'faculty')->firstOrFail();
    $kurikulumFak = Kurikulum::query()->create([
        'academic_unit_id' => $fakultas->id,
        'nama' => 'Kurikulum Fakultas Uji',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    $state = $this->service->penawaranDefaultState($kurikulumFak->id, 1, collect([$this->prodi->id]));

    expect($state)->toHaveCount(2)
        ->and(collect($state)->pluck('kode_penawaran')->sort()->values()->all())->toBe(['SET101', 'SET102']);
});
