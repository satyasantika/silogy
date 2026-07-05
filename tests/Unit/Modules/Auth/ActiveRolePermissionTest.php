<?php

use App\Models\User;
use App\Modules\Auth\Support\ActiveRole;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
});

it('userOwnsPermission membaca permission tanpa filter peran aktif', function () {
    $adminProdi = User::query()->where('username', 'adminprodi')->firstOrFail();

    expect(ActiveRole::userOwnsPermission($adminProdi, 'kelola_kelas'))->toBeTrue()
        ->and(ActiveRole::userOwnsRoleName($adminProdi, 'Admin'))->toBeTrue();
});

it('userOwnsRoleWithPermission false bila role tidak dimiliki', function () {
    $timkur = User::query()->where('username', 'timkur')->firstOrFail();

    expect(ActiveRole::userOwnsRoleWithPermission($timkur, 'Admin', 'kelola_kelas'))->toBeFalse()
        ->and(ActiveRole::userOwnsRoleWithPermission($timkur, 'Tim Kurikulum', 'kelola_kelas'))->toBeTrue();
});
