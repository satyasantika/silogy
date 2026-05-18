<?php

namespace App\Modules\AI\Services;

use App\Modules\AI\DataObjects\GeminiResponse;
use App\Modules\AI\Exceptions\AiServiceException;
use Gemini\Contracts\ClientContract;
use Gemini\Contracts\Resources\GenerativeModelContract;
use Gemini\Data\GenerationConfig;
use Gemini\Data\SafetySetting;
use Gemini\Enums\HarmBlockThreshold;
use Gemini\Enums\HarmCategory;
use Gemini\Responses\GenerativeModel\GenerateContentResponse;
use Throwable;
use ValueError;

class GeminiClientService
{
    public function __construct(
        private readonly ClientContract $client,
    ) {}

    public function generate(string $prompt, array $context = [], ?string $model = null): GeminiResponse
    {
        $modelName = $model ?? (string) config('services.gemini.model_default');

        return $this->call($prompt, $context, $modelName);
    }

    public function summarize(string $prompt, array $context = []): GeminiResponse
    {
        return $this->call(
            $prompt,
            $context,
            (string) config('services.gemini.model_flash'),
        );
    }

    private function call(string $prompt, array $context, string $model): GeminiResponse
    {
        $startedAt = hrtime(true);

        try {
            $generativeModel = $this->configureModel($model);
            $response = $generativeModel->generateContent($this->buildInput($prompt, $context));
            $latencyMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);

            return $this->mapResponse($response, $latencyMs, $model);
        } catch (Throwable $e) {
            throw AiServiceException::from($e);
        }
    }

    private function configureModel(string $model): GenerativeModelContract
    {
        $generativeModel = $this->client->generativeModel($model);

        foreach ($this->buildSafetySettings() as $safetySetting) {
            $generativeModel = $generativeModel->withSafetySetting($safetySetting);
        }

        $maxOutputTokens = config('services.gemini.max_output_tokens');

        if ($maxOutputTokens !== null) {
            $generativeModel = $generativeModel->withGenerationConfig(
                new GenerationConfig(maxOutputTokens: (int) $maxOutputTokens),
            );
        }

        return $generativeModel;
    }

    private function buildInput(string $prompt, array $context): string
    {
        if ($context === []) {
            return $prompt;
        }

        return $prompt."\n\n".json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return list<SafetySetting>
     */
    private function buildSafetySettings(): array
    {
        $config = config('services.gemini.safety_settings', []);

        $settings = [];

        foreach ($config as $category => $threshold) {
            $settings[] = new SafetySetting(
                category: HarmCategory::from((string) $category),
                threshold: HarmBlockThreshold::from((string) $threshold),
            );
        }

        return $settings;
    }

    private function mapResponse(
        GenerateContentResponse $response,
        int $latencyMs,
        string $model,
    ): GeminiResponse {
        return new GeminiResponse(
            text: $this->extractText($response),
            finish_reason: $this->resolveFinishReason($response),
            prompt_token_count: $response->usageMetadata->promptTokenCount,
            candidates_token_count: $response->usageMetadata->candidatesTokenCount ?? 0,
            total_token_count: $response->usageMetadata->totalTokenCount,
            latency_ms: $latencyMs,
            model: $model,
            raw_payload: $response->toArray(),
        );
    }

    private function extractText(GenerateContentResponse $response): string
    {
        if ($response->candidates === []) {
            return '';
        }

        try {
            return $response->text();
        } catch (ValueError) {
            $chunks = [];

            foreach ($response->candidates[0]->content->parts as $part) {
                if ($part->text !== null) {
                    $chunks[] = $part->text;
                }
            }

            return implode('', $chunks);
        }
    }

    private function resolveFinishReason(GenerateContentResponse $response): ?string
    {
        if ($response->promptFeedback?->blockReason !== null) {
            return $response->promptFeedback->blockReason->value;
        }

        if ($response->candidates !== [] && $response->candidates[0]->finishReason !== null) {
            return $response->candidates[0]->finishReason->value;
        }

        return null;
    }
}
