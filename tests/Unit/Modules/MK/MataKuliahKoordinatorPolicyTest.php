<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Models\AcademicUnitUser;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\MK\Filament\Resources\MataKuliahKoordinatorResource;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Policies\MataKuliahKoordinatorPolicy;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);

    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Policy Korma',
        'tahun' => 2026,
        'is_active' => true,
    ]);
    $this->policy = app(MataKuliahKoordinatorPolicy::class);
});

/**
 * User dengan role Koordinator Mata Kuliah tanpa pivot academic_unit_users.
 */
function buatUserKormaTanpaPivot(): User
{
    $user = User::query()->create([
        'id' => (string) Str::uuid(),
        'username' => 'korma_tanpa_pivot',
        'full_name' => 'Korma Tanpa Pivot',
        'email' => 'korma.tanpa.pivot@example.test',
        'password' => Hash::make('password'),
        'is_active' => true,
    ]);
    $user->assignRole('Koordinator Mata Kuliah');

    return $user;
}

it('mengizinkan akses bila role korma dan ditugaskan di mk tanpa pivot prodi', function () {
    $user = buatUserKormaTanpaPivot();

    expect(AcademicUnitUser::query()->where('user_id', $user->id)->exists())->toBeFalse()
        ->and($this->policy->viewAny($user))->toBeFalse();

    $mk = Mk::factory()->forKurikulum($this->kurikulum)->create([
        'koordinator_mk_id' => $user->id,
    ]);

    expect($this->policy->viewAny($user->fresh()))->toBeTrue()
        ->and($this->policy->view($user->fresh(), $mk))->toBeTrue()
        ->and(MataKuliahKoordinatorResource::canAccess())->toBeFalse(); // belum actingAs

    $this->actingAs($user);
    expect(MataKuliahKoordinatorResource::canAccess())->toBeTrue()
        ->and(MataKuliahKoordinatorResource::shouldRegisterNavigation())->toBeTrue();
});

it('mengizinkan multi-role: korma sekaligus Admin asal ada penugasan mk', function () {
    $user = buatUserKormaTanpaPivot();
    $user->assignRole('Admin');

    Mk::factory()->forKurikulum($this->kurikulum)->create([
        'koordinator_mk_id' => $user->id,
    ]);

    expect($user->fresh()->hasRole('Koordinator Mata Kuliah'))->toBeTrue()
        ->and($user->fresh()->hasRole('Admin'))->toBeTrue()
        ->and($this->policy->viewAny($user->fresh()))->toBeTrue();
});

it('menolak akses bila hanya punya role korma tanpa penugasan mk maupun kelas', function () {
    $user = buatUserKormaTanpaPivot();

    expect($this->policy->viewAny($user))->toBeFalse();
});

it('menolak akses Admin tanpa role Koordinator Mata Kuliah', function () {
    $admin = User::query()->where('username', 'adminprodi')->firstOrFail();

    expect($admin->hasRole('Admin'))->toBeTrue()
        ->and($admin->hasRole('Koordinator Mata Kuliah'))->toBeFalse()
        ->and($this->policy->viewAny($admin))->toBeFalse();
});

it('menolak view mk yang bukan ditugaskan kepada user', function () {
    $user = buatUserKormaTanpaPivot();
    Mk::factory()->forKurikulum($this->kurikulum)->create([
        'koordinator_mk_id' => $user->id,
    ]);

    $mkLain = Mk::factory()->forKurikulum($this->kurikulum)->create([
        'nama' => 'MK Bukan Miliknya',
    ]);

    expect($this->policy->viewAny($user->fresh()))->toBeTrue()
        ->and($this->policy->view($user->fresh(), $mkLain))->toBeFalse();
});
