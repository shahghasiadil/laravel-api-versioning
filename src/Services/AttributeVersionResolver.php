<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Services;

use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\AdvertiseApiVersions;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\ApiVersion;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\ApiVersionNeutral;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\Contracts\HasVersionDeprecation;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\Contracts\HasVersions;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\Deprecated;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\MapToApiVersion;
use ShahGhasiAdil\LaravelApiVersioning\Conventions\ConventionRegistry;
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
        private readonly AttributeCacheService $cache,
        private readonly ConventionRegistry $conventions = new ConventionRegistry,
    ) {}

    public function resolveVersionForRoute(Route $route, string $requestedVersion): ?VersionInfo
    {
        $resolved = $this->reflectControllerAction($route);

        if ($resolved === null) {
            return $this->resolveClosureRoute($route, $requestedVersion);
        }

        [$controllerClass, $controller, $action] = $resolved;
        $memoryKey = "{$controllerClass}@{$action}:{$requestedVersion}";

        if (array_key_exists($memoryKey, self::$memoryCache)) {
            return self::$memoryCache[$memoryKey];
        }

        $cacheKey = $this->cache->generateRouteKey($controllerClass, $action, $requestedVersion);

        /** @var VersionInfo|null $result */
        $result = $this->cache->remember($cacheKey, function () use ($controllerClass, $controller, $action, $requestedVersion) {
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

            $methodMetadata = $this->collectVersionMetadata($this->implementedVersionAttributes($reflectionMethod));

            if ($methodMetadata->versions !== [] && in_array($requestedVersion, $methodMetadata->versions, true)) {
                $advertised = $this->collectAdvertisedMetadata($reflectionMethod, $reflectionClass)->versions;

                return $this->createVersionInfo(
                    $requestedVersion,
                    false,
                    $reflectionMethod,
                    $reflectionClass,
                    routeVersions: array_values(array_unique([...$methodMetadata->versions, ...$advertised])),
                    perVersionDeprecation: $methodMetadata->deprecated,
                );
            }

            // Only look at class-level if method had no version attributes
            if ($methodMetadata->versions === []) {
                $classMetadata = $this->collectVersionMetadata($this->implementedVersionAttributes($reflectionClass));

                if ($classMetadata->versions !== [] && in_array($requestedVersion, $classMetadata->versions, true)) {
                    $advertised = $this->collectAdvertisedMetadata($reflectionMethod, $reflectionClass)->versions;

                    return $this->createVersionInfo(
                        $requestedVersion,
                        false,
                        $reflectionMethod,
                        $reflectionClass,
                        routeVersions: array_values(array_unique([...$classMetadata->versions, ...$advertised])),
                        perVersionDeprecation: $classMetadata->deprecated,
                    );
                }

                // No attributes on either method or class at all: fall back
                // to conventions registered via ApiVersioning::conventions().
                // Attributes always win when present, even partially (a
                // non-matching version on an attributed class/method is
                // still an attribute "claiming" that class/method) -- this
                // branch is only reached when there were none whatsoever.
                if ($classMetadata->versions === []) {
                    return $this->resolveFromConventions($controllerClass, $action, $requestedVersion);
                }
            }

            return null;
        });

        self::$memoryCache[$memoryKey] = $result;

        return $result;
    }

    /**
     * @param  class-string  $controllerClass
     */
    private function resolveFromConventions(string $controllerClass, string $action, string $requestedVersion): ?VersionInfo
    {
        if ($this->conventions->isControllerNeutral($controllerClass) || $this->conventions->isActionNeutral($controllerClass, $action)) {
            return $this->createVersionInfo(
                $requestedVersion,
                true,
                routeVersions: $this->versionManager->getSupportedVersions()
            );
        }

        $actionVersions = $this->conventions->getActionVersions($controllerClass, $action);
        $versions = $actionVersions !== [] ? $actionVersions : $this->conventions->getControllerVersions($controllerClass);
        $deprecations = $actionVersions !== []
            ? $this->conventions->getActionDeprecations($controllerClass, $action)
            : $this->conventions->getControllerDeprecations($controllerClass);

        if ($versions === [] || ! in_array($requestedVersion, $versions, true)) {
            return null;
        }

        return $this->createVersionInfo(
            $requestedVersion,
            false,
            routeVersions: $versions,
            perVersionDeprecation: $deprecations,
        );
    }

    /**
     * Closure routes have no class/method to carry attributes on, so
     * there's nothing to reflect. In order of precedence:
     *
     * 1. A route() convention matching this URI (see ConventionBuilder) --
     *    the only way to give a closure route real, non-neutral versions.
     * 2. 'closure_routes' config (default 'neutral', matching
     *    #[ApiVersionNeutral]; 'reject' rejects every version).
     */
    private function resolveClosureRoute(Route $route, string $requestedVersion): ?VersionInfo
    {
        if ($this->hasRouteConventions()) {
            $uri = $route->uri();

            if ($this->matchesNeutralRoutePattern($uri)) {
                return $this->createVersionInfo(
                    $requestedVersion,
                    true,
                    routeVersions: $this->versionManager->getSupportedVersions()
                );
            }

            $routeVersions = $this->matchingRouteConventionVersions($uri);
            if ($routeVersions !== null) {
                return in_array($requestedVersion, $routeVersions, true)
                    ? $this->createVersionInfo($requestedVersion, false, routeVersions: $routeVersions)
                    : null;
            }
        }

        return $this->closureRoutesAreNeutral()
            ? $this->createVersionInfo(
                $requestedVersion,
                true,
                routeVersions: $this->versionManager->getSupportedVersions()
            )
            : null;
    }

    private function matchesNeutralRoutePattern(string $uri): bool
    {
        foreach ($this->conventions->getNeutralRoutePatterns() as $pattern) {
            if (Str::is($pattern, $uri)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string[]|null
     */
    private function matchingRouteConventionVersions(string $uri): ?array
    {
        foreach ($this->conventions->getRouteVersions() as $pattern => $versions) {
            if (Str::is($pattern, $uri)) {
                return $versions;
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    public function getAllVersionsForRoute(Route $route): array
    {
        $resolved = $this->reflectControllerAction($route);

        if ($resolved === null) {
            return $this->closureRouteVersions($route);
        }

        [$controllerClass, $controller, $action] = $resolved;
        $cacheKey = $this->cache->generateRouteVersionsKey($controllerClass, $action);

        /** @var string[] $result */
        $result = $this->cache->remember($cacheKey, function () use ($controllerClass, $controller, $action) {
            $reflectionClass = new ReflectionClass($controller);
            $reflectionMethod = $reflectionClass->getMethod($action);

            if ($reflectionMethod->getAttributes(ApiVersionNeutral::class) !== [] ||
                $reflectionClass->getAttributes(ApiVersionNeutral::class) !== []) {
                return $this->versionManager->getSupportedVersions();
            }

            $implemented = $this->collectVersionMetadata($this->implementedVersionAttributes($reflectionMethod))->versions;

            if ($implemented === []) {
                $implemented = $this->collectVersionMetadata($this->implementedVersionAttributes($reflectionClass))->versions;
            }

            // #[AdvertiseApiVersions] declares versions implemented elsewhere;
            // they're never resolvable on this route, but still belong in
            // discovery data (headers, the api:versions command).
            $advertised = $this->collectAdvertisedMetadata($reflectionMethod, $reflectionClass)->versions;

            $versions = array_values(array_unique([...$implemented, ...$advertised]));

            return $versions !== [] ? $versions : $this->conventionVersions($controllerClass, $action);
        });

        return $result;
    }

    /**
     * @return string[]
     */
    private function closureRouteVersions(Route $route): array
    {
        if ($this->hasRouteConventions()) {
            $uri = $route->uri();

            if ($this->matchesNeutralRoutePattern($uri)) {
                return $this->versionManager->getSupportedVersions();
            }

            $routeVersions = $this->matchingRouteConventionVersions($uri);
            if ($routeVersions !== null) {
                return $routeVersions;
            }
        }

        return $this->closureRoutesAreNeutral() ? $this->versionManager->getSupportedVersions() : [];
    }

    private function hasRouteConventions(): bool
    {
        return $this->conventions->getNeutralRoutePatterns() !== [] || $this->conventions->getRouteVersions() !== [];
    }

    /**
     * @param  class-string  $controllerClass
     * @return string[]
     */
    private function conventionVersions(string $controllerClass, string $action): array
    {
        if ($this->conventions->isControllerNeutral($controllerClass) || $this->conventions->isActionNeutral($controllerClass, $action)) {
            return $this->versionManager->getSupportedVersions();
        }

        $actionVersions = $this->conventions->getActionVersions($controllerClass, $action);

        return $actionVersions !== [] ? $actionVersions : $this->conventions->getControllerVersions($controllerClass);
    }

    /**
     * The versions a route genuinely implements and can resolve a request
     * against -- unlike {@see getAllVersionsForRoute()}, this excludes
     * #[AdvertiseApiVersions] versions (which are discovery-only and never
     * resolvable here). Used by the 'current'/'lowest' version selectors
     * (see VersionSelectors), which must never pick a version the matched
     * route can't actually serve.
     *
     * @return string[]
     */
    public function getImplementedVersionsForRoute(Route $route): array
    {
        $resolved = $this->reflectControllerAction($route);

        if ($resolved === null) {
            return $this->closureRouteVersions($route);
        }

        [$controllerClass, $controller, $action] = $resolved;

        $reflectionClass = new ReflectionClass($controller);
        $reflectionMethod = $reflectionClass->getMethod($action);

        if ($reflectionMethod->getAttributes(ApiVersionNeutral::class) !== [] ||
            $reflectionClass->getAttributes(ApiVersionNeutral::class) !== []) {
            return $this->versionManager->getSupportedVersions();
        }

        $implemented = $this->collectVersionMetadata($this->implementedVersionAttributes($reflectionMethod))->versions;

        if ($implemented === []) {
            $implemented = $this->collectVersionMetadata($this->implementedVersionAttributes($reflectionClass))->versions;
        }

        return $implemented !== [] ? $implemented : $this->conventionVersions($controllerClass, $action);
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
        $resolved = $this->reflectControllerAction($route);

        if ($resolved === null) {
            return [];
        }

        [$controllerClass, $controller, $action] = $resolved;
        $cacheKey = $this->cache->generateRouteDeprecatedVersionsKey($controllerClass, $action);

        /** @var string[] $result */
        $result = $this->cache->remember($cacheKey, function () use ($controllerClass, $controller, $action) {
            $reflectionClass = new ReflectionClass($controller);
            $reflectionMethod = $reflectionClass->getMethod($action);

            if ($reflectionMethod->getAttributes(ApiVersionNeutral::class) !== [] ||
                $reflectionClass->getAttributes(ApiVersionNeutral::class) !== []) {
                return [];
            }

            $methodMetadata = $this->collectVersionMetadata($this->implementedVersionAttributes($reflectionMethod));

            $metadata = $methodMetadata->versions !== []
                ? $methodMetadata
                : $this->collectVersionMetadata($this->implementedVersionAttributes($reflectionClass));

            if ($metadata->versions === []) {
                // No attributes at all: fall back to convention-declared deprecations.
                $actionDeprecations = $this->conventions->getActionDeprecations($controllerClass, $action);
                $deprecations = $actionDeprecations !== []
                    ? $actionDeprecations
                    : $this->conventions->getControllerDeprecations($controllerClass);

                return array_keys($deprecations);
            }

            if ($metadata->deprecated !== []) {
                $deprecatedImplemented = array_keys($metadata->deprecated);
            } else {
                // No per-version deprecation declared: fall back to the coarse
                // #[Deprecated] attribute, which deprecates every version.
                $coarseDeprecated = $this->getDeprecationInfo($reflectionMethod) ?? $this->getDeprecationInfo($reflectionClass);
                $deprecatedImplemented = $coarseDeprecated !== null ? $metadata->versions : [];
            }

            $advertisedMetadata = $this->collectAdvertisedMetadata($reflectionMethod, $reflectionClass);
            $deprecatedAdvertised = array_keys($advertisedMetadata->deprecated);

            return array_values(array_unique([...$deprecatedImplemented, ...$deprecatedAdvertised]));
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
     * Narrow the route's controller from `mixed` (its genuine declared
     * return type in Illuminate\Routing\Route) to a real object, or null
     * for a closure route.
     *
     * @return array{0: class-string, 1: object, 2: string}|null Controller class name, controller instance, action method name.
     */
    private function reflectControllerAction(Route $route): ?array
    {
        $controller = $route->getController();

        if (! is_object($controller)) {
            return null;
        }

        return [get_class($controller), $controller, $route->getActionMethod()];
    }

    /**
     * Whether a route with no controller (a Closure route, or a Minimal-
     * API-style callable route) should be treated as version-neutral.
     */
    private function closureRoutesAreNeutral(): bool
    {
        /** @var mixed $mode */
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
     * @param  ReflectionClass<object>|null  $class
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

    /**
     * @param  ReflectionClass<object>|ReflectionMethod|null  $reflection
     */
    private function getDeprecationInfo(ReflectionClass|ReflectionMethod|null $reflection): ?Deprecated
    {
        if ($reflection === null) {
            return null;
        }

        $attributes = $reflection->getAttributes(Deprecated::class);

        return $attributes !== [] ? $attributes[0]->newInstance() : null;
    }

    /**
     * The #[ApiVersion]/#[MapToApiVersion] attributes on a reflector: the
     * two attribute types that declare versions this route *implements*.
     * Deliberately explicit (rather than filtering by the shared
     * HasVersions interface) so that #[AdvertiseApiVersions] -- which also
     * implements HasVersions, for typing purposes -- is never swept into
     * the "implemented" set.
     *
     * @param  ReflectionClass<object>|ReflectionMethod  $reflector
     * @return list<ReflectionAttribute<HasVersions>>
     */
    private function implementedVersionAttributes(ReflectionClass|ReflectionMethod $reflector): array
    {
        return [
            ...$reflector->getAttributes(ApiVersion::class),
            ...$reflector->getAttributes(MapToApiVersion::class),
        ];
    }

    /**
     * Collect #[AdvertiseApiVersions] metadata from both the method and the
     * class (unioned, not method-overrides-class like implemented versions
     * — advertised versions are supplementary discovery metadata, not
     * mutually exclusive alternatives to resolve a request against).
     *
     * @param  ReflectionClass<object>  $class
     */
    private function collectAdvertisedMetadata(ReflectionMethod $method, ReflectionClass $class): VersionAttributeMetadata
    {
        $methodMetadata = $this->collectVersionMetadata($method->getAttributes(AdvertiseApiVersions::class));
        $classMetadata = $this->collectVersionMetadata($class->getAttributes(AdvertiseApiVersions::class));

        return new VersionAttributeMetadata(
            array_values(array_unique([...$classMetadata->versions, ...$methodMetadata->versions])),
            array_merge($classMetadata->deprecated, $methodMetadata->deprecated),
        );
    }

    /**
     * Collect the merged, deduplicated version list from a set of HasVersions
     * attribute instances, along with a map of any versions individually
     * marked deprecated on the attribute that declared them.
     *
     * @param  list<ReflectionAttribute<HasVersions>>  $attributes
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
