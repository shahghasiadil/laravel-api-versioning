<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use ShahGhasiAdil\LaravelApiVersioning\Http\Responses\ProblemDetailsResponse;
use Symfony\Component\HttpFoundation\Response;

class VersionedRateLimitMiddleware
{
    public function __construct(
        private readonly RateLimiter $limiter
    ) {}

    /**
     * Handle an incoming request
     *
     * @param Request $request
     * @param Closure $next
     * @param int|string $maxAttempts Maximum attempts (can be version-specific)
     * @param int $decayMinutes Decay time in minutes
     * @return Response
     */
    public function handle(Request $request, Closure $next, int|string $maxAttempts = 60, int $decayMinutes = 1): Response
    {
        $apiVersion = $request->attributes->get('api_version', 'default');

        // Get version-specific rate limit if configured
        $versionLimits = config('api-versioning.rate_limits', []);
        if (isset($versionLimits[$apiVersion])) {
            $maxAttempts = $versionLimits[$apiVersion];
        }

        $key = $this->resolveRequestSignature($request, $apiVersion);

        if ($this->limiter->tooManyAttempts($key, (int) $maxAttempts)) {
            return $this->buildRateLimitResponse($key, (int) $maxAttempts);
        }

        $this->limiter->hit($key, $decayMinutes * 60);

        $response = $next($request);

        return $this->addHeaders(
            $response,
            (int) $maxAttempts,
            $this->calculateRemainingAttempts($key, (int) $maxAttempts)
        );
    }

    /**
     * Resolve request signature
     */
    protected function resolveRequestSignature(Request $request, string $apiVersion): string
    {
        $user = $request->user();

        return sha1(implode('|', [
            $apiVersion,
            $request->method(),
            $request->server('SERVER_NAME'),
            $request->path(),
            $user?->getAuthIdentifier() ?? $request->ip(),
        ]));
    }

    /**
     * Calculate remaining attempts
     */
    protected function calculateRemainingAttempts(string $key, int $maxAttempts): int
    {
        return $this->limiter->retriesLeft($key, $maxAttempts);
    }

    /**
     * Build rate limit exceeded response
     */
    protected function buildRateLimitResponse(string $key, int $maxAttempts): ProblemDetailsResponse
    {
        $retryAfter = $this->limiter->availableIn($key);

        return new ProblemDetailsResponse(
            title: 'Too Many Requests',
            detail: 'Rate limit exceeded. Please try again later.',
            status: 429,
            type: 'https://tools.ietf.org/html/rfc6585#section-4',
            extensions: [
                'retry_after' => $retryAfter,
                'limit' => $maxAttempts,
            ]
        );
    }

    /**
     * Add rate limit headers to response
     */
    protected function addHeaders(Response $response, int $maxAttempts, int $remainingAttempts): Response
    {
        $response->headers->add([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => max(0, $remainingAttempts),
        ]);

        return $response;
    }
}
