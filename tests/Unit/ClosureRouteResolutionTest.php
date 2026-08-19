<?php

use Illuminate\Routing\Route;
use ShahGhasiAdil\LaravelApiVersioning\Services\AttributeCacheService;
use ShahGhasiAdil\LaravelApiVersioning\Services\AttributeVersionResolver;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionManager;

beforeEach(function () {
    $config = [
        'default_version' => '2.0',
        'supported_versions' => ['1.0', '2.0'],
        'detection_methods' => [
            'header' => ['enabled' => true, 'header_name' => 'X-API-Version'],
            'query' => ['enabled' => true, 'parameter_name' => 'api-version'],
            'path' => ['enabled' => true, 'prefix' => 'api/v'],
            'media_type' => ['enabled' => false],
        ],
    ];

    $this->resolver = new AttributeVersionResolver(
        new VersionManager($config),
        new AttributeCacheService(enabled: false, ttl: 3600),
    );
});

function closureRoute(): Route
{
    $route = Mockery::mock(Route::class);
    $route->shouldReceive('getController')->andReturn(null);
    $route->shouldReceive('getActionMethod')->andReturn('Closure');

    return $route;
}

describe('closure_routes: neutral (default)', function () {
    test('resolves every supported version as version-neutral', function () {
        $route = closureRoute();

        $v1 = $this->resolver->resolveVersionForRoute($route, '1.0');
        $v2 = $this->resolver->resolveVersionForRoute($route, '2.0');

        expect($v1)->not()->toBeNull();
        expect($v1->isNeutral)->toBeTrue();
        expect($v1->routeVersions)->toBe(['1.0', '2.0']);

        expect($v2)->not()->toBeNull();
        expect($v2->isNeutral)->toBeTrue();
    });

    test('getAllVersionsForRoute reports every supported version', function () {
        expect($this->resolver->getAllVersionsForRoute(closureRoute()))->toBe(['1.0', '2.0']);
    });

    test('getDeprecatedVersionsForRoute is always empty (no attributes to read)', function () {
        expect($this->resolver->getDeprecatedVersionsForRoute(closureRoute()))->toBe([]);
    });
});

describe('closure_routes: reject', function () {
    test('resolveVersionForRoute returns null for every version', function () {
        config(['api-versioning.closure_routes' => 'reject']);

        $route = closureRoute();

        expect($this->resolver->resolveVersionForRoute($route, '1.0'))->toBeNull();
        expect($this->resolver->resolveVersionForRoute($route, '2.0'))->toBeNull();
    });

    test('getAllVersionsForRoute reports no versions', function () {
        config(['api-versioning.closure_routes' => 'reject']);

        expect($this->resolver->getAllVersionsForRoute(closureRoute()))->toBe([]);
    });
});
