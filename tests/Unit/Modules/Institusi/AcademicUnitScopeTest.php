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
    $fakultas = AcademicUnit::where('type', 'faculty')->first();

    expect(AcademicUnitScope::userCanViewUnit($adminProdi, $fakultas))->toBeTrue();
});

it('userCanViewUnit menolak unit di luar rantai ancestor', function () {
    $adminProdi = User::where('username', 'adminprodi')->first();
    $jurusan = AcademicUnit::where('type', 'department')->first();

    // Jurusan bukan ancestor prodi — prodi berinduk langsung ke fakultas.
    expect(AcademicUnitScope::userCanViewUnit($adminProdi, $jurusan))->toBeFalse();
});

it('userHasPivotToUnitOrAncestor menolak kelola unit di atas penugasan', function () {
    $adminProdi = User::where('username', 'adminprodi')->first();
    $fakultas = AcademicUnit::where('type', 'faculty')->first();

    expect(AcademicUnitScope::userHasPivotToUnitOrAncestor($adminProdi, $fakultas))->toBeFalse();
});
