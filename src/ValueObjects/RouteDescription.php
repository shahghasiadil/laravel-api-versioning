<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\ValueObjects;

/**
 * A single route that serves a given API version, as reported by
 * {@see \ShahGhasiAdil\LaravelApiVersioning\OpenApi\ApiVersionDescriptionProvider}.
 */
final class RouteDescription
{
    /**
     * @param  string[]  $methods
     */
    public function __construct(
        public readonly array $methods,
        public readonly string $uri,
        public readonly string $action,
    ) {}

    /**
     * @return array{methods: string[], uri: string, action: string}
     */
    public function toArray(): array
    {
        return [
            'methods' => $this->methods,
            'uri' => $this->uri,
            'action' => $this->action,
        ];
    }
}
