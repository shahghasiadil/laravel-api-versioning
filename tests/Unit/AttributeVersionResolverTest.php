<?php

namespace ShahGhasiAdil\LaravelApiVersioning\Tests\Unit;

use Illuminate\Foundation\Testing\TestCase;
use ShahGhasiAdil\LaravelApiVersioning\Services\AttributeVersionResolver;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionManager;
use ShahGhasiAdil\LaravelApiVersioning\Examples\V1UserController;
use ShahGhasiAdil\LaravelApiVersioning\Examples\SharedController;

class AttributeVersionResolverTest extends TestCase
{
    private AttributeVersionResolver $resolver;

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

        $versionManager = new VersionManager($config);
        $this->resolver = new AttributeVersionResolver($versionManager);
    }

    public function testResolvesVersionFromControllerAttribute()
    {
        $route = new \Illuminate\Routing\Route(['GET'], 'users', [V1UserController::class, 'index']);
        $route->setContainer($this->app);

        $versionInfo = $this->resolver->resolveVersionForRoute($route, '1.0');

        $this->assertNotNull($versionInfo);
        $this->assertEquals('1.0', $versionInfo->version);
        $this->assertTrue($versionInfo->isDeprecated);
        $this->assertEquals('2025-12-31', $versionInfo->sunsetDate);
    }

    public function testResolvesVersionNeutralEndpoint()
    {
        $route = new \Illuminate\Routing\Route(['GET'], 'health', [SharedController::class, 'health']);
        $route->setContainer($this->app);

        $versionInfo = $this->resolver->resolveVersionForRoute($route, '2.0');

        $this->assertNotNull($versionInfo);
        $this->assertTrue($versionInfo->isNeutral);
    }

    public function testReturnsNullForUnsupportedVersion()
    {
        $route = new \Illuminate\Routing\Route(['GET'], 'users', [V1UserController::class, 'index']);
        $route->setContainer($this->app);

        $versionInfo = $this->resolver->resolveVersionForRoute($route, '3.0');

        $this->assertNull($versionInfo);
    }

    public function testGetAllVersionsForRoute()
    {
        $route = new \Illuminate\Routing\Route(['GET'], 'users', [V1UserController::class, 'index']);
        $route->setContainer($this->app);

        $versions = $this->resolver->getAllVersionsForRoute($route);

        $this->assertEquals(['1.0', '1.1'], $versions);
    }
}
