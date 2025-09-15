<?php

use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\Router;
use ShahGhasiAdil\LaravelApiVersioning\Console\Commands\ApiVersionsCommand;
use ShahGhasiAdil\LaravelApiVersioning\Services\AttributeVersionResolver;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionManager;
use ShahGhasiAdil\LaravelApiVersioning\ValueObjects\VersionInfo;

beforeEach(function () {
    $this->router = Mockery::mock(Router::class);
    $this->versionManager = Mockery::mock(VersionManager::class);
    $this->resolver = Mockery::mock(AttributeVersionResolver::class);

    $this->command = new ApiVersionsCommand($this->router, $this->versionManager, $this->resolver);
});

afterEach(function () {
    Mockery::close();
});

describe('route filtering and display', function () {
    test('displays api routes with version information', function () {
        $routes = new RouteCollection;

        // Create mock routes
        $route1 = Mockery::mock(Route::class);
        $route1->shouldReceive('uri')->andReturn('api/users');
        $route1->shouldReceive('methods')->andReturn(['GET']);
        $route1->shouldReceive('getActionName')->andReturn('UserController@index');
        $route1->shouldReceive('getDomain')->andReturn('');
        $route1->shouldReceive('getName')->andReturn(null);
        $route1->shouldReceive('getAction')->andReturn([]);

        $route2 = Mockery::mock(Route::class);
        $route2->shouldReceive('uri')->andReturn('api/posts');
        $route2->shouldReceive('methods')->andReturn(['GET', 'POST']);
        $route2->shouldReceive('getActionName')->andReturn('PostController@index');
        $route2->shouldReceive('getDomain')->andReturn('');
        $route2->shouldReceive('getName')->andReturn(null);
        $route2->shouldReceive('getAction')->andReturn([]);

        $routes->add($route1);
        $routes->add($route2);

        $this->router->shouldReceive('getRoutes')->andReturn($routes);

        $this->resolver->shouldReceive('getAllVersionsForRoute')
            ->with($route1)
            ->andReturn(['1.0', '2.0']);

        $this->resolver->shouldReceive('getAllVersionsForRoute')
            ->with($route2)
            ->andReturn(['2.0', '2.1']);

        // Mock version info for deprecation check
        $versionInfo1 = new VersionInfo('1.0', false, false);
        $versionInfo2 = new VersionInfo('2.0', false, false);

        $this->resolver->shouldReceive('resolveVersionForRoute')
            ->andReturn($versionInfo1, $versionInfo2);

        $this->versionManager->shouldReceive('getSupportedVersions')
            ->andReturn(['1.0', '2.0', '2.1']);

        $output = new \Symfony\Component\Console\Output\BufferedOutput;
        $style = new \Illuminate\Console\OutputStyle(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            $output
        );
        $this->command->setOutput($style);

        $input = new \Symfony\Component\Console\Input\ArrayInput([]);
        $input->bind($this->command->getDefinition());
        $this->command->setInput($input);

        $result = $this->command->handle();

        expect($result)->toBe(0);
    });

    test('filters routes by pattern', function () {
        $routes = new RouteCollection;

        $route1 = Mockery::mock(Route::class);
        $route1->shouldReceive('uri')->andReturn('api/users');
        $route1->shouldReceive('methods')->andReturn(['GET']);
        $route1->shouldReceive('getActionName')->andReturn('UserController@index');
        $route1->shouldReceive('getDomain')->andReturn('');
        $route1->shouldReceive('getName')->andReturn(null);
        $route1->shouldReceive('getAction')->andReturn([]);

        $route2 = Mockery::mock(Route::class);
        $route2->shouldReceive('uri')->andReturn('api/posts');
        $route2->shouldReceive('methods')->andReturn(['GET']);
        $route2->shouldReceive('getActionName')->andReturn('PostController@index');
        $route2->shouldReceive('getDomain')->andReturn('');
        $route2->shouldReceive('getName')->andReturn(null);
        $route2->shouldReceive('getAction')->andReturn([]);

        $route3 = Mockery::mock(Route::class);
        $route3->shouldReceive('uri')->andReturn('web/dashboard');
        $route3->shouldReceive('methods')->andReturn(['GET']);
        $route3->shouldReceive('getDomain')->andReturn('');
        $route3->shouldReceive('getName')->andReturn(null);
        $route3->shouldReceive('getAction')->andReturn([]);

        $routes->add($route1);
        $routes->add($route2);
        $routes->add($route3);

        $this->router->shouldReceive('getRoutes')->andReturn($routes);

        // Only api routes should be processed, web routes filtered out
        $this->resolver->shouldReceive('getAllVersionsForRoute')
            ->with($route1)
            ->andReturn(['2.0']);

        $this->resolver->shouldReceive('getAllVersionsForRoute')
            ->with($route2)
            ->andReturn(['2.0']);

        $this->resolver->shouldReceive('resolveVersionForRoute')
            ->andReturn(new VersionInfo('2.0', false, false));

        $this->versionManager->shouldReceive('getSupportedVersions')
            ->andReturn(['1.0', '2.0']);

        $output = new \Symfony\Component\Console\Output\BufferedOutput;
        $style = new \Illuminate\Console\OutputStyle(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            $output
        );
        $this->command->setOutput($style);

        // Test with route filter
        $input = new \Symfony\Component\Console\Input\ArrayInput(['--route' => 'users']);
        $input->bind($this->command->getDefinition());
        $this->command->setInput($input);

        $result = $this->command->handle();

        expect($result)->toBe(0);
    });

    test('filters routes by api version', function () {
        $routes = new RouteCollection;

        $route1 = Mockery::mock(Route::class);
        $route1->shouldReceive('uri')->andReturn('api/users');
        $route1->shouldReceive('methods')->andReturn(['GET']);
        $route1->shouldReceive('getActionName')->andReturn('UserController@index');
        $route1->shouldReceive('getDomain')->andReturn('');
        $route1->shouldReceive('getName')->andReturn(null);
        $route1->shouldReceive('getAction')->andReturn([]);

        $routes->add($route1);

        $this->router->shouldReceive('getRoutes')->andReturn($routes);

        $this->resolver->shouldReceive('getAllVersionsForRoute')
            ->with($route1)
            ->andReturn(['1.0', '2.0']);

        $this->resolver->shouldReceive('resolveVersionForRoute')
            ->andReturn(new VersionInfo('1.0', false, false));

        $this->versionManager->shouldReceive('getSupportedVersions')
            ->andReturn(['1.0', '2.0']);

        $output = new \Symfony\Component\Console\Output\BufferedOutput;
        $style = new \Illuminate\Console\OutputStyle(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            $output
        );
        $this->command->setOutput($style);

        $input = new \Symfony\Component\Console\Input\ArrayInput(['--api-version' => '2.0']);
        $input->bind($this->command->getDefinition());
        $this->command->setInput($input);

        $result = $this->command->handle();

        expect($result)->toBe(0);
    });

    test('shows only deprecated endpoints when flag is set', function () {
        $routes = new RouteCollection;

        $route1 = Mockery::mock(Route::class);
        $route1->shouldReceive('uri')->andReturn('api/users');
        $route1->shouldReceive('methods')->andReturn(['GET']);
        $route1->shouldReceive('getActionName')->andReturn('UserController@index');
        $route1->shouldReceive('getDomain')->andReturn('');
        $route1->shouldReceive('getName')->andReturn(null);
        $route1->shouldReceive('getAction')->andReturn([]);

        $routes->add($route1);

        $this->router->shouldReceive('getRoutes')->andReturn($routes);

        $this->resolver->shouldReceive('getAllVersionsForRoute')
            ->with($route1)
            ->andReturn(['1.0']);

        // Mock deprecated version
        $deprecatedVersionInfo = new VersionInfo(
            version: '1.0',
            isDeprecated: true,
            isNeutral: false,
            deprecationMessage: 'Use v2.0',
            sunsetDate: '2025-12-31',
            replacedBy: '2.0'
        );

        $this->resolver->shouldReceive('resolveVersionForRoute')
            ->with($route1, '1.0')
            ->andReturn($deprecatedVersionInfo);

        $this->versionManager->shouldReceive('getSupportedVersions')
            ->andReturn(['1.0', '2.0']);

        $output = new \Symfony\Component\Console\Output\BufferedOutput;
        $style = new \Illuminate\Console\OutputStyle(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            $output
        );
        $this->command->setOutput($style);

        $input = new \Symfony\Component\Console\Input\ArrayInput(['--deprecated' => true]);
        $input->bind($this->command->getDefinition());
        $this->command->setInput($input);

        $result = $this->command->handle();

        expect($result)->toBe(0);
    });
});

