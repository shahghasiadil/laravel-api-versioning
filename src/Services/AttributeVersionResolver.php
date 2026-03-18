<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Services;

use Illuminate\Routing\Route;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\ApiVersionNeutral;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\Contracts\HasVersions;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\Deprecated;
use ShahGhasiAdil\LaravelApiVersioning\ValueObjects\VersionInfo;

class AttributeVersionResolver
{
    /**
     * In-process cache to avoid repeated cache-driver I/O within the same PHP process.
     *
     * @var array<string, VersionInfo|null>
     */
    private static array $memoryCache = [];

    public function __construct(
        private readonly VersionManager $versionManager,
        private readonly AttributeCacheService $cache
    ) {}

    public function resolveVersionForRoute(Route $route, string $requestedVersion): ?VersionInfo
    {
        $controller = $route->getController();
        $action = $route->getActionMethod();

        if ($controller === null) {
            return null;
        }

        $controllerClass = get_class($controller);
        $memoryKey = "{$controllerClass}@{$action}:{$requestedVersion}";

        if (array_key_exists($memoryKey, self::$memoryCache)) {
            return self::$memoryCache[$memoryKey];
        }

        $cacheKey = $this->cache->generateRouteKey($controllerClass, $action, $requestedVersion);

        $result = $this->cache->remember($cacheKey, function () use ($controller, $action, $requestedVersion) {
            $reflectionClass = new ReflectionClass($controller);
            $reflectionMethod = $reflectionClass->getMethod($action);

            // Single pass: check neutral on method and class
            if ($reflectionMethod->getAttributes(ApiVersionNeutral::class) !== [] ||
                $reflectionClass->getAttributes(ApiVersionNeutral::class) !== []) {
                return $this->createVersionInfo(
                    $requestedVersion,
                    true,
                    routeVersions: $this->versionManager->getSupportedVersions()
                );
            }

            // Single pass per reflector using IS_INSTANCEOF to fetch both
            // ApiVersion and MapToApiVersion in one getAttributes() call
            $methodVersionAttrs = $reflectionMethod->getAttributes(HasVersions::class, ReflectionAttribute::IS_INSTANCEOF);
            $methodVersions = $this->flattenVersionAttributes($methodVersionAttrs);

            if ($methodVersions !== [] && in_array($requestedVersion, $methodVersions, true)) {
                return $this->createVersionInfo(
                    $requestedVersion,
                    false,
                    $reflectionMethod,
                    $reflectionClass,
                    routeVersions: $methodVersions
                );
            }

            // Only look at class-level if method had no version attributes
            if ($methodVersions === []) {
                $classVersionAttrs = $reflectionClass->getAttributes(HasVersions::class, ReflectionAttribute::IS_INSTANCEOF);
                $classVersions = $this->flattenVersionAttributes($classVersionAttrs);

                if ($classVersions !== [] && in_array($requestedVersion, $classVersions, true)) {
                    return $this->createVersionInfo(
                        $requestedVersion,
                        false,
                        $reflectionMethod,
                        $reflectionClass,
                        routeVersions: $classVersions
                    );
                }
            }

            return null;
        });

        self::$memoryCache[$memoryKey] = $result;

        return $result;
    }

    /**
     * @return string[]
     */
    public function getAllVersionsForRoute(Route $route): array
    {
        $controller = $route->getController();
        $action = $route->getActionMethod();

        if ($controller === null) {
            return [];
        }

        $controllerClass = get_class($controller);
        $cacheKey = $this->cache->generateRouteVersionsKey($controllerClass, $action);

        return $this->cache->remember($cacheKey, function () use ($controller, $action) {
            $reflectionClass = new ReflectionClass($controller);
            $reflectionMethod = $reflectionClass->getMethod($action);

            if ($reflectionMethod->getAttributes(ApiVersionNeutral::class) !== [] ||
                $reflectionClass->getAttributes(ApiVersionNeutral::class) !== []) {
                return $this->versionManager->getSupportedVersions();
            }

            // Single pass: get all HasVersions attributes from method
            $methodVersionAttrs = $reflectionMethod->getAttributes(HasVersions::class, ReflectionAttribute::IS_INSTANCEOF);
            $methodVersions = $this->flattenVersionAttributes($methodVersionAttrs);

            if ($methodVersions !== []) {
                return $methodVersions;
            }

            // Fall back to class-level
            $classVersionAttrs = $reflectionClass->getAttributes(HasVersions::class, ReflectionAttribute::IS_INSTANCEOF);

            return $this->flattenVersionAttributes($classVersionAttrs);
        });
    }

    /**
     * Reset the in-process memory cache (useful in tests).
     */
    public static function resetMemoryCache(): void
    {
        self::$memoryCache = [];
    }

    private function createVersionInfo(
        string $version,
        bool $isNeutral,
        ?ReflectionMethod $method = null,
        ?ReflectionClass $class = null,
        ?array $routeVersions = null,
    ): VersionInfo {
        $deprecated = null;

        if ($method !== null) {
            $deprecated = $this->getDeprecationInfo($method) ?? $this->getDeprecationInfo($class);
        } elseif ($class !== null) {
            $deprecated = $this->getDeprecationInfo($class);
        }

        return new VersionInfo(
            version: $version,
            isNeutral: $isNeutral,
            isDeprecated: $deprecated !== null,
            deprecationMessage: $deprecated?->message,
            sunsetDate: $deprecated?->sunsetDate,
            replacedBy: $deprecated?->replacedBy,
            routeVersions: $routeVersions,
        );
    }

    private function getDeprecationInfo(ReflectionClass|ReflectionMethod|null $reflection): ?Deprecated
    {
        if ($reflection === null) {
            return null;
        }

        $attributes = $reflection->getAttributes(Deprecated::class);

        return $attributes !== [] ? $attributes[0]->newInstance() : null;
    }

    /**
     * Flatten a list of ReflectionAttribute instances (all implementing HasVersions)
     * into a unique, merged array of version strings.
     *
     * @param  ReflectionAttribute[]  $attributes
     * @return string[]
     */
    private function flattenVersionAttributes(array $attributes): array
    {
        if ($attributes === []) {
            return [];
        }

        return array_unique(array_merge(...array_map(
            fn ($attr) => $attr->newInstance()->getVersions(),
            $attributes
        )));
    }
}
