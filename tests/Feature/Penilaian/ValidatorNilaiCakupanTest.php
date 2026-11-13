<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Models\AcademicUnitUser;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use App\Modules\Penilaian\Policies\ValidasiNilaiPolicy;
use App\Modules\Penilaian\Support\ValidatorNilaiScope;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

require_once __DIR__.'/../../Support/KelasValidasiFixture.php';

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed([AcademicUnitSeeder::class, RolePermissionSeeder::class, SemesterSeeder::class, EvaluasiSeeder::class]));

it('validator prodi hanya mencakup kelas yang ditawarkan prodinya', function () {
    [$prodiA, $prodiB] = siapkanDuaProdi();
    $kelasA = siapkanKelasValidasi(User::factory()->create(), $prodiA)['kelas'];
    $kelasB = siapkanKelasValidasi(User::factory()->create(), $prodiB)['kelas'];
    $validator = User::factory()->create();
    jadikanValidator($validator, $prodiA);

    expect(ValidatorNilaiScope::kelasMkQuery($validator)->pluck('id')->all())->toBe([$kelasA->id]);
    expect(Gate::forUser($validator)->allows('lihatValidasiNilai', $kelasA))->toBeTrue();
    expect(Gate::forUser($validator)->allows('lihatValidasiNilai', $kelasB))->toBeFalse();
});

it('validator fakultas mencakup prodi di bawahnya, termasuk MK milik fakultas', function () {
    $prodi = AcademicUnit::where('type', 'study_program')->firstOrFail();
    $fakultas = $prodi;
    while ($fakultas->type !== 'faculty') {
        $fakultas = AcademicUnit::findOrFail($fakultas->parent_id);
    }
    $kelas = siapkanKelasValidasi(User::factory()->create(), $prodi, ['mk_pemilik' => $fakultas])['kelas'];
    $validator = User::factory()->create();
    jadikanValidator($validator, $fakultas);

    expect(ValidatorNilaiScope::unitIdsFor($validator)->all())->toContain($prodi->id, $fakultas->id);
    expect(Gate::forUser($validator)->allows('validasiNilai', $kelas))->toBeTrue();
});

it('penugasan tanpa penanda tidak memberi cakupan (multi-peran)', function () {
    [$prodiA, $prodiB] = siapkanDuaProdi();
    $kelasA = siapkanKelasValidasi(User::factory()->create(), $prodiA)['kelas'];
    $user = User::factory()->create();
    AcademicUnitUser::create(['academic_unit_id' => $prodiA->id, 'user_id' => $user->id]);
    jadikanValidator($user, $prodiB);

    expect(ValidatorNilaiScope::unitIdsFor($user)->all())->not->toContain($prodiA->id);
    expect(Gate::forUser($user)->allows('lihatValidasiNilai', $kelasA))->toBeFalse();
});

it('menolak kelas sendiri, kelas yang dikoordinatori, dan nilai belum lengkap', function () {
    $prodi = AcademicUnit::where('type', 'study_program')->firstOrFail();
    $validator = User::factory()->create();
    jadikanValidator($validator, $prodi);
    $policy = app(ValidasiNilaiPolicy::class);

    $sendiri = siapkanKelasValidasi($validator, $prodi)['kelas'];
    expect($policy->alasanTidakBisaValidasi($validator, $sendiri))->toBe('Anda dosen pengampu kelas ini.');

    $koordinasi = siapkanKelasValidasi(User::factory()->create(), $prodi, ['koordinator_mk_id' => $validator->id])['kelas'];
    expect($policy->alasanTidakBisaValidasi($validator, $koordinasi))->toBe('Anda koordinator mata kuliah ini.');

    $f = siapkanKelasValidasi(User::factory()->create(), $prodi);
    expect($policy->alasanTidakBisaValidasi($validator, $f['kelas']))->toBeNull();
    NilaiMahasiswa::where('kelas_mk_mahasiswa_id', $f['kmm'][0]->id)->first()->update(['nilai' => null]);
    expect($policy->alasanTidakBisaValidasi($validator, $f['kelas']->fresh()))->toBe('Nilai belum lengkap.');
});

it('menolak kelas di luar cakupan dengan alasan cakupan', function () {
    [$prodiA, $prodiB] = siapkanDuaProdi();
    $validator = User::factory()->create();
    jadikanValidator($validator, $prodiA);
    $kelasB = siapkanKelasValidasi(User::factory()->create(), $prodiB)['kelas'];

    expect(app(ValidasiNilaiPolicy::class)->alasanTidakBisaValidasi($validator, $kelasB))->toBe('Kelas di luar cakupan Anda.');
});

it('batalkan validasi ditolak untuk dosen pengampu dan koordinator kelas itu', function () {
    $prodi = AcademicUnit::where('type', 'study_program')->firstOrFail();
    $validator = User::factory()->create();
    jadikanValidator($validator, $prodi);
    $lain = siapkanKelasValidasi(User::factory()->create(), $prodi)['kelas'];
    $sendiri = siapkanKelasValidasi($validator, $prodi)['kelas'];
    $koordinasi = siapkanKelasValidasi(User::factory()->create(), $prodi, ['koordinator_mk_id' => $validator->id])['kelas'];
    $gate = Gate::forUser($validator);

    expect($gate->allows('batalkanValidasiNilai', $lain))->toBeTrue();
    expect($gate->allows('batalkanValidasiNilai', $sendiri))->toBeFalse();
    expect($gate->allows('batalkanValidasiNilai', $koordinasi))->toBeFalse();
});

it('Super Admin tanpa penanda tidak boleh memvalidasi', function () {
    $prodi = AcademicUnit::where('type', 'study_program')->firstOrFail();
    $kelas = siapkanKelasValidasi(User::factory()->create(), $prodi)['kelas'];
    $admin = User::where('username', 'admin')->first() ?? User::factory()->create();
    $admin->syncRoles(['Super Admin']);

    expect(Gate::forUser($admin)->allows('validasiNilai', $kelas))->toBeFalse();
    expect(Gate::forUser($admin)->allows('lihatValidasiNilai', $kelas))->toBeFalse();
});