describe('deprecation information handling', function () {
    test('displays deprecation information correctly', function () {
        $routes = new RouteCollection;

        $route = Mockery::mock(Route::class);
        $route->shouldReceive('uri')->andReturn('api/users');
        $route->shouldReceive('methods')->andReturn(['GET']);
        $route->shouldReceive('getActionName')->andReturn('UserController@index');
        $route->shouldReceive('getDomain')->andReturn('');
        $route->shouldReceive('getName')->andReturn(null);
        $route->shouldReceive('getAction')->andReturn([]);

        $routes->add($route);

        $this->router->shouldReceive('getRoutes')->andReturn($routes);

        $this->resolver->shouldReceive('getAllVersionsForRoute')
            ->with($route)
            ->andReturn(['1.0', '2.0']);

        // First version is deprecated
        $deprecatedInfo = new VersionInfo(
            version: '1.0',
            isDeprecated: true,
            isNeutral: false,
            deprecationMessage: null,
            sunsetDate: '2025-06-30',
            replacedBy: null
        );

        // Second version is not deprecated
        $currentInfo = new VersionInfo('2.0', false, false);

        $this->resolver->shouldReceive('resolveVersionForRoute')
            ->with($route, '1.0')
            ->andReturn($deprecatedInfo);

        $this->resolver->shouldReceive('resolveVersionForRoute')
            ->with($route, '2.0')
            ->andReturn($currentInfo);

        $this->versionManager->shouldReceive('getSupportedVersions')
            ->andReturn(['1.0', '2.0']);

        $output = new \Symfony\Component\Console\Output\BufferedOutput;
        $style = new \Illuminate\Console\OutputStyle(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            $output
        );
        $this->command->setOutput($style);

        $input = new \Symfony\Component\Console\Input\ArrayInput([]);
        $input->bind($this->command->getDefinition());
        $this->command->setInput($input);

        $result = $this->command->handle();

        expect($result)->toBe(0);
    });

    test('handles routes with no versions', function () {
        $routes = new RouteCollection;

        $route = Mockery::mock(Route::class);
        $route->shouldReceive('uri')->andReturn('api/health');
        $route->shouldReceive('methods')->andReturn(['GET']);
        $route->shouldReceive('getActionName')->andReturn('HealthController@check');
        $route->shouldReceive('getDomain')->andReturn('');
        $route->shouldReceive('getName')->andReturn(null);
        $route->shouldReceive('getAction')->andReturn([]);

        $routes->add($route);

        $this->router->shouldReceive('getRoutes')->andReturn($routes);

        $this->resolver->shouldReceive('getAllVersionsForRoute')
            ->with($route)
            ->andReturn([]);

        $this->versionManager->shouldReceive('getSupportedVersions')
            ->andReturn(['1.0', '2.0']);

        $output = new \Symfony\Component\Console\Output\BufferedOutput;
        $style = new \Illuminate\Console\OutputStyle(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            $output
        );
        $this->command->setOutput($style);

        $input = new \Symfony\Component\Console\Input\ArrayInput([]);
        $input->bind($this->command->getDefinition());
        $this->command->setInput($input);

        $result = $this->command->handle();

        expect($result)->toBe(0);
    });
});

