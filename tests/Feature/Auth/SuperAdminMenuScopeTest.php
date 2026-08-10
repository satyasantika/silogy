<?php

use App\Models\User;
use App\Modules\AI\Filament\Pages\RequestAnalisis;
use App\Modules\AI\Filament\Resources\AnalisisAiResource;
use App\Modules\Auth\Filament\Resources\PermissionResource;
use App\Modules\Auth\Filament\Resources\UserResource;
use App\Modules\BoK\Filament\Resources\BokResource;
use App\Modules\CPL\Filament\Resources\CplResource;
use App\Modules\Institusi\Filament\Resources\AcademicUnitResource;
use App\Modules\Kalender\Filament\Resources\SemesterResource;
use App\Modules\Kelas\Filament\Resources\KelasMkResource;
use App\Modules\Kurikulum\Filament\Pages\AnalisisMkProdi;
use App\Modules\Kurikulum\Filament\Pages\CplBokMatrix;
use App\Modules\Kurikulum\Filament\Pages\CplMkMatrix;
use App\Modules\Kurikulum\Filament\Pages\DaftarKurikulumSuperAdmin;
use App\Modules\Kurikulum\Filament\Pages\ProfilCplMatrix;
use App\Modules\Kurikulum\Filament\Resources\KurikulumResource;
use App\Modules\Kurikulum\Filament\Resources\ProfilLulusanResource;
use App\Modules\Mahasiswa\Filament\Resources\MahasiswaResource;
use App\Modules\MK\Filament\Pages\CplCpmkMatrix;
use App\Modules\MK\Filament\Pages\SubcpmkAsesmenMatrix;
use App\Modules\MK\Filament\Resources\CpmkResource;
use App\Modules\MK\Filament\Resources\MataKuliahKoordinatorResource;
use App\Modules\MK\Filament\Resources\MkResource;
use App\Modules\MK\Filament\Resources\MkUnitResource;
use App\Modules\MK\Filament\Resources\SubcpmkResource;
use App\Modules\Penilaian\Filament\Pages\InputNilai;
use App\Modules\Penilaian\Filament\Pages\LaporanKoordinator;
use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource;
use App\Modules\Penilaian\Filament\Resources\PenilaianDosenResource;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(User::query()->where('username', 'superadmin')->firstOrFail());
});

it('superadmin hanya bisa mengakses Unit Akademik, Semester, Kurikulum (lihat+reset), Pengguna, Mahasiswa, dan Log Aktivitas', function () {
    expect(AcademicUnitResource::canAccess())->toBeTrue()
        ->and(SemesterResource::canAccess())->toBeTrue()
        ->and(DaftarKurikulumSuperAdmin::canAccess())->toBeTrue()
        ->and(UserResource::canAccess())->toBeTrue()
        ->and(MahasiswaResource::canAccess())->toBeTrue();
    // Log Aktivitas (ActivityLogResource) sudah ditutupi ActivityLogPolicyTest.
});

it('superadmin tidak lagi bisa mengakses resource/page akademik & operasional lainnya', function () {
    $resources = [
        CplResource::class,
        BokResource::class,
        MkResource::class,
        MkUnitResource::class,
        KurikulumResource::class,
        ProfilLulusanResource::class,
        CpmkResource::class,
        SubcpmkResource::class,
        KomponenPenilaianResource::class,
        KelasMkResource::class,
        PenilaianDosenResource::class,
        MataKuliahKoordinatorResource::class,
        AnalisisAiResource::class,
        PermissionResource::class,
    ];

    foreach ($resources as $resource) {
        expect($resource::canAccess())->toBeFalse("{$resource} seharusnya menolak Super Admin");
    }

    $pages = [
        RequestAnalisis::class,
        AnalisisMkProdi::class,
        LaporanKoordinator::class,
        CplBokMatrix::class,
        CplMkMatrix::class,
        ProfilCplMatrix::class,
        CplCpmkMatrix::class,
        SubcpmkAsesmenMatrix::class,
        InputNilai::class,
    ];

    foreach ($pages as $page) {
        expect($page::canAccess())->toBeFalse("{$page} seharusnya menolak Super Admin");
    }
});
