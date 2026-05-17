<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Support\AcademicUnitScope;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
});

it('userCanViewUnit mengizinkan lihat ancestor dari penugasan', function () {
    $adminProdi = User::where('username', 'adminprodi')->first();
    $jurusan = AcademicUnit::where('type', 'department')->first();

    expect(AcademicUnitScope::userCanViewUnit($adminProdi, $jurusan))->toBeTrue();
});

it('userHasPivotToUnitOrAncestor menolak kelola unit di atas penugasan', function () {
    $adminProdi = User::where('username', 'adminprodi')->first();
    $jurusan = AcademicUnit::where('type', 'department')->first();

    expect(AcademicUnitScope::userHasPivotToUnitOrAncestor($adminProdi, $jurusan))->toBeFalse();
});
