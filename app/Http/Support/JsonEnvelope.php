<?php

namespace App\Http\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class JsonEnvelope
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function success(array $data, string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'request_id' => (string) Str::ulid(),
            ],
            'message' => $message,
        ], $status);
    }

    /**
     * @param  array<string, mixed>|null  $details
     */
    public static function error(
        string $code,
        string $message,
        int $status = 500,
        ?array $details = null,
    ): JsonResponse {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if ($details !== null) {
            $error['details'] = $details;
        }

        return response()->json([
            'success' => false,
            'error' => $error,
            'meta' => [
                'request_id' => (string) Str::ulid(),
            ],
        ], $status);
    }
}
