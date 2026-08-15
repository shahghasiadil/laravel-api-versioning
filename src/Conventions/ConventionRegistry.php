<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Conventions;

/**
 * Storage for conventions registered via {@see \ShahGhasiAdil\LaravelApiVersioning\ApiVersioning::conventions()}.
 *
 * A single mutable, container-bound singleton: {@see ConventionBuilder} and
 * its child builders ({@see ControllerConvention}, {@see ActionConvention},
 * {@see RouteConvention}) write into it; {@see \ShahGhasiAdil\LaravelApiVersioning\Services\AttributeVersionResolver}
 * reads from it as a fallback for classes/methods/routes that carry no
 * version attributes of their own -- attributes always win when present.
 */
final class ConventionRegistry
{
    /** @var array<string, bool> */
    private array $neutralControllers = [];

    /** @var array<string, string[]> */
    private array $controllerVersions = [];

    /** @var array<string, array<string, array{sunset: string|null, replacedBy: string|null}>> */
    private array $controllerDeprecated = [];

    /** @var array<string, array<string, bool>> */
    private array $neutralActions = [];

    /** @var array<string, array<string, string[]>> */
    private array $actionVersions = [];

    /** @var array<string, array<string, array<string, array{sunset: string|null, replacedBy: string|null}>>> */
    private array $actionDeprecated = [];

    /** @var string[] */
    private array $neutralRoutePatterns = [];

    /** @var array<string, string[]> Pattern => versions, for closure routes given real versions via route() conventions. */
    private array $routeVersions = [];

    public function markControllerNeutral(string $class): void
    {
        $this->neutralControllers[$class] = true;
    }

    public function isControllerNeutral(string $class): bool
    {
        return $this->neutralControllers[$class] ?? false;
    }

    /**
     * @param  string[]  $versions
     */
    public function addControllerVersions(string $class, array $versions): void
    {
        $this->controllerVersions[$class] = array_values(array_unique([
            ...($this->controllerVersions[$class] ?? []),
            ...$versions,
        ]));
    }

    /**
     * @return string[]
     */
    public function getControllerVersions(string $class): array
    {
        return $this->controllerVersions[$class] ?? [];
    }

    /**
     * @param  string[]  $versions
     */
    public function markControllerVersionsDeprecated(string $class, array $versions, ?string $sunset, ?string $replacedBy): void
    {
        foreach ($versions as $version) {
            $this->controllerDeprecated[$class][$version] = ['sunset' => $sunset, 'replacedBy' => $replacedBy];
        }
    }

    /**
     * @return array<string, array{sunset: string|null, replacedBy: string|null}>
     */
    public function getControllerDeprecations(string $class): array
    {
        return $this->controllerDeprecated[$class] ?? [];
    }

    public function markActionNeutral(string $class, string $method): void
    {
        $this->neutralActions[$class][$method] = true;
    }

    public function isActionNeutral(string $class, string $method): bool
    {
        return $this->neutralActions[$class][$method] ?? false;
    }

    public function addActionVersion(string $class, string $method, string $version): void
    {
        $this->actionVersions[$class][$method] = array_values(array_unique([
            ...($this->actionVersions[$class][$method] ?? []),
            $version,
        ]));
    }

    /**
     * @return string[]
     */
    public function getActionVersions(string $class, string $method): array
    {
        return $this->actionVersions[$class][$method] ?? [];
    }

    public function markActionVersionDeprecated(string $class, string $method, string $version, ?string $sunset, ?string $replacedBy): void
    {
        $this->actionDeprecated[$class][$method][$version] = ['sunset' => $sunset, 'replacedBy' => $replacedBy];
    }

    /**
     * @return array<string, array{sunset: string|null, replacedBy: string|null}>
     */
    public function getActionDeprecations(string $class, string $method): array
    {
        return $this->actionDeprecated[$class][$method] ?? [];
    }

    public function markRouteNeutral(string $pattern): void
    {
        $this->neutralRoutePatterns[] = $pattern;
    }

    /**
     * @return string[]
     */
    public function getNeutralRoutePatterns(): array
    {
        return $this->neutralRoutePatterns;
    }

    /**
     * @param  string[]  $versions
     */
    public function addRouteVersions(string $pattern, array $versions): void
    {
        $this->routeVersions[$pattern] = array_values(array_unique([
            ...($this->routeVersions[$pattern] ?? []),
            ...$versions,
        ]));
    }

    /**
     * @return array<string, string[]>
     */
    public function getRouteVersions(): array
    {
        return $this->routeVersions;
    }

    /**
     * Reset all registered conventions (useful in tests).
     */
    public function flush(): void
    {
        $this->neutralControllers = [];
        $this->controllerVersions = [];
        $this->controllerDeprecated = [];
        $this->neutralActions = [];
        $this->actionVersions = [];
        $this->actionDeprecated = [];
        $this->neutralRoutePatterns = [];
        $this->routeVersions = [];
    }
}
