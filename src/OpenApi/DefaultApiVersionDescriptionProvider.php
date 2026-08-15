<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\OpenApi;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use ShahGhasiAdil\LaravelApiVersioning\Services\AttributeVersionResolver;
use ShahGhasiAdil\LaravelApiVersioning\Services\SunsetPolicyManager;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionComparator;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionManager;
use ShahGhasiAdil\LaravelApiVersioning\ValueObjects\ApiVersionDescription;
use ShahGhasiAdil\LaravelApiVersioning\ValueObjects\RouteDescription;
use ShahGhasiAdil\LaravelApiVersioning\ValueObjects\SunsetPolicy;

/**
 * Builds {@see ApiVersionDescription}s from the same attribute-resolution
 * machinery {@see \ShahGhasiAdil\LaravelApiVersioning\Console\Commands\ApiVersionsCommand}
 * uses ({@see AttributeVersionResolver}), so the two never disagree about
 * what a version is or which routes serve it.
 */
class DefaultApiVersionDescriptionProvider implements ApiVersionDescriptionProvider
{
    public function __construct(
        private readonly Router $router,
        private readonly VersionManager $versionManager,
        private readonly AttributeVersionResolver $resolver,
        private readonly SunsetPolicyManager $sunsetPolicyManager,
        private readonly VersionComparator $comparator,
    ) {}

    /**
     * @return ApiVersionDescription[]
     */
    public function describe(): array
    {
        $pathBase = $this->configuredPathBase();

        /** @var array<string, RouteDescription[]> $routesByVersion */
        $routesByVersion = [];
        /** @var array<string, bool> $deprecatedVersions */
        $deprecatedVersions = [];
        /** @var array<string, string|null> $sunsetDateByVersion */
        $sunsetDateByVersion = [];

        /** @var Route[] $routes */
        $routes = iterator_to_array($this->router->getRoutes(), false);

        foreach ($routes as $route) {
            if (! str_starts_with($route->uri(), $pathBase)) {
                continue;
            }

            $versions = $this->resolver->getAllVersionsForRoute($route);

            /** @var string[] $routeMethods */
            $routeMethods = $route->methods();

            foreach ($versions as $version) {
                $routesByVersion[$version][] = new RouteDescription(
                    $routeMethods,
                    $route->uri(),
                    $route->getActionName(),
                );

                if (array_key_exists($version, $deprecatedVersions)) {
                    continue;
                }

                $info = $this->resolver->resolveVersionForRoute($route, $version);

                if ($info !== null && $info->isDeprecated) {
                    $deprecatedVersions[$version] = true;
                    $sunsetDateByVersion[$version] = $info->sunsetDate;
                }
            }
        }

        $allVersions = array_values(array_unique([
            ...$this->versionManager->getSupportedVersions(),
            ...array_keys($routesByVersion),
        ]));

        $sortedVersions = $this->comparator->sort($allVersions);

        return array_map(function (string $version) use ($routesByVersion, $deprecatedVersions, $sunsetDateByVersion): ApiVersionDescription {
            $isDeprecated = $deprecatedVersions[$version] ?? false;

            return new ApiVersionDescription(
                version: $version,
                isDeprecated: $isDeprecated,
                sunsetPolicy: $isDeprecated
                    ? $this->sunsetPolicyManager->getPolicy($version, $sunsetDateByVersion[$version] ?? null)
                    : $this->impliedSunsetPolicy($version),
                routes: $routesByVersion[$version] ?? [],
            );
        }, $sortedVersions);
    }

    /**
     * A version can also have a sunset policy declared purely via
     * 'sunset_policies' config, independent of any #[Deprecated] attribute.
     */
    private function impliedSunsetPolicy(string $version): ?SunsetPolicy
    {
        return $this->sunsetPolicyManager->getPolicy($version);
    }

    /**
     * The same configured-path-prefix derivation
     * {@see \ShahGhasiAdil\LaravelApiVersioning\Console\Commands\ApiVersionsCommand}
     * uses, so `describe()` and `api:versions` agree on which routes count.
     */
    private function configuredPathBase(): string
    {
        /** @var mixed $prefix */
        $prefix = config('api-versioning.detection_methods.path.prefix', 'api/v');
        $prefix = is_string($prefix) && $prefix !== '' ? $prefix : 'api/v';

        $lastSlash = strrpos($prefix, '/');

        return $lastSlash !== false ? substr($prefix, 0, $lastSlash + 1) : $prefix;
    }
}
