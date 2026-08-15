<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Services;

use Closure;
use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;
use ShahGhasiAdil\LaravelApiVersioning\Services\AttributeVersionResolver;

class AttributeCacheService
{
    private const CACHE_PREFIX = 'api_versioning:';

    private const CACHE_TAG = 'api_versioning';

    /**
     * Cache key (without prefix) holding the index of every key this
     * service has written on an untagged store, so {@see flush()} can
     * remove them individually instead of no-oping.
     */
    private const KEY_INDEX = '__key_index';

    private ?bool $tagsSupported = null;

    public function __construct(
        private readonly bool $enabled,
        private readonly int $ttl
    ) {}

    /**
     * Get cached value for a key, computing it via callback when absent.
     *
     * When the underlying store supports tags the entry is stored under the
     * `api_versioning` tag, enabling a precise flush via {@see flush()}. On
     * stores that don't support tags, the key is recorded in a small index
     * so it can still be individually removed by {@see flush()}.
     *
     * @param  string  $key  Cache key (without prefix)
     * @param  Closure(): mixed  $callback  Callback to generate value if not cached
     */
    public function remember(string $key, Closure $callback): mixed
    {
        if (! $this->enabled) {
            return $callback();
        }

        $cacheKey = self::CACHE_PREFIX.$key;

        if ($this->supportsTags()) {
            return Cache::tags([self::CACHE_TAG])->remember($cacheKey, $this->ttl, $callback);
        }

        $this->indexKey($cacheKey);

        return Cache::remember($cacheKey, $this->ttl, $callback);
    }

    /**
     * Flush only the API versioning cache entries.
     *
     * Uses tag-based flushing when supported. On drivers that do not
     * support tags (e.g. the file driver) this walks a maintained key
     * index and removes each entry individually, so a full flush is still
     * a real flush rather than a silent no-op.
     *
     * Also resets the in-process ({@see AttributeVersionResolver}) memory
     * cache, which a per-request cache driver flush can never reach.
     */
    public function flush(): void
    {
        if ($this->supportsTags()) {
            Cache::tags([self::CACHE_TAG])->flush();
        } else {
            $index = $this->readIndex();

            foreach ($index as $cacheKey) {
                Cache::forget($cacheKey);
            }

            Cache::forget(self::CACHE_PREFIX.self::KEY_INDEX);
        }

        AttributeVersionResolver::resetMemoryCache();
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
        return sprintf('route:%s@%s:%s:%s', $controller, $method, $version, $this->configFingerprint());
    }

    /**
     * Generate cache key for all versions of a route.
     */
    public function generateRouteVersionsKey(string $controller, string $method): string
    {
        return sprintf('route_versions:%s@%s:%s', $controller, $method, $this->configFingerprint());
    }

    /**
     * Generate cache key for the deprecated versions of a route.
     */
    public function generateRouteDeprecatedVersionsKey(string $controller, string $method): string
    {
        return sprintf('route_deprecated_versions:%s@%s:%s', $controller, $method, $this->configFingerprint());
    }

    /**
     * Whether the active cache store supports tag-based flushing. Exposed
     * so callers (e.g. `api:cache:clear`) can explain to the operator what
     * kind of flush actually happened.
     */
    public function supportsTagging(): bool
    {
        return $this->supportsTags();
    }

    /**
     * Short hash of the configuration values that route-level resolution
     * results depend on but that aren't already part of the cache key
     * (controller, method, requested version). Folding this in means a
     * config change (e.g. adding a version to 'supported_versions') is
     * visible immediately instead of only after the TTL expires.
     */
    private function configFingerprint(): string
    {
        /** @var mixed $supportedVersions */
        $supportedVersions = config('api-versioning.supported_versions', []);
        /** @var mixed $defaultVersion */
        $defaultVersion = config('api-versioning.default_version');
        /** @var mixed $inheritance */
        $inheritance = config('api-versioning.version_inheritance', []);

        $payload = json_encode([$supportedVersions, $defaultVersion, $inheritance]);

        return substr(md5($payload !== false ? $payload : ''), 0, 12);
    }

    /**
     * Determine whether the active cache store supports tagging.
     */
    private function supportsTags(): bool
    {
        return $this->tagsSupported ??= Cache::getStore() instanceof TaggableStore;
    }

    /**
     * Record a cache key in the untagged-store key index, so it can be
     * found and removed by {@see flush()}.
     */
    private function indexKey(string $cacheKey): void
    {
        $index = $this->readIndex();

        if (in_array($cacheKey, $index, true)) {
            return;
        }

        $index[] = $cacheKey;

        Cache::put(self::CACHE_PREFIX.self::KEY_INDEX, $index, $this->ttl);
    }

    /**
     * @return string[]
     */
    private function readIndex(): array
    {
        /** @var mixed $index */
        $index = Cache::get(self::CACHE_PREFIX.self::KEY_INDEX, []);

        return is_array($index) ? array_values(array_filter($index, 'is_string')) : [];
    }
}
