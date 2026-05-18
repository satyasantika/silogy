<?php

namespace App\Modules\AI\DataObjects;

readonly class GeminiResponse
{
    /**
     * @param  array<string, mixed>  $raw_payload
     */
    public function __construct(
        public string $text,
        public ?string $finish_reason,
        public int $prompt_token_count,
        public int $candidates_token_count,
        public int $total_token_count,
        public int $latency_ms,
        public array $raw_payload,
    ) {}
}
