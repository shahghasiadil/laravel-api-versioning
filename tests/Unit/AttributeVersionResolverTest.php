<?php

use Illuminate\Routing\Route;
use ShahGhasiAdil\LaravelApiVersioning\Examples\SharedController;
use ShahGhasiAdil\LaravelApiVersioning\Examples\V1UserController;
use ShahGhasiAdil\LaravelApiVersioning\Services\AttributeCacheService;
use ShahGhasiAdil\LaravelApiVersioning\Services\AttributeVersionResolver;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionManager;

beforeEach(function () {
    $config = [
        'default_version' => '2.0',
        'supported_versions' => ['1.0', '1.1', '2.0', '2.1'],
        'detection_methods' => [
            'header' => ['enabled' => true, 'header_name' => 'X-API-Version'],
            'query' => ['enabled' => true, 'parameter_name' => 'api-version'],
            'path' => ['enabled' => true, 'prefix' => 'api/v'],
            'media_type' => ['enabled' => false],
        ],
    ];

    $this->versionManager = new VersionManager($config);
    $this->cache = new AttributeCacheService(enabled: false, ttl: 3600); // Disable cache for tests
    $this->resolver = new AttributeVersionResolver($this->versionManager, $this->cache);
});

test('resolves version from controller attribute', function () {
    // Create a mock route with controller
    $controller = new V1UserController;
    $route = new Route(['GET'], 'users', [V1UserController::class, 'index']);
    $route->setContainer($this->app);

    // We need to manually set the controller since we're not going through normal routing
    $route->setAction(['uses' => V1UserController::class.'@index']);

    // Mock the route to return our controller
    $route = createMockRoute($controller, 'index');

    $versionInfo = $this->resolver->resolveVersionForRoute($route, '1.0');

    expect($versionInfo)->not()->toBeNull();
    expect($versionInfo->version)->toBe('1.0');
    expect($versionInfo->isDeprecated)->toBeTrue();
    expect($versionInfo->sunsetDate)->toBe('2025-12-31');
});

test('resolves version neutral endpoint', function () {
    $controller = new SharedController;
    $route = createMockRoute($controller, 'health');

    $versionInfo = $this->resolver->resolveVersionForRoute($route, '2.0');

    expect($versionInfo)->not()->toBeNull();
    expect($versionInfo->isNeutral)->toBeTrue();
});

test('returns null for unsupported version', function () {
    $controller = new V1UserController;
    $route = createMockRoute($controller, 'index');

    $versionInfo = $this->resolver->resolveVersionForRoute($route, '3.0');

    expect($versionInfo)->toBeNull();
});

test('get all versions for route', function () {
    $controller = new V1UserController;
    $route = createMockRoute($controller, 'index');

    $versions = $this->resolver->getAllVersionsForRoute($route);

    expect($versions)->toBe(['1.0', '1.1']);
});

/**
 * Create a mock route that properly returns controller and action method
 */
function createMockRoute($controller, string $method): Route
{
    $route = Mockery::mock(Route::class);

    $route->shouldReceive('getController')
        ->andReturn($controller);

    $route->shouldReceive('getActionMethod')
        ->andReturn($method);

    return $route;
}
