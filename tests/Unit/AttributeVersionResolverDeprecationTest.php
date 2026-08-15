<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Route;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\ApiVersion;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\Deprecated;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\MapToApiVersion;
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

function mockRouteFor(object $controller, string $method): Route
{
    $route = Mockery::mock(Route::class);
    $route->shouldReceive('getController')->andReturn($controller);
    $route->shouldReceive('getActionMethod')->andReturn($method);

    return $route;
}

#[ApiVersion('1.0', deprecated: true, sunset: '2026-06-30', replacedBy: '2.0')]
#[ApiVersion('2.0')]
class PerVersionDeprecatedController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([]);
    }
}

#[ApiVersion('1.0', deprecated: true, sunset: '2026-06-30', replacedBy: '2.0')]
#[ApiVersion('2.0')]
#[Deprecated(message: 'v1 is going away')]
class PerVersionDeprecatedWithMessageController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([]);
    }
}

class PerVersionDeprecatedMethodController extends Controller
{
    #[MapToApiVersion('1.0', deprecated: true, sunset: '2026-06-30', replacedBy: '2.0')]
    #[MapToApiVersion('2.0')]
    public function index(): JsonResponse
    {
        return response()->json([]);
    }
}

describe('per-version deprecation via #[ApiVersion]', function () {
    test('marks only the version declared deprecated on its own attribute', function () {
        $route = mockRouteFor(new PerVersionDeprecatedController, 'index');

        $v1 = $this->resolver->resolveVersionForRoute($route, '1.0');
        $v2 = $this->resolver->resolveVersionForRoute($route, '2.0');

        expect($v1->isDeprecated)->toBeTrue();
        expect($v1->sunsetDate)->toBe('2026-06-30');
        expect($v1->replacedBy)->toBe('2.0');

        expect($v2->isDeprecated)->toBeFalse();
        expect($v2->sunsetDate)->toBeNull();
        expect($v2->replacedBy)->toBeNull();
    });

    test('fills in the deprecation message from a coarse #[Deprecated] attribute, only for the deprecated version', function () {
        $route = mockRouteFor(new PerVersionDeprecatedWithMessageController, 'index');

        $v1 = $this->resolver->resolveVersionForRoute($route, '1.0');
        $v2 = $this->resolver->resolveVersionForRoute($route, '2.0');

        expect($v1->isDeprecated)->toBeTrue();
        expect($v1->deprecationMessage)->toBe('v1 is going away');

        expect($v2->isDeprecated)->toBeFalse();
        expect($v2->deprecationMessage)->toBeNull();
    });
});

describe('per-version deprecation via #[MapToApiVersion]', function () {
    test('marks only the version declared deprecated on its own attribute', function () {
        $route = mockRouteFor(new PerVersionDeprecatedMethodController, 'index');

        $v1 = $this->resolver->resolveVersionForRoute($route, '1.0');
        $v2 = $this->resolver->resolveVersionForRoute($route, '2.0');

        expect($v1->isDeprecated)->toBeTrue();
        expect($v1->sunsetDate)->toBe('2026-06-30');
        expect($v1->replacedBy)->toBe('2.0');

        expect($v2->isDeprecated)->toBeFalse();
    });
});
