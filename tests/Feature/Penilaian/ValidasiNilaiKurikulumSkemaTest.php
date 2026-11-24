<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Models\AcademicUnitUser;
use App\Modules\Kurikulum\Models\Kurikulum;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([AcademicUnitSeeder::class, RolePermissionSeeder::class, SemesterSeeder::class, EvaluasiSeeder::class]);
    require_once base_path('tests/Support/KelasValidasiFixture.php');
});

it('kurikulum baru default mewajibkan validasi', function () {
    $data = siapkanKelasValidasi(User::factory()->create());

    expect($data['kelas']->mkUnit->kurikulum->fresh()->validasi_nilai_aktif)->toBeTrue();
});

it('validatorTambahan menyimpan pengguna lewat pivot', function () {
    $data = siapkanKelasValidasi(User::factory()->create());
    $kurikulum = $data['kelas']->mkUnit->kurikulum;

    $kurikulum->validatorTambahan()->attach(User::factory()->create()->id);

    expect($kurikulum->validatorTambahan()->count())->toBe(1);
});

it('KelasMk::validasiDiwajibkan mengikuti switch kurikulum dan false tanpa kurikulum', function () {
    $data = siapkanKelasValidasi(User::factory()->create());
    $kelas = $data['kelas'];

    expect($kelas->validasiDiwajibkan())->toBeTrue();

    $kelas->mkUnit->kurikulum->update(['validasi_nilai_aktif' => false]);
    expect($kelas->fresh()->validasiDiwajibkan())->toBeFalse();

    $kelas->mkUnit->kurikulum->update(['validasi_nilai_aktif' => true]);
    DB::table('mk_units')->where('id', $kelas->mk_unit_id)->update(['kurikulum_id' => null]);
    expect($kelas->fresh()->validasiDiwajibkan())->toBeFalse();
});

it('migrasi menyalin validator lama menjadi validator tambahan pada kurikulum di unit dan turunannya', function () {
    $prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $prodi->id, 'nama' => 'K1', 'tahun' => 2026, 'is_active' => true,
    ]);
    $biasa = User::factory()->create();
    $pimpinan = User::factory()->create();

    AcademicUnitUser::query()->create([
        'academic_unit_id' => $prodi->id, 'user_id' => $biasa->id, 'status_validator_nilai' => true,
    ]);
    AcademicUnitUser::query()->create([
        'academic_unit_id' => $prodi->id, 'user_id' => $pimpinan->id,
        'status_validator_nilai' => true, 'status_pimpinan' => true,
    ]);
    DB::table('kurikulum_validator')->delete();

    (require database_path('migrations/2026_11_24_000001_tambah_validasi_nilai_ke_kurikulum.php'))->up();
    (require database_path('migrations/2026_11_24_000001_tambah_validasi_nilai_ke_kurikulum.php'))->up();

    $ids = DB::table('kurikulum_validator')->where('kurikulum_id', $kurikulum->id)->pluck('user_id')->all();
    expect($ids)->toContain($biasa->id)->not->toContain($pimpinan->id);
});
