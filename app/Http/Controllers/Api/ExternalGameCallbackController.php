<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ExternalGames\ExternalGameCallbackService;
use App\Services\ExternalGames\ExternalGameProviderConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExternalGameCallbackController extends Controller
{
    public function handle(Request $request, ExternalGameCallbackService $callbacks, ?string $command = null): JsonResponse
    {
        $response = $callbacks->handle($request, $command);

        return $this->json($response);
    }

    public function handleProviderWallet(Request $request, ExternalGameCallbackService $callbacks, string $provider_alias): JsonResponse
    {
        $alias = ExternalGameProviderConfig::fromPublicAlias(strtolower(trim($provider_alias)));
        if ($alias === null) {
            return response()->json([
                'status' => 0,
                'error' => 'UNKNOWN_PROVIDER',
                'message' => 'Unknown provider.',
            ]);
        }

        $request->attributes->set(ExternalGameProviderConfig::REQUEST_ALIAS, $alias);

        return $this->json($callbacks->handle($request));
    }

    private function json(array $response): JsonResponse
    {
        $status = (int) ($response['http_status'] ?? 200);
        unset($response['http_status']);

        return response()->json($response, $status);
    }
}
