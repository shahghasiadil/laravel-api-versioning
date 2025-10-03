<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Services;

use Illuminate\Support\Facades\Cache;

class AttributeCacheService
{
    private const CACHE_PREFIX = 'api_versioning:';

    public function __construct(
        private readonly bool $enabled,
        private readonly int $ttl
    ) {}

    /**
     * Get cached version info for a route
     *
     * @param  string  $key  Cache key
     * @param  callable  $callback  Callback to generate value if not cached
     */
    public function remember(string $key, callable $callback): mixed
    {
        if (! $this->enabled) {
            return $callback();
        }

        $cacheKey = self::CACHE_PREFIX.$key;

        return Cache::remember($cacheKey, $this->ttl, $callback);
    }

    /**
     * Clear all API versioning cache
     */
    public function flush(): void
    {
        Cache::flush();
    }

    /**
     * Clear specific cache entry
     */
    public function forget(string $key): void
    {
        Cache::forget(self::CACHE_PREFIX.$key);
    }

    /**
     * Generate cache key for route version resolution
     */
    public function generateRouteKey(string $controller, string $method, string $version): string
    {
        return sprintf('route:%s@%s:%s', $controller, $method, $version);
    }

    /**
     * Generate cache key for all versions of a route
     */
    public function generateRouteVersionsKey(string $controller, string $method): string
    {
        return sprintf('route_versions:%s@%s', $controller, $method);
    }
}
