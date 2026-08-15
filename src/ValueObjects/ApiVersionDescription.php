<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\ValueObjects;

/**
 * Everything a documentation generator (or `api:versions --json`) needs to
 * know about a single API version: whether it's deprecated, when it
 * sunsets, and which routes serve it. Produced by
 * {@see \ShahGhasiAdil\LaravelApiVersioning\OpenApi\ApiVersionDescriptionProvider}.
 */
final class ApiVersionDescription
{
    /**
     * @param  RouteDescription[]  $routes
     */
    public function __construct(
        public readonly string $version,
        public readonly bool $isDeprecated,
        public readonly ?SunsetPolicy $sunsetPolicy,
        public readonly array $routes,
    ) {}

    /**
     * @return array{version: string, is_deprecated: bool, sunset_date: string|null, routes: list<array{methods: string[], uri: string, action: string}>}
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'is_deprecated' => $this->isDeprecated,
            'sunset_date' => $this->sunsetPolicy?->formattedDate(),
            'routes' => array_values(array_map(fn (RouteDescription $route): array => $route->toArray(), $this->routes)),
        ];
    }
}
