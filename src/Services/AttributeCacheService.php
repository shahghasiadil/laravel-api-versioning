<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Services;

use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;

class AttributeCacheService
{
    private const CACHE_PREFIX = 'api_versioning:';

    private const CACHE_TAG = 'api_versioning';

    private ?bool $tagsSupported = null;

    public function __construct(
        private readonly bool $enabled,
        private readonly int $ttl
    ) {}

    /**
     * Get cached value for a key, computing it via callback when absent.
     *
     * When the underlying store supports tags the entry is stored under the
     * `api_versioning` tag, enabling a precise flush via {@see flush()}.
     *
     * @param  string  $key  Cache key (without prefix)
     * @param  callable  $callback  Callback to generate value if not cached
     */
    public function remember(string $key, callable $callback): mixed
    {
        if (! $this->enabled) {
            return $callback();
        }

        $cacheKey = self::CACHE_PREFIX.$key;

        if ($this->supportsTags()) {
            return Cache::tags([self::CACHE_TAG])->remember($cacheKey, $this->ttl, $callback);
        }

        return Cache::remember($cacheKey, $this->ttl, $callback);
    }

    /**
     * Flush only the API versioning cache entries.
     *
     * Uses tag-based flushing when supported. On drivers that do not support
     * tags (e.g. the file driver) this method is a no-op to avoid clearing
     * the entire application cache.
     */
    public function flush(): void
    {
        if ($this->supportsTags()) {
            Cache::tags([self::CACHE_TAG])->flush();

            return;
        }

        // File/database stores do not support tag-based flushing.
        // Clearing the whole store would wipe unrelated cache entries, so we
        // intentionally skip the flush and leave stale entries to expire via TTL.
    }

    /**
     * Clear a specific cache entry.
     */
    public function forget(string $key): void
    {
        $cacheKey = self::CACHE_PREFIX.$key;

        if ($this->supportsTags()) {
            Cache::tags([self::CACHE_TAG])->forget($cacheKey);

            return;
        }

        Cache::forget($cacheKey);
    }

    /**
     * Generate cache key for route version resolution.
     */
    public function generateRouteKey(string $controller, string $method, string $version): string
    {
        return sprintf('route:%s@%s:%s', $controller, $method, $version);
    }

    /**
     * Generate cache key for all versions of a route.
     */
    public function generateRouteVersionsKey(string $controller, string $method): string
    {
        return sprintf('route_versions:%s@%s', $controller, $method);
    }

    /**
     * Determine whether the active cache store supports tagging.
     */
    private function supportsTags(): bool
    {
        return $this->tagsSupported ??= Cache::getStore() instanceof TaggableStore;
    }
}
