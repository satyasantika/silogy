<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

/**
 * Menyediakan peran "Validator Nilai" di basis data yang sudah berjalan.
 * Idempoten; memakai givePermissionTo (bukan sync) agar penyesuaian izin
 * di produksi tidak tertimpa.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['validasi_nilai', 'lihat_laporan', 'lihat_dashboard'] as $izin) {
            Permission::firstOrCreate(['name' => $izin, 'guard_name' => 'web']);
        }

        $peran = Role::firstOrCreate(['name' => 'Validator Nilai', 'guard_name' => 'web']);
        $peran->givePermissionTo(['validasi_nilai', 'lihat_laporan', 'lihat_dashboard']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        //
    }
};
