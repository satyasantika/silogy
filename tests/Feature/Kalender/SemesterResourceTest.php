<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Filament\Resources\SemesterResource;
use App\Modules\Kalender\Filament\Resources\SemesterResource\Pages\CreateSemester;
use App\Modules\Kalender\Filament\Resources\SemesterResource\Pages\EditSemester;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
});

it('super admin dapat membuat semester baru', function () {
    $this->actingAs(User::query()->where('username', 'superadmin')->firstOrFail());

    Livewire::test(CreateSemester::class)
        ->fillForm([
            'kode' => '2026G',
            'nama' => 'Ganjil 2026/2027',
            'jenis' => 'ganjil',
            'tahun_mulai' => 2026,
            'tahun_selesai' => 2027,
            'status_aktif' => false,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Semester::query()->where('kode', '2026G')->exists())->toBeTrue();
});

it('mengaktifkan semester menonaktifkan semester aktif lainnya', function () {
    $lama = Semester::query()->create([
        'kode' => 'LAMA', 'nama' => 'Semester Lama', 'jenis' => 'ganjil',
        'tahun_mulai' => 2024, 'tahun_selesai' => 2025, 'status_aktif' => true,
    ]);

    $this->actingAs(User::query()->where('username', 'superadmin')->firstOrFail());

    Livewire::test(CreateSemester::class)
        ->fillForm([
            'kode' => 'BARU',
            'nama' => 'Semester Baru',
            'jenis' => 'genap',
            'tahun_mulai' => 2026,
            'tahun_selesai' => 2027,
            'status_aktif' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect($lama->fresh()->status_aktif)->toBeFalse()
        ->and(Semester::query()->where('kode', 'BARU')->first()?->status_aktif)->toBeTrue();
});

it('role selain super admin tidak bisa mengakses resource semester', function () {
    foreach (['timkur', 'adminprodi', 'kaprodi'] as $username) {
        $user = User::query()->where('username', $username)->first();

        if (! $user) {
            continue;
        }

        $this->actingAs($user);

        expect(SemesterResource::canAccess())->toBeFalse("{$username} seharusnya ditolak")
            ->and(SemesterResource::shouldRegisterNavigation())->toBeFalse();
    }
});

it('semester yang masih dipakai kelas mk tidak bisa dihapus', function () {
    $semester = Semester::query()->create([
        'kode' => 'DIPAK', 'nama' => 'Semester Dipakai', 'jenis' => 'ganjil',
        'tahun_mulai' => 2026, 'tahun_selesai' => 2027, 'status_aktif' => false,
    ]);

    $prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $mk = Mk::factory()->forAcademicUnit($prodi)->create();
    $mkUnit = MkUnit::factory()->forMk($mk)->forAcademicUnit($prodi)->create();
    KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $semester->id,
        'kode_kelas' => 'A',
    ]);

    $this->actingAs(User::query()->where('username', 'superadmin')->firstOrFail());

    expect($semester->sedangDigunakan())->toBeTrue();

    Livewire::test(EditSemester::class, ['record' => $semester->id])
        ->assertActionHidden('delete');
});
