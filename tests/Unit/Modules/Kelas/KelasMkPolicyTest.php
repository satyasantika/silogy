<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Policies\KelasMkPolicy;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->policy = new KelasMkPolicy;
    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->first();
});

function createKelasMkForProdi(AcademicUnit $prodi): KelasMk
{
    $mk = Mk::factory()->create(['academic_unit_id' => $prodi->id]);
    $mkUnit = MkUnit::factory()->forMk($mk)->forAcademicUnit($prodi)->create();
    $semester = Semester::query()->firstOrCreate(
        ['kode' => '20251'],
        [
            'nama' => 'Ganjil 2025/2026',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2026,
            'jenis' => 'ganjil',
            'status_aktif' => true,
        ],
    );

    return KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $semester->id,
        'kode_kelas' => 'A',
    ]);
}

it('admin prodi dapat membuat dan mengelola kelas di prodinya', function () {
    $adminProdi = User::where('username', 'adminprodi')->first();
    $kelas = createKelasMkForProdi($this->prodi);

    expect($this->policy->viewAny($adminProdi))->toBeTrue()
        ->and($this->policy->create($adminProdi))->toBeTrue()
        ->and($this->policy->update($adminProdi, $kelas))->toBeTrue()
        ->and($this->policy->assignDosenPengampu($adminProdi, $kelas))->toBeTrue();
});

it('admin non-prodi tidak melihat daftar kelas mk', function () {
    $adminFak = User::where('username', 'adminfak')->first();

    expect($this->policy->viewAny($adminFak))->toBeFalse()
        ->and($this->policy->create($adminFak))->toBeFalse();
});

it('dosen pengampu hanya dapat mengelola kelas yang ditugaskan kepadanya', function () {
    $dosen = User::where('username', 'dosen')->first();
    $kelas = createKelasMkForProdi($this->prodi);
    $kelasLain = createKelasMkForProdi($this->prodi);
    $kelas->update(['dosen_pengampu_id' => $dosen->id]);

    expect($this->policy->update($dosen, $kelas))->toBeTrue()
        ->and($this->policy->update($dosen, $kelasLain))->toBeFalse()
        ->and($this->policy->assignDosenPengampu($dosen, $kelas))->toBeFalse();
});
