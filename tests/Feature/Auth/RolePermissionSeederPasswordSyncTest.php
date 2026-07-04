<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('RolePermissionSeeder menyinkronkan kata sandi akun demo walau pengguna sudah ada', function (): void {
    $this->seed(AcademicUnitSeeder::class);

    User::query()->create([
        'username' => 'superadmin',
        'email' => 'superadmin@silogy.test',
        'full_name' => 'Legacy',
        'nidn' => null,
        'password' => 'kata-sandi-salah-sebelum-seed',
    ]);

    $this->seed(RolePermissionSeeder::class);

    $user = User::query()->where('username', 'superadmin')->firstOrFail();

    expect(Hash::check('siliwangi', $user->password))->toBeTrue()
        ->and(Hash::check('kata-sandi-salah-sebelum-seed', $user->password))->toBeFalse();
});
