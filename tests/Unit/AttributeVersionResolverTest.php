<?php

namespace ShahGhasiAdil\LaravelApiVersioning\Tests\Unit;

use Illuminate\Routing\Route;
use ShahGhasiAdil\LaravelApiVersioning\Examples\SharedController;
use ShahGhasiAdil\LaravelApiVersioning\Examples\V1UserController;
use ShahGhasiAdil\LaravelApiVersioning\Services\AttributeVersionResolver;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionManager;
use ShahGhasiAdil\LaravelApiVersioning\Tests\TestCase;

class AttributeVersionResolverTest extends TestCase
{
    private AttributeVersionResolver $resolver;

    private VersionManager $versionManager;

    protected function setUp(): void
    {
        parent::setUp();

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
        $this->resolver = new AttributeVersionResolver($this->versionManager);
    }

    public function test_resolves_version_from_controller_attribute()
    {
        // Create a mock route with controller
        $controller = new V1UserController;
        $route = new Route(['GET'], 'users', [V1UserController::class, 'index']);
        $route->setContainer($this->app);

        // We need to manually set the controller since we're not going through normal routing
        $route->setAction(['uses' => V1UserController::class.'@index']);

        // Mock the route to return our controller
        $route = $this->createMockRoute($controller, 'index');

        $versionInfo = $this->resolver->resolveVersionForRoute($route, '1.0');

        self::assertNotNull($versionInfo);
        self::assertEquals('1.0', $versionInfo->version);
        self::assertTrue($versionInfo->isDeprecated);
        self::assertEquals('2025-12-31', $versionInfo->sunsetDate);
    }

    public function test_resolves_version_neutral_endpoint()
    {
        $controller = new SharedController;
        $route = $this->createMockRoute($controller, 'health');

        $versionInfo = $this->resolver->resolveVersionForRoute($route, '2.0');

        self::assertNotNull($versionInfo);
        self::assertTrue($versionInfo->isNeutral);
    }

    public function test_returns_null_for_unsupported_version()
    {
        $controller = new V1UserController;
        $route = $this->createMockRoute($controller, 'index');

        $versionInfo = $this->resolver->resolveVersionForRoute($route, '3.0');

        self::assertNull($versionInfo);
    }

    public function test_get_all_versions_for_route()
    {
        $controller = new V1UserController;
        $route = $this->createMockRoute($controller, 'index');

        $versions = $this->resolver->getAllVersionsForRoute($route);

        self::assertEquals(['1.0', '1.1'], $versions);
    }

    /**
     * Create a mock route that properly returns controller and action method
     */
    private function createMockRoute($controller, string $method): Route
    {
        $route = $this->createMock(Route::class);

        $route->method('getController')
            ->willReturn($controller);

        $route->method('getActionMethod')
            ->willReturn($method);

        return $route;
    }
}
