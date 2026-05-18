<?php

use App\Models\User;
use App\Modules\Audit\Filament\Resources\ActivityLogResource;
use App\Modules\Audit\Filament\Resources\ActivityLogResource\Pages\ListActivityLogs;
use App\Modules\Audit\Models\Activity;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\States\DraftState;
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

    $this->superadmin = User::query()->where('username', 'superadmin')->firstOrFail();
    $this->auditor = User::query()->where('username', 'auditor')->firstOrFail();
    $this->timkur = User::query()->where('username', 'timkur')->firstOrFail();
    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
});

it('logs kurikulum changes and shows them in activity log resource', function () {
    $kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Awal Audit',
        'kode' => 'KUR-AUDIT-01',
        'tahun' => 2025,
        'target_capaian_lulusan' => 75,
        'state' => DraftState::class,
        'is_active' => false,
    ]);

    $this->actingAs($this->superadmin);

    $kurikulum->update(['nama' => 'Kurikulum Diubah Audit']);

    $activity = Activity::query()
        ->where('subject_type', Kurikulum::class)
        ->where('subject_id', $kurikulum->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($this->superadmin->id)
        ->and($activity->properties['attributes']['nama'] ?? null)->toBe('Kurikulum Diubah Audit');

    Livewire::test(ListActivityLogs::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$activity])
        ->assertSee('Kurikulum Diubah Audit');
});

it('allows super admin and auditor to view activity logs', function () {
    $this->actingAs($this->superadmin);

    expect(ActivityLogResource::canViewAny())->toBeTrue();

    $this->actingAs($this->auditor);

    expect(ActivityLogResource::canViewAny())->toBeTrue();
});

it('denies tim kurikulum from viewing activity logs', function () {
    $this->actingAs($this->timkur);

    expect(ActivityLogResource::canViewAny())->toBeFalse();
});

it('does not log password changes on user update', function () {
    $this->actingAs($this->superadmin);

    $user = User::query()->where('username', 'dosen')->firstOrFail();
    $user->update(['password' => 'PasswordBaru2026!']);

    $activity = Activity::query()
        ->where('subject_type', User::class)
        ->where('subject_id', $user->id)
        ->latest('id')
        ->first();

    if ($activity !== null) {
        expect($activity->properties['attributes'] ?? [])
            ->not->toHaveKey('password')
            ->and($activity->properties['old'] ?? [])
            ->not->toHaveKey('password');
    }

    expect(true)->toBeTrue();
});
