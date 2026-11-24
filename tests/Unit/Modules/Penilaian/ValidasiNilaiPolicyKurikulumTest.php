<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Models\AcademicUnitUser;
use App\Modules\Penilaian\Policies\ValidasiNilaiPolicy;
use App\Modules\Penilaian\Support\ValidatorNilaiScope;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/../../../Support/KelasValidasiFixture.php';

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(fn () => $this->seed([AcademicUnitSeeder::class, RolePermissionSeeder::class, SemesterSeeder::class, EvaluasiSeeder::class]));

it('pimpinan dan tim kurikulum prodi berhak otomatis, pengguna lain tidak', function () {
    $prodi = AcademicUnit::where('type', 'study_program')->firstOrFail();
    $kelas = siapkanKelasValidasi(User::factory()->create(), $prodi)['kelas'];
    $kurikulum = $kelas->mkUnit->kurikulum;
    $pimpinan = User::factory()->create();
    $tim = User::factory()->create();
    $biasa = User::factory()->create();
    AcademicUnitUser::create(['academic_unit_id' => $prodi->id, 'user_id' => $pimpinan->id, 'status_pimpinan' => true]);
    AcademicUnitUser::create(['academic_unit_id' => $prodi->id, 'user_id' => $tim->id, 'status_tim_kurikulum' => true]);

    expect(ValidatorNilaiScope::berhakPadaKurikulum($pimpinan, $kurikulum))->toBeTrue()
        ->and(ValidatorNilaiScope::berhakPadaKurikulum($tim, $kurikulum))->toBeTrue()
        ->and(ValidatorNilaiScope::berhakPadaKurikulum($biasa, $kurikulum))->toBeFalse()
        ->and(ValidatorNilaiScope::otomatisBerhakUserIds($kurikulum)->all())
        ->toContain($pimpinan->id, $tim->id)->not->toContain($biasa->id)
        ->and(ValidatorNilaiScope::punyaHakValidasi($pimpinan))->toBeTrue()
        ->and(ValidatorNilaiScope::punyaHakValidasi($biasa))->toBeFalse();
});

it('validator tambahan berhak, switch mati menghapus hak semua', function () {
    $prodi = AcademicUnit::where('type', 'study_program')->firstOrFail();
    $kelas = siapkanKelasValidasi(User::factory()->create(), $prodi)['kelas'];
    $kurikulum = $kelas->mkUnit->kurikulum;
    $pimpinan = User::factory()->create();
    $tim = User::factory()->create();
    $tambahan = User::factory()->create();
    AcademicUnitUser::create(['academic_unit_id' => $prodi->id, 'user_id' => $pimpinan->id, 'status_pimpinan' => true]);
    AcademicUnitUser::create(['academic_unit_id' => $prodi->id, 'user_id' => $tim->id, 'status_tim_kurikulum' => true]);
    $kurikulum->validatorTambahan()->attach($tambahan->id);

    expect(ValidatorNilaiScope::berhakPadaKurikulum($tambahan, $kurikulum))->toBeTrue()
        ->and(ValidatorNilaiScope::kelasMkQuery($tambahan)->count())->toBe(1);

    $kurikulum->update(['validasi_nilai_aktif' => false]);
    $kurikulum->refresh();

    foreach ([$pimpinan, $tim, $tambahan] as $user) {
        expect(ValidatorNilaiScope::berhakPadaKurikulum($user, $kurikulum))->toBeFalse()
            ->and(ValidatorNilaiScope::kelasMkQuery($user)->count())->toBe(0)
            ->and(ValidatorNilaiScope::punyaHakValidasi($user))->toBeFalse();
    }
});

it('pimpinan/tim kurikulum unit induk dan admin tidak otomatis berhak', function () {
    $prodi = AcademicUnit::where('type', 'study_program')->firstOrFail();
    $fakultas = AcademicUnit::findOrFail($prodi->parent_id);
    $kelas = siapkanKelasValidasi(User::factory()->create(), $prodi)['kelas'];
    $kurikulum = $kelas->mkUnit->kurikulum;
    $pimpinanFakultas = User::factory()->create();
    AcademicUnitUser::create(['academic_unit_id' => $fakultas->id, 'user_id' => $pimpinanFakultas->id, 'status_pimpinan' => true]);
    $admin = User::factory()->create();
    $admin->syncRoles(['Admin']);
    $superAdmin = User::factory()->create();
    $superAdmin->syncRoles(['Super Admin']);

    foreach ([$pimpinanFakultas, $admin, $superAdmin] as $user) {
        expect(ValidatorNilaiScope::berhakPadaKurikulum($user, $kurikulum))->toBeFalse()
            ->and(ValidatorNilaiScope::kelasMkQuery($user)->count())->toBe(0);
    }
});

it('kelas tanpa kurikulum tidak masuk cakupan dan tidak crash', function () {
    $prodi = AcademicUnit::where('type', 'study_program')->firstOrFail();
    $kelas = siapkanKelasValidasi(User::factory()->create(), $prodi)['kelas'];
    $validator = User::factory()->create();
    AcademicUnitUser::create(['academic_unit_id' => $prodi->id, 'user_id' => $validator->id, 'status_pimpinan' => true]);
    expect(ValidatorNilaiScope::kelasMkQuery($validator)->count())->toBe(1);

    $kelas->mkUnit->update(['kurikulum_id' => null]);

    expect(ValidatorNilaiScope::kelasMkQuery($validator)->count())->toBe(0);
});

it('koordinator yang juga tim kurikulum ditolak di kelas MK-nya tetapi boleh di MK lain', function () {
    $prodi = AcademicUnit::where('type', 'study_program')->firstOrFail();
    $user = User::factory()->create();
    $koordinasi = siapkanKelasValidasi(User::factory()->create(), $prodi, ['koordinator_mk_id' => $user->id])['kelas'];
    $lain = siapkanKelasValidasi(User::factory()->create(), $prodi)['kelas'];
    AcademicUnitUser::create(['academic_unit_id' => $prodi->id, 'user_id' => $user->id, 'status_tim_kurikulum' => true]);
    // Peran diberikan setelah penulisan penugasan (sinkronisasi lama mencabutnya).
    $user->roles()->syncWithoutDetaching([\App\Models\Role::where('name', 'Validator Nilai')->firstOrFail()->id]);
    $policy = app(ValidasiNilaiPolicy::class);

    expect($policy->validasi($user, $koordinasi))->toBeFalse()
        ->and($policy->alasanTidakBisaValidasi($user, $koordinasi))->toBe('Anda koordinator mata kuliah ini.')
        ->and($policy->validasi($user, $lain))->toBeTrue();
});
