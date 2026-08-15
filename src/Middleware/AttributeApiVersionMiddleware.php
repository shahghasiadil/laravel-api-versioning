<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use ShahGhasiAdil\LaravelApiVersioning\Exceptions\UnsupportedVersionException;
use ShahGhasiAdil\LaravelApiVersioning\Exceptions\VersionProblemReason;
use ShahGhasiAdil\LaravelApiVersioning\Http\Responses\ProblemDetailsResponse;
use ShahGhasiAdil\LaravelApiVersioning\Services\AttributeVersionResolver;
use ShahGhasiAdil\LaravelApiVersioning\Services\SunsetPolicyManager;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionManager;
use ShahGhasiAdil\LaravelApiVersioning\ValueObjects\VersionInfo;
use Symfony\Component\HttpFoundation\Response;

class AttributeApiVersionMiddleware
{
    public function __construct(
        private readonly VersionManager $versionManager,
        private readonly AttributeVersionResolver $attributeResolver,
        private readonly SunsetPolicyManager $sunsetPolicyManager = new SunsetPolicyManager,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Detect requested version
            $requestedVersion = $this->versionManager->detectVersionFromRequest($request);

            // Get the current route
            $route = $request->route();

            if (! $route instanceof Route) {
                return ProblemDetailsResponse::routeNotFound();
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

        // Route versions are embedded in VersionInfo to avoid a second resolver call
        $routeVersions = $versionInfo->routeVersions ?? $this->attributeResolver->getAllVersionsForRoute($route);

        /** @var array<string, mixed> $reportingConfig */
        $reportingConfig = config('api-versioning.reporting', []);
        $standardHeaders = (bool) ($reportingConfig['standard_headers'] ?? true);
        $legacyHeaders = (bool) ($reportingConfig['legacy_headers'] ?? true);

        if ($standardHeaders) {
            if ($routeVersions !== []) {
                $response->headers->set('api-supported-versions', implode(', ', $routeVersions));

                $deprecatedVersions = $this->attributeResolver->getDeprecatedVersionsForRoute($route);
                if ($deprecatedVersions !== []) {
                    $response->headers->set('api-deprecated-versions', implode(', ', $deprecatedVersions));
                }
            }

            $this->addSunsetHeaders($response, $versionInfo);
        }

        if ($legacyHeaders) {
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

            if ($routeVersions !== []) {
                $response->headers->set('X-API-Route-Versions', implode(', ', $routeVersions));
            }
        }
    }

    /**
     * Emit the RFC 8594 'Sunset' header (an HTTP-date) and, when a link is
     * configured, an RFC 8288 'Link' header with rel="sunset". The policy
     * can come from config('api-versioning.sunset_policies') or, absent
     * that, from the sunset date already resolved onto $versionInfo via
     * #[Deprecated]/#[ApiVersion] attributes -- so this is independent of
     * (and additional to) the legacy X-API-Sunset header.
     */
    private function addSunsetHeaders(Response $response, VersionInfo $versionInfo): void
    {
        $policy = $this->sunsetPolicyManager->getPolicy($versionInfo->version, $versionInfo->sunsetDate);

        if ($policy === null) {
            return;
        }

        if ($policy->hasDate()) {
            $response->headers->set('Sunset', $policy->formattedDate());
        }

        if ($policy->hasLinks()) {
            $response->headers->set('Link', implode(', ', $policy->formattedLinks()));
        }
    }

    private function createErrorResponse(UnsupportedVersionException $e): ProblemDetailsResponse
    {
        /** @var string|null $configuredDocumentationUrl */
        $configuredDocumentationUrl = config('api-versioning.documentation.base_url');
        $documentationUrl = is_string($configuredDocumentationUrl) && $configuredDocumentationUrl !== ''
            ? $configuredDocumentationUrl
            : null;

        if ($e->reason === VersionProblemReason::Unsupported) {
            return ProblemDetailsResponse::unsupportedVersion(
                requestedVersion: $e->requestedVersion ?? 'unknown',
                supportedVersions: $this->versionManager->getSupportedVersions(),
                endpointVersions: $e->supportedVersions,
                documentationUrl: $documentationUrl
            );
        }

        return ProblemDetailsResponse::versionProblem(
            reason: $e->reason,
            requestedVersion: $e->requestedVersion,
            supportedVersions: $this->versionManager->getSupportedVersions(),
            context: $e->context,
            documentationUrl: $documentationUrl
        );
    }
}
