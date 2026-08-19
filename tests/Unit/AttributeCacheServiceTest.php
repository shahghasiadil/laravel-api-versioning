<?php

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Cache;
use ShahGhasiAdil\LaravelApiVersioning\Services\AttributeCacheService;
use ShahGhasiAdil\LaravelApiVersioning\Services\AttributeVersionResolver;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionManager;
use ShahGhasiAdil\LaravelApiVersioning\Tests\Fixtures\Controllers\V1UserController;
use ShahGhasiAdil\LaravelApiVersioning\ValueObjects\VersionInfo;

afterEach(function () {
    AttributeVersionResolver::resetMemoryCache();
});

describe('untagged store (file driver)', function () {
    beforeEach(function () {
        config(['cache.default' => 'file']);
        Cache::clearResolvedInstances();
        Cache::store('file')->flush();
    });

    test('flush() actually removes entries via a maintained key index', function () {
        $cache = new AttributeCacheService(enabled: true, ttl: 3600);

        expect($cache->supportsTagging())->toBeFalse();

        $key = 'some:key:'.uniqid();
        $calls = 0;
        $compute = function () use (&$calls) {
            $calls++;

            return "value-{$calls}";
        };

        expect($cache->remember($key, $compute))->toBe('value-1');
        // Cached: second call doesn't recompute.
        expect($cache->remember($key, $compute))->toBe('value-1');

        $cache->flush();

        // After a real flush, the key is gone and the callback runs again.
        expect($cache->remember($key, $compute))->toBe('value-2');
    });

    test('flush() resets the AttributeVersionResolver memory cache', function () {
        $cache = new AttributeCacheService(enabled: true, ttl: 3600);

        $ref = new ReflectionClass(AttributeVersionResolver::class);
        $prop = $ref->getProperty('memoryCache');
        $prop->setAccessible(true);
        $prop->setValue(null, ['some-key' => null]);

        expect($prop->getValue(null))->not->toBe([]);

        $cache->flush();

        expect($prop->getValue(null))->toBe([]);
    });
});

describe('VersionInfo caching', function () {
    test('resolveVersionForRoute() caches VersionInfo as a plain array, not the object itself', function () {
        // A prior test may have resolved the same route/version combo
        // through AttributeVersionResolver's static in-process memory
        // cache; without resetting it here, resolveVersionForRoute() below
        // could short-circuit before ever touching the cache store.
        AttributeVersionResolver::resetMemoryCache();

        config(['cache.default' => 'file']);
        Cache::clearResolvedInstances();
        Cache::store('file')->flush();

        $versionManager = new VersionManager([
            'default_version' => '2.0',
            'supported_versions' => ['1.0', '1.1', '2.0', '2.1'],
            'detection_methods' => [
                'header' => ['enabled' => true, 'header_name' => 'X-API-Version'],
                'query' => ['enabled' => true, 'parameter_name' => 'api-version'],
                'path' => ['enabled' => true, 'prefix' => 'api/v'],
                'media_type' => ['enabled' => false],
            ],
        ]);
        $cache = new AttributeCacheService(enabled: true, ttl: 3600);
        $resolver = new AttributeVersionResolver($versionManager, $cache);

        $controller = new V1UserController;
        $route = Mockery::mock(Route::class);
        $route->shouldReceive('getController')->andReturn($controller);
        $route->shouldReceive('getActionMethod')->andReturn('index');

        $versionInfo = $resolver->resolveVersionForRoute($route, '1.0');
        expect($versionInfo)->toBeInstanceOf(VersionInfo::class);

        // 'api_versioning:' mirrors AttributeCacheService::CACHE_PREFIX.
        $cacheKey = 'api_versioning:'.$cache->generateRouteKey(V1UserController::class, 'index', '1.0');
        $raw = Cache::store('file')->get($cacheKey);

        // The cache store must never have to unserialize this package's
        // VersionInfo class -- applications with a hardened unserialize()
        // class allow-list shouldn't need to know it exists.
        expect($raw)->toBeArray();
        expect($raw)->not->toBeInstanceOf(VersionInfo::class);
        expect($raw['version'])->toBe('1.0');

        // A second, fresh resolution against the same cache entry (a
        // different PHP process would look like this) must still return a
        // real, correctly reconstructed VersionInfo.
        AttributeVersionResolver::resetMemoryCache();
        $rehydrated = $resolver->resolveVersionForRoute($route, '1.0');

        expect($rehydrated)->toBeInstanceOf(VersionInfo::class);
        expect($rehydrated->toArray())->toBe($versionInfo->toArray());
    });
});

describe('config-dependent cache keys', function () {
    test('route cache key changes when supported_versions changes', function () {
        $cache = new AttributeCacheService(enabled: true, ttl: 3600);

        config(['api-versioning.supported_versions' => ['1.0']]);
        $keyBefore = $cache->generateRouteKey('App\\Http\\Controllers\\UserController', 'index', '1.0');

        config(['api-versioning.supported_versions' => ['1.0', '3.0']]);
        $keyAfter = $cache->generateRouteKey('App\\Http\\Controllers\\UserController', 'index', '1.0');

        expect($keyBefore)->not->toBe($keyAfter);
    });

    test('route versions key and deprecated versions key are also config-sensitive', function () {
        $cache = new AttributeCacheService(enabled: true, ttl: 3600);

        config(['api-versioning.default_version' => '1.0']);
        $versionsKeyBefore = $cache->generateRouteVersionsKey('C', 'm');
        $deprecatedKeyBefore = $cache->generateRouteDeprecatedVersionsKey('C', 'm');

        config(['api-versioning.default_version' => '2.0']);
        $versionsKeyAfter = $cache->generateRouteVersionsKey('C', 'm');
        $deprecatedKeyAfter = $cache->generateRouteDeprecatedVersionsKey('C', 'm');

        expect($versionsKeyBefore)->not->toBe($versionsKeyAfter);
        expect($deprecatedKeyBefore)->not->toBe($deprecatedKeyAfter);
    });
});
