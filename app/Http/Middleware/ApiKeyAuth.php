<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\ApiKey;
use Illuminate\Http\JsonResponse;

class ApiKeyAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $this->extractApiKey($request);

        if (!$apiKey) {
            return $this->unauthorizedResponse('API key is required. Please provide a valid API key in the Authorization header or as api_key parameter.');
        }

        $apiKeyModel = ApiKey::where('key', $apiKey)->first();

        if (!$apiKeyModel) {
            return $this->unauthorizedResponse('Invalid API key.');
        }

        if (!$apiKeyModel->isValid()) {
            return $this->unauthorizedResponse('API key is inactive or expired.');
        }

        // Check IP restrictions
        if (!$apiKeyModel->isIpAllowed($request->ip())) {
            return $this->unauthorizedResponse('Access denied from this IP address.');
        }

        // Check rate limits
        if (!$this->checkRateLimits($apiKeyModel)) {
            return $this->rateLimitResponse($apiKeyModel);
        }

        // Check format restrictions if format is provided
        if ($request->has('format') && !$apiKeyModel->isFormatAllowed($request->input('format'))) {
            return $this->unauthorizedResponse('This API key is not authorized to download in the requested format.');
        }

        // Record API key usage
        $apiKeyModel->recordUsage();
        $apiKeyModel->incrementRateLimit();

        // Add API key to request for potential use in controllers
        $request->attributes->set('api_key', $apiKeyModel);

        return $next($request);
    }

    /**
     * Extract API key from request.
     */
    private function extractApiKey(Request $request): ?string
    {
        // Check Authorization header (Bearer token)
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        // Check X-API-Key header
        $apiKeyHeader = $request->header('X-API-Key');
        if ($apiKeyHeader) {
            return $apiKeyHeader;
        }

        // Check api_key parameter
        return $request->input('api_key');
    }

    /**
     * Check rate limits for the API key.
     */
    private function checkRateLimits(ApiKey $apiKey): bool
    {
        return $apiKey->checkRateLimit('minute') &&
               $apiKey->checkRateLimit('hour') &&
               $apiKey->checkRateLimit('day');
    }

    /**
     * Return unauthorized response.
     */
    private function unauthorizedResponse(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'UNAUTHORIZED',
                'message' => $message,
            ],
        ], 401);
    }

    /**
     * Return rate limit exceeded response.
     */
    private function rateLimitResponse(ApiKey $apiKey): JsonResponse
    {
        $usage = $apiKey->getRateLimitUsage();

        $exceededLimits = [];
        foreach ($usage as $period => $data) {
            if ($data['used'] >= $data['limit']) {
                $exceededLimits[] = $period;
            }
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'RATE_LIMIT_EXCEEDED',
                'message' => 'Rate limit exceeded for: ' . implode(', ', $exceededLimits),
                'rate_limits' => $usage,
            ],
        ], 429);
    }
}
