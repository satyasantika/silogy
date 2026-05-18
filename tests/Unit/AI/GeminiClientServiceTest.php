<?php

use App\Modules\AI\Exceptions\AiServiceException;
use App\Modules\AI\Services\GeminiClientService;
use Gemini\Contracts\ClientContract;
use Gemini\Resources\GenerativeModel;
use Gemini\Responses\GenerativeModel\GenerateContentResponse;
use Gemini\Testing\ClientFake;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config([
        'services.gemini' => [
            'model_default' => 'gemini-2.5-pro',
            'model_flash' => 'gemini-2.5-flash',
            'max_output_tokens' => 4096,
            'safety_settings' => [
                'HARM_CATEGORY_HARASSMENT' => 'BLOCK_MEDIUM_AND_ABOVE',
                'HARM_CATEGORY_HATE_SPEECH' => 'BLOCK_MEDIUM_AND_ABOVE',
                'HARM_CATEGORY_SEXUALLY_EXPLICIT' => 'BLOCK_MEDIUM_AND_ABOVE',
                'HARM_CATEGORY_DANGEROUS_CONTENT' => 'BLOCK_MEDIUM_AND_ABOVE',
            ],
        ],
    ]);
});

function bindGeminiFake(array $responses): ClientFake
{
    $fake = new ClientFake($responses);
    app()->instance(ClientContract::class, $fake);

    return $fake;
}

function geminiService(): GeminiClientService
{
    return new GeminiClientService(app(ClientContract::class));
}

function fakeSuccessResponse(string $text = 'hasil analisis'): GenerateContentResponse
{
    return GenerateContentResponse::fake([
        'candidates' => [
            [
                'content' => [
                    'parts' => [
                        ['text' => $text],
                    ],
                    'role' => 'model',
                ],
                'finishReason' => 'STOP',
            ],
        ],
        'usageMetadata' => [
            'promptTokenCount' => 12,
            'candidatesTokenCount' => 48,
            'totalTokenCount' => 60,
        ],
    ]);
}

it('calls gemini pro for analysis', function () {
    $fake = bindGeminiFake([fakeSuccessResponse()]);

    $response = geminiService()->generate('Ringkas capaian CPL prodi', ['unit' => 'TI']);

    $fake->assertSent(
        resource: GenerativeModel::class,
        model: 'gemini-2.5-pro',
        callback: fn (string $method, array $parameters): bool => $method === 'generateContent'
            && str_contains($parameters[0], 'Ringkas capaian CPL prodi')
            && str_contains($parameters[0], '"unit":"TI"'),
    );

    expect($response->text)->toBe('hasil analisis')
        ->and($response->finish_reason)->toBe('STOP')
        ->and($response->prompt_token_count)->toBe(12)
        ->and($response->candidates_token_count)->toBe(48)
        ->and($response->total_token_count)->toBe(60);
});

it('falls back to flash for summary', function () {
    $fake = bindGeminiFake([fakeSuccessResponse('ringkasan singkat')]);

    $response = geminiService()->summarize('Buat ringkasan eksekutif');

    $fake->assertSent(
        resource: GenerativeModel::class,
        model: 'gemini-2.5-flash',
        callback: fn (string $method): bool => $method === 'generateContent',
    );

    expect($response->text)->toBe('ringkasan singkat');
});

it('records latency ms', function () {
    bindGeminiFake([fakeSuccessResponse()]);

    $response = geminiService()->generate('Uji latensi');

    expect($response->latency_ms)->toBeGreaterThanOrEqual(0);
});

it('wraps safety block into finish reason', function () {
    bindGeminiFake([
        GenerateContentResponse::fake([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => ''],
                        ],
                        'role' => 'model',
                    ],
                    'finishReason' => 'SAFETY',
                ],
            ],
            'usageMetadata' => [
                'promptTokenCount' => 5,
                'candidatesTokenCount' => 0,
                'totalTokenCount' => 5,
            ],
        ]),
    ]);

    $response = geminiService()->generate('konten berisiko');

    expect($response->text)->toBe('')
        ->and($response->finish_reason)->toBe('SAFETY');
});

it('wraps gemini errors in ai service exception', function () {
    bindGeminiFake([]);

    geminiService()->generate('prompt tanpa respons fake');
})->throws(AiServiceException::class);
