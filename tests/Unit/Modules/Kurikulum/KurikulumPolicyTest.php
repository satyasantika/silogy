<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Models\ProfilLulusan;
use App\Modules\Kurikulum\Policies\KurikulumPolicy;
use App\Modules\Kurikulum\States\DraftState;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
});

it('mengizinkan tim kurikulum mengelola kurikulum di unitnya', function () {
    $prodi = AcademicUnit::query()->where('type', 'study_program')->first();
    $timkur = User::query()->where('username', 'timkur')->first();

    $kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $prodi->id,
        'nama' => 'Kurikulum IF 2025',
        'tahun' => 2025,
        'state' => DraftState::class,
        'is_active' => false,
    ]);

    $policy = new KurikulumPolicy;

    expect($policy->manage($timkur, $kurikulum))->toBeTrue();
});

it('menolak user berpenugasan yang bukan tim kurikulum ataupun admin', function () {
    $prodi = AcademicUnit::query()->where('type', 'study_program')->first();
    // Pimpinan punya pivot di prodi namun bukan tim kurikulum/Admin —
    // kategori Kurikulum didelegasikan hanya ke keduanya.
    $kaprodi = User::query()->where('username', 'kaprodi')->first();

    $kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $prodi->id,
        'nama' => 'Kurikulum IF 2025',
        'tahun' => 2025,
        'state' => DraftState::class,
        'is_active' => false,
    ]);

    $policy = new KurikulumPolicy;

    expect($policy->manage($kaprodi, $kurikulum))->toBeFalse()
        ->and($policy->manage(User::where('username', 'adminprodi')->first(), $kurikulum))->toBeTrue();
});

it('mengizinkan tim kurikulum menghapus kurikulum yang belum dipakai di tabel lain', function () {
    $prodi = AcademicUnit::query()->where('type', 'study_program')->first();
    $timkur = User::query()->where('username', 'timkur')->first();

    $kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $prodi->id,
        'nama' => 'Kurikulum IF 2025',
        'tahun' => 2025,
        'state' => DraftState::class,
        'is_active' => false,
    ]);

    $policy = new KurikulumPolicy;

    expect($policy->delete($timkur, $kurikulum))->toBeTrue();
});

it('menolak tim kurikulum menghapus kurikulum yang sudah punya profil lulusan', function () {
    $prodi = AcademicUnit::query()->where('type', 'study_program')->first();
    $timkur = User::query()->where('username', 'timkur')->first();

    $kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $prodi->id,
        'nama' => 'Kurikulum IF 2025',
        'tahun' => 2025,
        'state' => DraftState::class,
        'is_active' => false,
    ]);

    ProfilLulusan::query()->create([
        'kurikulum_id' => $kurikulum->id,
        'kode' => 'PL-01',
        'deskripsi' => 'Profil uji',
    ]);

    $policy = new KurikulumPolicy;

    expect($policy->delete($timkur, $kurikulum->fresh()))->toBeFalse();
});
