<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Services;

use Illuminate\Routing\Route;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\ApiVersionNeutral;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\Contracts\HasVersionDeprecation;
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
            // Closure routes have no class/method to carry attributes, so
            // there is nothing to resolve against. By default they are
            // treated as version-neutral (respond to every supported
            // version), matching #[ApiVersionNeutral]; set
            // 'closure_routes' => 'reject' to restore the original
            // behavior of rejecting every version on closure routes.
            return $this->closureRoutesAreNeutral()
                ? $this->createVersionInfo(
                    $requestedVersion,
                    true,
                    routeVersions: $this->versionManager->getSupportedVersions()
                )
                : null;
        }

        $controllerClass = get_class($controller);
        $memoryKey = "{$controllerClass}@{$action}:{$requestedVersion}";

        if (array_key_exists($memoryKey, self::$memoryCache)) {
            return self::$memoryCache[$memoryKey];
        }

        $cacheKey = $this->cache->generateRouteKey($controllerClass, $action, $requestedVersion);

        /** @var VersionInfo|null $result */
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
            $methodMetadata = $this->collectVersionMetadata($methodVersionAttrs);

            if ($methodMetadata->versions !== [] && in_array($requestedVersion, $methodMetadata->versions, true)) {
                return $this->createVersionInfo(
                    $requestedVersion,
                    false,
                    $reflectionMethod,
                    $reflectionClass,
                    routeVersions: $methodMetadata->versions,
                    perVersionDeprecation: $methodMetadata->deprecated,
                );
            }

            // Only look at class-level if method had no version attributes
            if ($methodMetadata->versions === []) {
                $classVersionAttrs = $reflectionClass->getAttributes(HasVersions::class, ReflectionAttribute::IS_INSTANCEOF);
                $classMetadata = $this->collectVersionMetadata($classVersionAttrs);

                if ($classMetadata->versions !== [] && in_array($requestedVersion, $classMetadata->versions, true)) {
                    return $this->createVersionInfo(
                        $requestedVersion,
                        false,
                        $reflectionMethod,
                        $reflectionClass,
                        routeVersions: $classMetadata->versions,
                        perVersionDeprecation: $classMetadata->deprecated,
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
            return $this->closureRoutesAreNeutral() ? $this->versionManager->getSupportedVersions() : [];
        }

        $controllerClass = get_class($controller);
        $cacheKey = $this->cache->generateRouteVersionsKey($controllerClass, $action);

        /** @var string[] $result */
        $result = $this->cache->remember($cacheKey, function () use ($controller, $action) {
            $reflectionClass = new ReflectionClass($controller);
            $reflectionMethod = $reflectionClass->getMethod($action);

            if ($reflectionMethod->getAttributes(ApiVersionNeutral::class) !== [] ||
                $reflectionClass->getAttributes(ApiVersionNeutral::class) !== []) {
                return $this->versionManager->getSupportedVersions();
            }

            // Single pass: get all HasVersions attributes from method
            $methodVersionAttrs = $reflectionMethod->getAttributes(HasVersions::class, ReflectionAttribute::IS_INSTANCEOF);
            $methodVersions = $this->collectVersionMetadata($methodVersionAttrs)->versions;

            if ($methodVersions !== []) {
                return $methodVersions;
            }

            // Fall back to class-level
            $classVersionAttrs = $reflectionClass->getAttributes(HasVersions::class, ReflectionAttribute::IS_INSTANCEOF);

            return $this->collectVersionMetadata($classVersionAttrs)->versions;
        });

        return $result;
    }

    /**
     * The subset of a route's declared versions that are deprecated, whether
     * via per-version #[ApiVersion]/#[MapToApiVersion] attributes or a
     * coarse #[Deprecated] attribute (which deprecates every version the
     * route declares).
     *
     * @return string[]
     */
    public function getDeprecatedVersionsForRoute(Route $route): array
    {
        $controller = $route->getController();
        $action = $route->getActionMethod();

        if ($controller === null) {
            return [];
        }

        $controllerClass = get_class($controller);
        $cacheKey = $this->cache->generateRouteDeprecatedVersionsKey($controllerClass, $action);

        /** @var string[] $result */
        $result = $this->cache->remember($cacheKey, function () use ($controller, $action) {
            $reflectionClass = new ReflectionClass($controller);
            $reflectionMethod = $reflectionClass->getMethod($action);

            if ($reflectionMethod->getAttributes(ApiVersionNeutral::class) !== [] ||
                $reflectionClass->getAttributes(ApiVersionNeutral::class) !== []) {
                return [];
            }

            $methodVersionAttrs = $reflectionMethod->getAttributes(HasVersions::class, ReflectionAttribute::IS_INSTANCEOF);
            $methodMetadata = $this->collectVersionMetadata($methodVersionAttrs);

            $metadata = $methodMetadata->versions !== []
                ? $methodMetadata
                : $this->collectVersionMetadata($reflectionClass->getAttributes(HasVersions::class, ReflectionAttribute::IS_INSTANCEOF));

            if ($metadata->deprecated !== []) {
                return array_keys($metadata->deprecated);
            }

            // No per-version deprecation declared: fall back to the coarse
            // #[Deprecated] attribute, which deprecates every version.
            $coarseDeprecated = $this->getDeprecationInfo($reflectionMethod) ?? $this->getDeprecationInfo($reflectionClass);

            return $coarseDeprecated !== null ? $metadata->versions : [];
        });

        return $result;
    }

    /**
     * Reset the in-process memory cache (useful in tests).
     */
    public static function resetMemoryCache(): void
    {
        self::$memoryCache = [];
    }

    /**
     * Whether a route with no controller (a Closure route, or a Minimal-
     * API-style callable route) should be treated as version-neutral.
     */
    private function closureRoutesAreNeutral(): bool
    {
        /** @var string $mode */
        $mode = config('api-versioning.closure_routes', 'neutral');

        return $mode !== 'reject';
    }

    /**
     * @param  string[]|null  $routeVersions
     * @param  array<string, array{sunset: string|null, replacedBy: string|null}>  $perVersionDeprecation
     *                                                                                                     Versions explicitly marked deprecated on the #[ApiVersion]/#[MapToApiVersion]
     *                                                                                                     attribute that declared them. A non-empty array here means per-version
     *                                                                                                     deprecation is in use for this route, which takes precedence over the
     *                                                                                                     coarse-grained #[Deprecated] attribute for deciding *which* versions are
     *                                                                                                     deprecated (though #[Deprecated]'s message/sunset/replacedBy are still used
     *                                                                                                     to fill in anything the attribute itself didn't specify).
     */
    private function createVersionInfo(
        string $version,
        bool $isNeutral,
        ?ReflectionMethod $method = null,
        ?ReflectionClass $class = null,
        ?array $routeVersions = null,
        array $perVersionDeprecation = [],
    ): VersionInfo {
        $coarseDeprecated = null;

        if ($method !== null) {
            $coarseDeprecated = $this->getDeprecationInfo($method) ?? $this->getDeprecationInfo($class);
        } elseif ($class !== null) {
            $coarseDeprecated = $this->getDeprecationInfo($class);
        }

        if ($perVersionDeprecation !== []) {
            // Per-version deprecation is in use for this route: only the versions
            // explicitly marked deprecated on their declaring attribute are deprecated,
            // regardless of a coarse #[Deprecated] attribute elsewhere on the class/method.
            $versionOverride = $perVersionDeprecation[$version] ?? null;
            $isDeprecated = $versionOverride !== null;

            return new VersionInfo(
                version: $version,
                isNeutral: $isNeutral,
                isDeprecated: $isDeprecated,
                deprecationMessage: $isDeprecated ? $coarseDeprecated?->message : null,
                sunsetDate: $isDeprecated ? ($versionOverride['sunset'] ?? $coarseDeprecated?->sunsetDate) : null,
                replacedBy: $isDeprecated ? ($versionOverride['replacedBy'] ?? $coarseDeprecated?->replacedBy) : null,
                routeVersions: $routeVersions,
            );
        }

        return new VersionInfo(
            version: $version,
            isNeutral: $isNeutral,
            isDeprecated: $coarseDeprecated !== null,
            deprecationMessage: $coarseDeprecated?->message,
            sunsetDate: $coarseDeprecated?->sunsetDate,
            replacedBy: $coarseDeprecated?->replacedBy,
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
     * Collect the merged, deduplicated version list from a set of HasVersions
     * attribute instances, along with a map of any versions individually
     * marked deprecated on the attribute that declared them.
     *
     * @param  ReflectionAttribute[]  $attributes
     */
    private function collectVersionMetadata(array $attributes): VersionAttributeMetadata
    {
        if ($attributes === []) {
            return new VersionAttributeMetadata([], []);
        }

        $versions = [];
        $deprecated = [];

        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();
            /** @var string[] $instanceVersions */
            $instanceVersions = $instance->getVersions();
            $versions = array_merge($versions, $instanceVersions);

            if ($instance instanceof HasVersionDeprecation && $instance->isDeprecated()) {
                foreach ($instanceVersions as $instanceVersion) {
                    $deprecated[$instanceVersion] = [
                        'sunset' => $instance->getSunsetDate(),
                        'replacedBy' => $instance->getReplacedBy(),
                    ];
                }
            }
        }

        return new VersionAttributeMetadata(
            array_values(array_unique($versions)),
            $deprecated,
        );
    }
}
