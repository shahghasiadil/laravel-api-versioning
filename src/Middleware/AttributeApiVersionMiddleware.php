<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use ShahGhasiAdil\LaravelApiVersioning\Exceptions\UnsupportedVersionException;
use ShahGhasiAdil\LaravelApiVersioning\Services\AttributeVersionResolver;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionManager;
use ShahGhasiAdil\LaravelApiVersioning\ValueObjects\VersionInfo;
use Symfony\Component\HttpFoundation\Response;

class AttributeApiVersionMiddleware
{
    public function __construct(
        private readonly VersionManager $versionManager,
        private readonly AttributeVersionResolver $attributeResolver
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Detect requested version
            $requestedVersion = $this->versionManager->detectVersionFromRequest($request);

            // Get the current route
            $route = $request->route();

            if (! $route instanceof Route) {
                throw new UnsupportedVersionException('Route not found');
            }

            // Resolve version info using attributes
            $versionInfo = $this->attributeResolver->resolveVersionForRoute($route, $requestedVersion);

            if ($versionInfo === null) {
                throw new UnsupportedVersionException(
                    message: "API version '{$requestedVersion}' is not supported for this endpoint.",
                    supportedVersions: $this->attributeResolver->getAllVersionsForRoute($route),
                    requestedVersion: $requestedVersion
                );
            }

            // Store version info in request
            $request->attributes->set('api_version_info', $versionInfo);
            $request->attributes->set('api_version', $requestedVersion);

            // Process request
            $response = $next($request);

            // Add version headers
            $this->addVersionHeaders($response, $versionInfo, $route);

            return $response;

        } catch (UnsupportedVersionException $e) {
            return $this->createErrorResponse($e);
        }
    }

    private function addVersionHeaders(Response $response, VersionInfo $versionInfo, Route $route): void
    {
        $response->headers->set('X-API-Version', $versionInfo->version);
        $response->headers->set('X-API-Supported-Versions',
            implode(', ', $this->versionManager->getSupportedVersions()));

        if ($versionInfo->isDeprecated) {
            $response->headers->set('X-API-Deprecated', 'true');

            if ($versionInfo->deprecationMessage !== null) {
                $response->headers->set('X-API-Deprecation-Message', $versionInfo->deprecationMessage);
            }

            if ($versionInfo->sunsetDate !== null) {
                $response->headers->set('X-API-Sunset', $versionInfo->sunsetDate);
            }

            if ($versionInfo->replacedBy !== null) {
                $response->headers->set('X-API-Replaced-By', $versionInfo->replacedBy);
            }
        }

        // Add route-specific supported versions
        $routeVersions = $this->attributeResolver->getAllVersionsForRoute($route);
        if ($routeVersions !== []) {
            $response->headers->set('X-API-Route-Versions', implode(', ', $routeVersions));
        }
    }

    private function createErrorResponse(UnsupportedVersionException $e): JsonResponse
    {
        $data = [
            'error' => 'Unsupported API Version',
            'message' => $e->getMessage(),
            'supported_versions' => $this->versionManager->getSupportedVersions(),
        ];

        if ($e->requestedVersion !== null) {
            $data['requested_version'] = $e->requestedVersion;
        }

        if ($e->supportedVersions !== []) {
            $data['endpoint_versions'] = $e->supportedVersions;
        }

        $documentationUrl = config('api-versioning.documentation.base_url');
        if ($documentationUrl !== null) {
            $data['documentation'] = $documentationUrl;
        }

        return response()->json($data, 400);
    }
}
