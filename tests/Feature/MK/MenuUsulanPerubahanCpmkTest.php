<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Models\AcademicUnitUser;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Filament\Resources\MkUnitResource;
use App\Modules\MK\Filament\Resources\PerubahanCpmkResource;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\PerubahanCpmkRequest;
use App\Modules\MK\Services\PerubahanCpmkService;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SemesterSeeder::class);

    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->korma = User::query()->where('username', 'korma')->firstOrFail();
    $this->timkur = User::query()->where('username', 'timkur')->firstOrFail();
    $this->timkurFak = User::query()->where('username', 'timkurfak')->firstOrFail();
    $this->semester = Semester::query()->where('status_aktif', true)->firstOrFail();

    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Uji Menu Usulan',
        'kode' => 'MNUSL',
        'tahun' => 2026,
        'is_active' => true,
    ]);
    $this->mk = Mk::factory()->forKurikulum($this->kurikulum)->create([
        'koordinator_mk_id' => $this->korma->id,
        'academic_unit_id' => $this->prodi->id,
    ]);

    cpmkUntukSemester($this->mk, $this->semester, ['kode' => 'CPMK01']);
});

function ajukanUsulanUji(object $konteks): void
{
    app(PerubahanCpmkService::class)
        ->ajukan($konteks->mk, $konteks->semester->id, $konteks->korma, 'Rumusan CPMK perlu disesuaikan.');
}

it('tidak muncul di menu Tim Kurikulum selama belum ada usulan dari Koordinator MK', function () {
    $this->actingAs($this->timkur);
    KurikulumTerpilih::set($this->kurikulum->id);

    expect(PerubahanCpmkResource::shouldRegisterNavigation())->toBeFalse();
});

it('muncul di menu Tim Kurikulum begitu Koordinator MK mengajukan usulan', function () {
    ajukanUsulanUji($this);

    $this->actingAs($this->timkur);
    KurikulumTerpilih::set($this->kurikulum->id);

    expect(PerubahanCpmkResource::shouldRegisterNavigation())->toBeTrue()
        ->and(PerubahanCpmkResource::getNavigationBadge())->toBe('1');
});

it('muncul juga bagi Tim Kurikulum fakultas (unit induk) yang berwenang meninjau', function () {
    ajukanUsulanUji($this);

    $this->actingAs($this->timkurFak);

    expect(PerubahanCpmkResource::shouldRegisterNavigation())->toBeTrue();
});

it('menghilang lagi dari menu setelah usulan diputuskan', function () {
    ajukanUsulanUji($this);
    $usulan = PerubahanCpmkRequest::query()->firstOrFail();

    app(PerubahanCpmkService::class)->setujui($usulan, $this->timkur, 'Silakan.');

    $this->actingAs($this->timkur);
    KurikulumTerpilih::set($this->kurikulum->id);

    expect(PerubahanCpmkResource::shouldRegisterNavigation())->toBeFalse()
        // Halamannya tetap bisa dibuka lewat URL: riwayat tidak kehilangan akses.
        ->and(PerubahanCpmkResource::canAccess())->toBeTrue();
});

it('tidak pernah muncul di menu Koordinator MK walau ia punya usulan sendiri', function () {
    ajukanUsulanUji($this);

    $this->actingAs($this->korma);

    expect(PerubahanCpmkResource::shouldRegisterNavigation())->toBeFalse();
});

it('tidak muncul bagi Tim Kurikulum prodi lain yang tidak berwenang atas MK tersebut', function () {
    ajukanUsulanUji($this);

    $prodiLain = AcademicUnit::query()->create([
        'parent_id' => $this->prodi->parent_id,
        'type' => 'study_program',
        'nama' => 'Program Studi Pembanding',
        'kode' => 'PSBAND',
        'status' => 'aktif',
    ]);

    $timkurLain = User::factory()->create();
    $timkurLain->assignRole('Tim Kurikulum');
    AcademicUnitUser::query()->create([
        'academic_unit_id' => $prodiLain->id,
        'user_id' => $timkurLain->id,
        'status_tim_kurikulum' => true,
    ]);

    $this->actingAs($timkurLain->fresh());

    expect(PerubahanCpmkResource::shouldRegisterNavigation())->toBeFalse();
});

it('tidak muncul bagi Super Admin', function () {
    ajukanUsulanUji($this);

    $this->actingAs(User::query()->where('username', 'superadmin')->firstOrFail());

    expect(PerubahanCpmkResource::shouldRegisterNavigation())->toBeFalse();
});

it('berada tepat di bawah Penawaran MK pada menu Tim Kurikulum prodi', function () {
    ajukanUsulanUji($this);

    $this->actingAs($this->timkur);
    KurikulumTerpilih::set($this->kurikulum->id);

    $urutanPenawaran = MkUnitResource::getNavigationSort();
    $urutanUsulan = PerubahanCpmkResource::getNavigationSort();

    expect($urutanUsulan)->toBeGreaterThan($urutanPenawaran);

    // Tidak ada menu lain yang tampil dan menyelip di antara keduanya.
    $menuDiAntara = collect(Filament::getPanel('admin')->getResources())
        ->reject(fn (string $resource): bool => in_array($resource, [MkUnitResource::class, PerubahanCpmkResource::class], true))
        ->filter(fn (string $resource): bool => $resource::shouldRegisterNavigation())
        ->filter(fn (string $resource): bool => $resource::getNavigationSort() > $urutanPenawaran
            && $resource::getNavigationSort() < $urutanUsulan);

    expect($menuDiAntara->all())->toBe([]);
});
