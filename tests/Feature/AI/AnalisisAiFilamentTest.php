<?php

use App\Models\User;
use App\Modules\AI\Filament\Pages\RequestAnalisis;
use App\Modules\AI\Filament\Resources\AnalisisAiResource\Pages\ListAnalisisAis;
use App\Modules\AI\Jobs\RunAnalisisAiJob;
use App\Modules\AI\Models\AnalisisAi;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kalkulasi\Models\HasilCplUnit;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Filament\Facades\Filament;
use Gemini\Contracts\ClientContract;
use Gemini\Responses\GenerativeModel\GenerateContentResponse;
use Gemini\Testing\ClientFake;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SetsUpKalkulasiFixtures;

uses(RefreshDatabase::class, SetsUpKalkulasiFixtures::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(SemesterSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->seedKalkulasiBase();
});

it('allows rektor to access request analisis page', function () {
    $rektor = User::where('username', 'rektor')->firstOrFail();

    $this->actingAs($rektor);

    Livewire::test(RequestAnalisis::class)
        ->assertSuccessful();
});

it('schedules analisis from request page and saves result via job', function () {
    $rektor = User::where('username', 'rektor')->firstOrFail();
    $univ = AcademicUnit::query()->where('type', 'university')->firstOrFail();
    $semester = Semester::query()->where('status_aktif', true)->firstOrFail();

    $dasar = $this->createKelasPenilaianDasar();
    $prodi = AcademicUnit::query()->findOrFail($dasar['mk']->academic_unit_id);
    $this->buatKurikulumAktif($prodi, 75);

    HasilCplUnit::query()->create([
        'cpl_id' => $dasar['cpl']->id,
        'academic_unit_id' => $univ->id,
        'semester_id' => $semester->id,
        'rata_rata' => 78,
        'persentase_tercapai' => 80,
        'jumlah_mahasiswa' => 5,
    ]);

    app()->instance(ClientContract::class, new ClientFake([
        GenerateContentResponse::fake([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [['text' => '### Ringkasan universitas']],
                        'role' => 'model',
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
            'usageMetadata' => [
                'promptTokenCount' => 50,
                'candidatesTokenCount' => 100,
                'totalTokenCount' => 150,
            ],
        ]),
    ]));

    $this->actingAs($rektor);

    Livewire::test(RequestAnalisis::class)
        ->fillForm([
            'academic_unit_id' => $univ->id,
            'semester_id' => $semester->id,
            'jenis' => 'ringkasan_cpl',
        ])
        ->call('submit')
        ->assertNotified();

    $analisis = AnalisisAi::query()->latest()->first();

    expect($analisis)->not->toBeNull()
        ->and($analisis->academic_unit_id)->toBe($univ->id);

    if ($analisis->status === 'queued') {
        RunAnalisisAiJob::dispatchSync($analisis->id);
        $analisis->refresh();
    }

    expect(in_array($analisis->status, ['queued', 'running', 'completed'], true))->toBeTrue()
        ->and($analisis->status)->toBe('completed')
        ->and($analisis->hasil)->toContain('### Ringkasan universitas');
});

it('shows analisis list for rektor', function () {
    $rektor = User::where('username', 'rektor')->firstOrFail();

    $this->actingAs($rektor);

    Livewire::test(ListAnalisisAis::class)
        ->assertSuccessful();
});

it('marks failed status when gemini errors', function () {
    $rektor = User::where('username', 'rektor')->firstOrFail();
    $univ = AcademicUnit::query()->where('type', 'university')->firstOrFail();
    $semester = Semester::query()->where('status_aktif', true)->firstOrFail();

    app()->instance(ClientContract::class, new ClientFake([]));

    $analisis = AnalisisAi::query()->create([
        'academic_unit_id' => $univ->id,
        'semester_id' => $semester->id,
        'jenis' => 'ringkasan_cpl',
        'status' => 'queued',
        'prompt' => '-',
        'dibuat_oleh' => $rektor->id,
    ]);

    try {
        RunAnalisisAiJob::dispatchSync($analisis->id);
    } catch (Throwable) {
        //
    }

    $job = new RunAnalisisAiJob($analisis->id);
    $job->failed(new RuntimeException('API key tidak valid'));

    $analisis->refresh();

    expect($analisis->status)->toBe('failed')
        ->and($analisis->hasil)->toContain('[ERROR]');
});
