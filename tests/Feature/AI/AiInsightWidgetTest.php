<?php

use App\Filament\Pages\Dashboard;
use App\Models\User;
use App\Modules\AI\Exceptions\GeminiQuotaExceededException;
use App\Modules\AI\Filament\Widgets\AiInsightWidget;
use App\Modules\AI\Models\AnalisisAi;
use App\Modules\AI\Services\GeminiCostGuard;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalkulasi\Models\HasilCplUnit;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SetsUpKalkulasiFixtures;

uses(RefreshDatabase::class, SetsUpKalkulasiFixtures::class);

beforeEach(function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SemesterSeeder::class);
    $this->seedKalkulasiBase();

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('displays latest ai insight for kaprodi', function () {
    $kaprodi = User::where('username', 'kaprodi')->firstOrFail();
    $dasar = $this->createKelasPenilaianDasar();
    $prodi = AcademicUnit::query()->findOrFail($dasar['mk']->academic_unit_id);

    $this->buatKurikulumAktif($prodi);

    HasilCplUnit::query()->create([
        'cpl_id' => $dasar['cpl']->id,
        'academic_unit_id' => $prodi->id,
        'semester_id' => $dasar['semester']->id,
        'rata_rata' => 80,
        'persentase_tercapai' => 85,
        'jumlah_mahasiswa' => 1,
    ]);

    AnalisisAi::query()->create([
        'academic_unit_id' => $prodi->id,
        'semester_id' => $dasar['semester']->id,
        'jenis' => 'ringkasan_cpl',
        'status' => 'completed',
        'prompt' => 'prompt uji',
        'hasil' => "### Ringkasan CPL Prodi\n\nCapaian memadai untuk semester ini.",
        'model_ai' => 'gemini-2.5-flash',
        'token_digunakan' => 120,
        'durasi_ms' => 50,
        'dibuat_oleh' => $kaprodi->id,
    ]);

    $this->actingAs($kaprodi);

    Livewire::test(Dashboard::class)
        ->assertSuccessful()
        ->assertSee('Insight AI');

    Livewire::test(AiInsightWidget::class, [
        'pageFilters' => [
            'academic_unit_id' => $prodi->id,
            'semester_id' => $dasar['semester']->id,
            'cpl_id' => $dasar['cpl']->id,
        ],
    ])
        ->assertSuccessful()
        ->assertSee('Ringkasan CPL Prodi')
        ->assertSee('Capaian memadai');
});

it('blocks when user exceeds daily quota', function () {
    $kaprodi = User::where('username', 'kaprodi')->firstOrFail();
    $prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $semester = \App\Modules\Kalender\Models\Semester::query()->where('status_aktif', true)->firstOrFail();
    $guard = app(GeminiCostGuard::class);

    for ($i = 0; $i < GeminiCostGuard::MAX_REQUESTS_PER_USER_PER_DAY; $i++) {
        AnalisisAi::query()->create([
            'academic_unit_id' => $prodi->id,
            'semester_id' => $semester->id,
            'jenis' => 'ringkasan_cpl',
            'status' => 'queued',
            'prompt' => '-',
            'dibuat_oleh' => $kaprodi->id,
        ]);
    }

    expect(fn () => $guard->check($kaprodi, $prodi))
        ->toThrow(GeminiQuotaExceededException::class);
});

it('blocks when monthly token budget exhausted', function () {
    config(['services.gemini.monthly_token_budget' => 1_000]);

    $kaprodi = User::where('username', 'kaprodi')->firstOrFail();
    $prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $semester = \App\Modules\Kalender\Models\Semester::query()->where('status_aktif', true)->firstOrFail();
    $guard = app(GeminiCostGuard::class);

    AnalisisAi::query()->create([
        'academic_unit_id' => $prodi->id,
        'semester_id' => $semester->id,
        'jenis' => 'ringkasan_cpl',
        'status' => 'completed',
        'prompt' => '-',
        'hasil' => 'ok',
        'token_digunakan' => 1_000,
        'dibuat_oleh' => $kaprodi->id,
    ]);

    expect(fn () => $guard->check($kaprodi, $prodi))
        ->toThrow(GeminiQuotaExceededException::class);

    $this->actingAs($kaprodi);

    Livewire::test(AiInsightWidget::class, [
        'pageFilters' => [
            'academic_unit_id' => $prodi->id,
            'semester_id' => $semester->id,
        ],
    ])
        ->call('mintaAnalisisSekarang')
        ->assertNotified();
});
