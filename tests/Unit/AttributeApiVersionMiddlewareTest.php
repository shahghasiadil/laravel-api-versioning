<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use ShahGhasiAdil\LaravelApiVersioning\Exceptions\UnsupportedVersionException;
use ShahGhasiAdil\LaravelApiVersioning\Middleware\AttributeApiVersionMiddleware;
use ShahGhasiAdil\LaravelApiVersioning\Services\AttributeVersionResolver;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionManager;
use ShahGhasiAdil\LaravelApiVersioning\ValueObjects\VersionInfo;

beforeEach(function () {
    $this->versionManager = Mockery::mock(VersionManager::class);
    $this->attributeResolver = Mockery::mock(AttributeVersionResolver::class);
    $this->middleware = new AttributeApiVersionMiddleware($this->versionManager, $this->attributeResolver);
});

afterEach(function () {
    Mockery::close();
});

describe('successful request handling', function () {
    test('processes request with valid version and adds headers', function () {
        $request = Request::create('/api/users');
        $route = Mockery::mock(Route::class);
        $request->setRouteResolver(fn () => $route);

        $versionInfo = new VersionInfo(
            version: '2.0',
            isDeprecated: false,
            isNeutral: false,
            deprecationMessage: null,
            sunsetDate: null,
            replacedBy: null
        );

        $this->versionManager->shouldReceive('detectVersionFromRequest')
            ->with($request)
            ->once()
            ->andReturn('2.0');

        $this->versionManager->shouldReceive('getSupportedVersions')
            ->once()
            ->andReturn(['1.0', '2.0', '2.1']);

        $this->attributeResolver->shouldReceive('resolveVersionForRoute')
            ->with($route, '2.0')
            ->once()
            ->andReturn($versionInfo);

        $this->attributeResolver->shouldReceive('getAllVersionsForRoute')
            ->with($route)
            ->once()
            ->andReturn(['2.0', '2.1']);

        $response = new Response('{"data": "test"}', 200, ['Content-Type' => 'application/json']);

        $result = $this->middleware->handle($request, fn () => $response);

        expect($result)->toBe($response);
        expect($request->attributes->get('api_version_info'))->toBe($versionInfo);
        expect($request->attributes->get('api_version'))->toBe('2.0');
        expect($result->headers->get('X-API-Version'))->toBe('2.0');
        expect($result->headers->get('X-API-Supported-Versions'))->toBe('1.0, 2.0, 2.1');
        expect($result->headers->get('X-API-Route-Versions'))->toBe('2.0, 2.1');
    });

    test('adds deprecation headers for deprecated version', function () {
        $request = Request::create('/api/users');
        $route = Mockery::mock(Route::class);
        $request->setRouteResolver(fn () => $route);

        $versionInfo = new VersionInfo(
            version: '1.0',
            isDeprecated: true,
            isNeutral: false,
            deprecationMessage: 'Use v2.0 instead',
            sunsetDate: '2025-12-31',
            replacedBy: '2.0'
        );

        $this->versionManager->shouldReceive('detectVersionFromRequest')
            ->with($request)
            ->once()
            ->andReturn('1.0');

        $this->versionManager->shouldReceive('getSupportedVersions')
            ->once()
            ->andReturn(['1.0', '2.0']);

        $this->attributeResolver->shouldReceive('resolveVersionForRoute')
            ->with($route, '1.0')
            ->once()
            ->andReturn($versionInfo);

        $this->attributeResolver->shouldReceive('getAllVersionsForRoute')
            ->with($route)
            ->once()
            ->andReturn(['1.0']);

        $response = new Response('{"data": "test"}');

        $result = $this->middleware->handle($request, fn () => $response);

        expect($result->headers->get('X-API-Version'))->toBe('1.0');
        expect($result->headers->get('X-API-Deprecated'))->toBe('true');
        expect($result->headers->get('X-API-Deprecation-Message'))->toBe('Use v2.0 instead');
        expect($result->headers->get('X-API-Sunset'))->toBe('2025-12-31');
        expect($result->headers->get('X-API-Replaced-By'))->toBe('2.0');
    });

    test('handles partial deprecation information', function () {
        $request = Request::create('/api/users');
        $route = Mockery::mock(Route::class);
        $request->setRouteResolver(fn () => $route);

        $versionInfo = new VersionInfo(
            version: '1.0',
            isDeprecated: true,
            isNeutral: false,
            deprecationMessage: null,
            sunsetDate: '2025-06-30',
            replacedBy: null
        );

        $this->versionManager->shouldReceive('detectVersionFromRequest')
            ->andReturn('1.0');

        $this->versionManager->shouldReceive('getSupportedVersions')
            ->andReturn(['1.0', '2.0']);

        $this->attributeResolver->shouldReceive('resolveVersionForRoute')
            ->andReturn($versionInfo);

        $this->attributeResolver->shouldReceive('getAllVersionsForRoute')
            ->andReturn(['1.0']);

        $response = new Response;

        $result = $this->middleware->handle($request, fn () => $response);

        expect($result->headers->get('X-API-Deprecated'))->toBe('true');
        expect($result->headers->get('X-API-Sunset'))->toBe('2025-06-30');
        expect($result->headers->has('X-API-Deprecation-Message'))->toBeFalse();
        expect($result->headers->has('X-API-Replaced-By'))->toBeFalse();
    });
});

