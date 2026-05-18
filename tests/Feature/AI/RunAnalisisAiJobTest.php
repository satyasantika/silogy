<?php

use App\Models\User;
use App\Modules\AI\Exceptions\GeminiRateLimitExceededException;
use App\Modules\AI\Jobs\RunAnalisisAiJob;
use App\Modules\AI\Models\AnalisisAi;
use App\Modules\AI\Notifications\AnalisisAiGagalNotification;
use App\Modules\AI\RateLimiters\GeminiPerUserPerDay;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalkulasi\Models\HasilCplUnit;
use Database\Seeders\RolePermissionSeeder;
use Gemini\Contracts\ClientContract;
use Gemini\Responses\GenerativeModel\GenerateContentResponse;
use Gemini\Testing\ClientFake;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Unit\Kalkulasi\Concerns\SetsUpKalkulasiFixtures;

uses(RefreshDatabase::class, SetsUpKalkulasiFixtures::class);

beforeEach(function () {
    $this->seedKalkulasiBase();
    $this->seed(RolePermissionSeeder::class);

    config([
        'services.gemini.model_default' => 'gemini-2.5-pro',
        'services.gemini.model_flash' => 'gemini-2.5-flash',
    ]);
});

function bindGeminiClientFake(array $responses): void
{
    app()->instance(ClientContract::class, new ClientFake($responses));
}

function fakeGeminiSuccess(string $text = '### Ringkasan CPL\nCapaian memadai.'): GenerateContentResponse
{
    return GenerateContentResponse::fake([
        'candidates' => [
            [
                'content' => [
                    'parts' => [['text' => $text]],
                    'role' => 'model',
                ],
                'finishReason' => 'STOP',
            ],
        ],
        'usageMetadata' => [
            'promptTokenCount' => 100,
            'candidatesTokenCount' => 200,
            'totalTokenCount' => 300,
        ],
    ]);
}

function buatAnalisisRingkasan(): AnalisisAi
{
    $dasar = test()->createKelasPenilaianDasar();
    $prodi = AcademicUnit::query()->findOrFail($dasar['mk']->academic_unit_id);
    $semester = $dasar['semester'];
    $user = User::where('username', 'superadmin')->firstOrFail();

    test()->buatKurikulumAktif($prodi, 75);

    HasilCplUnit::query()->create([
        'cpl_id' => $dasar['cpl']->id,
        'academic_unit_id' => $prodi->id,
        'semester_id' => $semester->id,
        'rata_rata' => 80,
        'persentase_tercapai' => 85,
        'jumlah_mahasiswa' => 1,
    ]);

    return AnalisisAi::query()->create([
        'academic_unit_id' => $prodi->id,
        'semester_id' => $semester->id,
        'jenis' => 'ringkasan_cpl',
        'status' => 'pending',
        'prompt' => '-',
        'dibuat_oleh' => $user->id,
    ]);
}

it('runs summary job and saves result text', function () {
    bindGeminiClientFake([fakeGeminiSuccess('### Ringkasan CPL Prodi')]);

    $analisis = buatAnalisisRingkasan();

    RunAnalisisAiJob::dispatchSync($analisis->id);

    $analisis->refresh();

    expect($analisis->status)->toBe('completed')
        ->and($analisis->hasil)->toContain('### Ringkasan CPL Prodi')
        ->and($analisis->model_ai)->toBe('gemini-2.5-flash')
        ->and($analisis->token_digunakan)->toBe(300)
        ->and($analisis->durasi_ms)->toBeGreaterThanOrEqual(0)
        ->and($analisis->finish_reason)->toBe('STOP')
        ->and($analisis->safety_blocked)->toBeFalse()
        ->and($analisis->prompt)->toContain('analis akademik OBE');
});

it('marks safety blocked when finish reason is safety', function () {
    bindGeminiClientFake([
        GenerateContentResponse::fake([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [['text' => '']],
                        'role' => 'model',
                    ],
                    'finishReason' => 'SAFETY',
                ],
            ],
            'usageMetadata' => [
                'promptTokenCount' => 10,
                'candidatesTokenCount' => 0,
                'totalTokenCount' => 10,
            ],
        ]),
    ]);

    $analisis = buatAnalisisRingkasan();

    RunAnalisisAiJob::dispatchSync($analisis->id);

    $analisis->refresh();

    expect($analisis->status)->toBe('completed')
        ->and($analisis->finish_reason)->toBe('SAFETY')
        ->and($analisis->safety_blocked)->toBeTrue();
});

it('respects rate limit per user', function () {
    $user = User::where('username', 'superadmin')->firstOrFail();
    RateLimiter::clear(GeminiPerUserPerDay::keyForUser($user->id));

    for ($i = 0; $i < GeminiPerUserPerDay::MAX_ATTEMPTS; $i++) {
        GeminiPerUserPerDay::ensure($user->id);
    }

    expect(fn () => GeminiPerUserPerDay::ensure($user->id))
        ->toThrow(GeminiRateLimitExceededException::class)
        ->and(GeminiPerUserPerDay::remaining($user->id))->toBe(0);
});

it('dispatches job on ai-analysis queue', function () {
    Queue::fake();

    $analisis = buatAnalisisRingkasan();

    RunAnalisisAiJob::dispatch($analisis->id);

    Queue::assertPushedOn('ai-analysis', RunAnalisisAiJob::class);
});

it('notifies pembuat when job fails', function () {
    Notification::fake();

    bindGeminiClientFake([]);

    $analisis = buatAnalisisRingkasan();
    $user = $analisis->dibuatOleh;

    try {
        RunAnalisisAiJob::dispatchSync($analisis->id);
    } catch (Throwable) {
        // Job exception diteruskan saat sync dispatch.
    }

    $job = new RunAnalisisAiJob($analisis->id);
    $job->failed(new RuntimeException('Koneksi Gemini gagal'));

    $analisis->refresh();

    expect($analisis->status)->toBe('failed')
        ->and($analisis->hasil)->toBe('[ERROR] Koneksi Gemini gagal');

    Notification::assertSentTo($user, AnalisisAiGagalNotification::class);
});
