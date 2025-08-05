<?php

namespace ShahGhasiAdil\LaravelApiVersioning\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use ShahGhasiAdil\LaravelApiVersioning\Services\AttributeVersionResolver;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionManager;
use ShahGhasiAdil\LaravelApiVersioning\Exceptions\UnsupportedVersionException;
use ShahGhasiAdil\LaravelApiVersioning\ValueObjects\VersionInfo;

class AttributeApiVersionMiddleware
{
    public function __construct(
        private VersionManager $versionManager,
        private AttributeVersionResolver $attributeResolver
    ) {}

    public function handle(Request $request, Closure $next): JsonResponse
    {
        try {
            // Detect requested version
            $requestedVersion = $this->versionManager->detectVersionFromRequest($request);

            // Get the current route
            $route = $request->route();

            // Resolve version info using attributes
            $versionInfo = $this->attributeResolver->resolveVersionForRoute($route, $requestedVersion);

            if (!$versionInfo) {
                throw new UnsupportedVersionException(
                    "API version '{$requestedVersion}' is not supported for this endpoint."
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
            return response()->json([
                'error' => 'Unsupported API Version',
                'message' => $e->getMessage(),
                'supported_versions' => $this->versionManager->getSupportedVersions(),
            ], 400);
        }
    }

    private function addVersionHeaders(Response $response, VersionInfo $versionInfo, $route): void
    {
        $response->headers->set('X-API-Version', $versionInfo->version);
        $response->headers->set('X-API-Supported-Versions',
            implode(', ', $this->versionManager->getSupportedVersions()));

        if ($versionInfo->isDeprecated) {
            $response->headers->set('X-API-Deprecated', 'true');

            if ($versionInfo->deprecationMessage) {
                $response->headers->set('X-API-Deprecation-Message', $versionInfo->deprecationMessage);
            }

            if ($versionInfo->sunsetDate) {
                $response->headers->set('X-API-Sunset', $versionInfo->sunsetDate);
            }

            if ($versionInfo->replacedBy) {
                $response->headers->set('X-API-Replaced-By', $versionInfo->replacedBy);
            }
        }

        // Add route-specific supported versions
        $routeVersions = $this->attributeResolver->getAllVersionsForRoute($route);
        if (!empty($routeVersions)) {
            $response->headers->set('X-API-Route-Versions', implode(', ', $routeVersions));
        }
    }
}