describe('error handling', function () {
    test('returns error response for unsupported version', function () {
        $request = Request::create('/api/users');
        $route = Mockery::mock(Route::class);
        $request->setRouteResolver(fn () => $route);

        $this->versionManager->shouldReceive('detectVersionFromRequest')
            ->with($request)
            ->once()
            ->andReturn('3.0');

        $this->attributeResolver->shouldReceive('resolveVersionForRoute')
            ->with($route, '3.0')
            ->once()
            ->andReturn(null);

        $this->attributeResolver->shouldReceive('getAllVersionsForRoute')
            ->with($route)
            ->once()
            ->andReturn(['1.0', '2.0']);

        $this->versionManager->shouldReceive('getSupportedVersions')
            ->once()
            ->andReturn(['1.0', '2.0', '2.1']);

        $result = $this->middleware->handle($request, fn () => new Response);

        expect($result)->toBeInstanceOf(JsonResponse::class);
        expect($result->getStatusCode())->toBe(400);

        $data = $result->getData(true);
        expect($data['title'])->toBe('Unsupported API Version');
        expect($data['detail'])->toBe("API version '3.0' is not supported for this endpoint.");
        expect($data['status'])->toBe(400);
        expect($data['requested_version'])->toBe('3.0');
        expect($data['supported_versions'])->toBe(['1.0', '2.0', '2.1']);
        expect($data['endpoint_versions'])->toBe(['1.0', '2.0']);
    });

    test('handles version manager exception', function () {
        $request = Request::create('/api/users');
        $route = Mockery::mock(Route::class);
        $request->setRouteResolver(fn () => $route);

        $exception = new UnsupportedVersionException(
            message: "API version '4.0' is not supported.",
            supportedVersions: ['1.0', '2.0'],
            requestedVersion: '4.0'
        );

        $this->versionManager->shouldReceive('detectVersionFromRequest')
            ->with($request)
            ->once()
            ->andThrow($exception);

        $this->versionManager->shouldReceive('getSupportedVersions')
            ->once()
            ->andReturn(['1.0', '2.0']);

        $result = $this->middleware->handle($request, fn () => new Response);

        expect($result)->toBeInstanceOf(JsonResponse::class);
        expect($result->getStatusCode())->toBe(400);

        $data = $result->getData(true);
        expect($data['title'])->toBe('Unsupported API Version');
        expect($data['detail'])->toBe("API version '4.0' is not supported for this endpoint.");
        expect($data['status'])->toBe(400);
        expect($data['requested_version'])->toBe('4.0');
        expect($data['supported_versions'])->toBe(['1.0', '2.0']);
    });

    test('handles missing route', function () {
        $request = Request::create('/api/users');
        $request->setRouteResolver(fn () => null);

        $this->versionManager->shouldReceive('detectVersionFromRequest')
            ->with($request)
            ->once()
            ->andReturn('2.0');

        $result = $this->middleware->handle($request, fn () => new Response);

        expect($result)->toBeInstanceOf(JsonResponse::class);
        expect($result->getStatusCode())->toBe(404);

        $data = $result->getData(true);
        expect($data['title'])->toBe('Route Not Found');
        expect($data['detail'])->toBe('Route not found');
        expect($data['status'])->toBe(404);
    });
});

describe('header management', function () {
    test('includes documentation URL when configured', function () {
        config(['api-versioning.documentation.base_url' => 'https://docs.example.com/api']);

        $request = Request::create('/api/users');
        $route = Mockery::mock(Route::class);
        $request->setRouteResolver(fn () => $route);

        $this->versionManager->shouldReceive('detectVersionFromRequest')
            ->andReturn('3.0');

        $this->attributeResolver->shouldReceive('resolveVersionForRoute')
            ->andReturn(null);

        $this->attributeResolver->shouldReceive('getAllVersionsForRoute')
            ->andReturn(['1.0', '2.0']);

        $this->versionManager->shouldReceive('getSupportedVersions')
            ->andReturn(['1.0', '2.0']);

        $result = $this->middleware->handle($request, fn () => new Response);

        $data = $result->getData(true);
        expect($data['documentation'])->toBe('https://docs.example.com/api');
    });

    test('omits route versions header when no versions available', function () {
        $request = Request::create('/api/users');
        $route = Mockery::mock(Route::class);
        $request->setRouteResolver(fn () => $route);

        $versionInfo = new VersionInfo(
            version: '2.0',
            isDeprecated: false,
            isNeutral: false
        );

        $this->versionManager->shouldReceive('detectVersionFromRequest')
            ->andReturn('2.0');

        $this->versionManager->shouldReceive('getSupportedVersions')
            ->andReturn(['1.0', '2.0']);

        $this->attributeResolver->shouldReceive('resolveVersionForRoute')
            ->andReturn($versionInfo);

        $this->attributeResolver->shouldReceive('getAllVersionsForRoute')
            ->andReturn([]);

        $response = new Response;

        $result = $this->middleware->handle($request, fn () => $response);

        expect($result->headers->has('X-API-Route-Versions'))->toBeFalse();
    });
});
