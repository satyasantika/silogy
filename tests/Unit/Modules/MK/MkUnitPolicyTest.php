<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\MK\Filament\Resources\MkUnitResource;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Policies\MkUnitPolicy;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);

    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->fakultas = AcademicUnit::query()->where('type', 'faculty')->firstOrFail();
    $this->univ = AcademicUnit::query()->where('type', 'university')->firstOrFail();
    $this->policy = new MkUnitPolicy;
});

it('tim kurikulum prodi dapat mengelola penawaran mk', function () {
    $timkur = User::query()->where('username', 'timkur')->firstOrFail();
    $mkUnit = MkUnit::factory()
        ->forMk(Mk::factory()->create(['academic_unit_id' => $this->prodi->id]))
        ->forAcademicUnit($this->prodi)
        ->create();

    expect($this->policy->viewAny($timkur))->toBeTrue()
        ->and($this->policy->create($timkur))->toBeTrue()
        ->and($this->policy->manage($timkur, $mkUnit))->toBeTrue();
});

it('tim kurikulum fakultas dan universitas tidak dapat mengelola penawaran mk', function () {
    $mkUnit = MkUnit::factory()
        ->forMk(Mk::factory()->create(['academic_unit_id' => $this->prodi->id]))
        ->forAcademicUnit($this->prodi)
        ->create();

    foreach (['timkurfak', 'timkuruniv'] as $username) {
        $user = User::query()->where('username', $username)->firstOrFail();

        expect($this->policy->viewAny($user))->toBeFalse()
            ->and($this->policy->create($user))->toBeFalse()
            ->and($this->policy->manage($user, $mkUnit))->toBeFalse();
    }
});

it('admin prodi dapat mengelola penawaran mk namun admin fakultas tidak', function () {
    $mkUnit = MkUnit::factory()
        ->forMk(Mk::factory()->create(['academic_unit_id' => $this->prodi->id]))
        ->forAcademicUnit($this->prodi)
        ->create();

    $adminProdi = User::query()->where('username', 'adminprodi')->firstOrFail();
    $adminFak = User::query()->where('username', 'adminfak')->firstOrFail();

    expect($this->policy->viewAny($adminProdi))->toBeTrue()
        ->and($this->policy->manage($adminProdi, $mkUnit))->toBeTrue()
        ->and($this->policy->viewAny($adminFak))->toBeFalse()
        ->and($this->policy->manage($adminFak, $mkUnit))->toBeFalse();
});

it('menu penawaran mk hanya tampil untuk tim kurikulum prodi', function () {
    $this->actingAs(User::query()->where('username', 'timkur')->firstOrFail());
    expect(MkUnitResource::shouldRegisterNavigation())->toBeTrue();

    $this->actingAs(User::query()->where('username', 'timkurfak')->firstOrFail());
    expect(MkUnitResource::shouldRegisterNavigation())->toBeFalse();

    $this->actingAs(User::query()->where('username', 'timkuruniv')->firstOrFail());
    expect(MkUnitResource::shouldRegisterNavigation())->toBeFalse();
});

it('koordinator mk melihat menu penawaran mk namun tidak dapat mengedit', function () {
    $korma = User::query()->where('username', 'korma')->firstOrFail();
    $mk = Mk::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'koordinator_mk_id' => $korma->id,
    ]);
    $mkUnit = MkUnit::factory()->forMk($mk)->forAcademicUnit($this->prodi)->create(['kode' => 'KOR901']);

    expect($this->policy->viewAny($korma))->toBeTrue()
        ->and($this->policy->view($korma, $mkUnit))->toBeTrue()
        ->and($this->policy->update($korma, $mkUnit))->toBeFalse()
        ->and($this->policy->create($korma))->toBeFalse();

    $this->actingAs($korma);
    expect(MkUnitResource::shouldRegisterNavigation())->toBeFalse();
});
