<?php

use Illuminate\Support\Facades\Cache;
use ShahGhasiAdil\LaravelApiVersioning\Services\AttributeVersionResolver;
use ShahGhasiAdil\LaravelApiVersioning\Services\AttributeCacheService;

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