describe('error handling', function () {
    test('handles version resolution exceptions gracefully', function () {
        $routes = new RouteCollection;

        $route = Mockery::mock(Route::class);
        $route->shouldReceive('uri')->andReturn('api/users');
        $route->shouldReceive('methods')->andReturn(['GET']);
        $route->shouldReceive('getActionName')->andReturn('UserController@index');
        $route->shouldReceive('getDomain')->andReturn('');
        $route->shouldReceive('getName')->andReturn(null);
        $route->shouldReceive('getAction')->andReturn([]);

        $routes->add($route);

        $this->router->shouldReceive('getRoutes')->andReturn($routes);

        $this->resolver->shouldReceive('getAllVersionsForRoute')
            ->with($route)
            ->andReturn(['1.0']);

        // Simulate exception during version resolution
        $this->resolver->shouldReceive('resolveVersionForRoute')
            ->with($route, '1.0')
            ->andThrow(new Exception('Version resolution failed'));

        $this->versionManager->shouldReceive('getSupportedVersions')
            ->andReturn(['1.0', '2.0']);

        $output = new \Symfony\Component\Console\Output\BufferedOutput;
        $style = new \Illuminate\Console\OutputStyle(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            $output
        );
        $this->command->setOutput($style);

        $input = new \Symfony\Component\Console\Input\ArrayInput([]);
        $input->bind($this->command->getDefinition());
        $this->command->setInput($input);

        $result = $this->command->handle();

        expect($result)->toBe(0); // Should handle gracefully
    });

    test('displays message when no routes found', function () {
        $routes = new RouteCollection;

        $this->router->shouldReceive('getRoutes')->andReturn($routes);

        $this->versionManager->shouldReceive('getSupportedVersions')
            ->andReturn(['1.0', '2.0']);

        $output = new \Symfony\Component\Console\Output\BufferedOutput;
        $style = new \Illuminate\Console\OutputStyle(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            $output
        );
        $this->command->setOutput($style);

        $input = new \Symfony\Component\Console\Input\ArrayInput([]);
        $input->bind($this->command->getDefinition());
        $this->command->setInput($input);

        $result = $this->command->handle();

        expect($result)->toBe(0);
        expect($output->fetch())->toContain('No matching routes found');
    });

    test('displays message when filtered routes not found', function () {
        $routes = new RouteCollection;

        $route = Mockery::mock(Route::class);
        $route->shouldReceive('uri')->andReturn('api/posts');
        $route->shouldReceive('methods')->andReturn(['GET']);
        $route->shouldReceive('getDomain')->andReturn('');
        $route->shouldReceive('getName')->andReturn(null);
        $route->shouldReceive('getAction')->andReturn([]);

        $routes->add($route);

        $this->router->shouldReceive('getRoutes')->andReturn($routes);

        $this->versionManager->shouldReceive('getSupportedVersions')
            ->andReturn(['1.0', '2.0']);

        $output = new \Symfony\Component\Console\Output\BufferedOutput;
        $style = new \Illuminate\Console\OutputStyle(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            $output
        );
        $this->command->setOutput($style);

        // Filter for routes containing 'users' but only 'posts' exists
        $input = new \Symfony\Component\Console\Input\ArrayInput(['--route' => 'users']);
        $input->bind($this->command->getDefinition());
        $this->command->setInput($input);

        $result = $this->command->handle();

        expect($result)->toBe(0);
        expect($output->fetch())->toContain('No matching routes found');
    });
});
