<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Route;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\AdvertiseApiVersions;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\ApiVersion;
use ShahGhasiAdil\LaravelApiVersioning\Services\AttributeCacheService;
use ShahGhasiAdil\LaravelApiVersioning\Services\AttributeVersionResolver;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionManager;

beforeEach(function () {
    $config = [
        'default_version' => '2.0',
        'supported_versions' => ['1.0', '2.0', '3.0'],
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

function advertisedRouteFor(object $controller, string $method): Route
{
    $route = Mockery::mock(Route::class);
    $route->shouldReceive('getController')->andReturn($controller);
    $route->shouldReceive('getActionMethod')->andReturn($method);

    return $route;
}

#[ApiVersion('2.0')]
#[AdvertiseApiVersions('1.0')]
#[AdvertiseApiVersions('3.0', deprecated: true, sunset: '2026-06-30', replacedBy: '2.0')]
class AdvertisingController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([]);
    }
}

test('an advertised version is never resolved by the route that advertises it', function () {
    $route = advertisedRouteFor(new AdvertisingController, 'index');

    expect($this->resolver->resolveVersionForRoute($route, '1.0'))->toBeNull();
    expect($this->resolver->resolveVersionForRoute($route, '3.0'))->toBeNull();
});

test('an implemented version still resolves normally alongside advertised ones', function () {
    $route = advertisedRouteFor(new AdvertisingController, 'index');

    $versionInfo = $this->resolver->resolveVersionForRoute($route, '2.0');

    expect($versionInfo)->not()->toBeNull();
    expect($versionInfo->version)->toBe('2.0');
});

test('getAllVersionsForRoute includes advertised versions alongside implemented ones', function () {
    $route = advertisedRouteFor(new AdvertisingController, 'index');

    expect($this->resolver->getAllVersionsForRoute($route))->toBe(['2.0', '1.0', '3.0']);
});

test('getDeprecatedVersionsForRoute includes a deprecated advertised version', function () {
    $route = advertisedRouteFor(new AdvertisingController, 'index');

    expect($this->resolver->getDeprecatedVersionsForRoute($route))->toBe(['3.0']);
});
