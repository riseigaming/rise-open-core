<?php

namespace App\Betting\Application\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class BettingApiResponder
{
    public function success(array $data, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'error' => null,
            'meta' => $this->meta($meta),
        ], $status);
    }

    public function error(string $code, string $message, int $status = 422, array $details = [], array $meta = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'data' => null,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => (object) $details,
            ],
            'meta' => $this->meta($meta),
        ], $status);
    }

    private function meta(array $meta = []): array
    {
        $request = request();
        $requestId = (string) $request->attributes->get('betting_api_request_id', '');

        if ($requestId === '') {
            $requestId = $request->headers->get('X-Request-Id') ?: (string) Str::uuid();
            $request->attributes->set('betting_api_request_id', $requestId);
        }

        return array_merge([
            'request_id' => $requestId,
            'generated_at' => now()->toIso8601String(),
        ], $meta);
    }
}
