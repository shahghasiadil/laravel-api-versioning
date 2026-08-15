<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Conventions;

/**
 * Entry point passed to the callback given to
 * {@see \ShahGhasiAdil\LaravelApiVersioning\ApiVersioning::conventions()}.
 */
final class ConventionBuilder
{
    public function __construct(
        private readonly ConventionRegistry $registry,
    ) {}

    /**
     * @param  class-string  $class
     */
    public function controller(string $class): ControllerConvention
    {
        return new ControllerConvention($this->registry, $class);
    }

    /**
     * @param  string  $pattern  A URI pattern (`*` wildcards), matched with Str::is().
     */
    public function route(string $pattern): RouteConvention
    {
        return new RouteConvention($this->registry, $pattern);
    }
}
