<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Conventions;

use Illuminate\Support\Str;

/**
 * Fluent builder for conventions matched by URI pattern rather than
 * controller class, obtained via {@see ConventionBuilder::route()}.
 *
 * This is what lets closure routes (which have no class/method to carry
 * attributes on at all) opt into real versions or version-neutrality:
 * `$c->route('api/webhooks/*')->isApiVersionNeutral()`.
 *
 * $pattern is matched against the route's URI with
 * {@see Str::is()} (`*` wildcards), the same syntax
 * Laravel route groups and gates already use.
 */
final class RouteConvention
{
    public function __construct(
        private readonly ConventionRegistry $registry,
        private readonly string $pattern,
    ) {}

    public function isApiVersionNeutral(): self
    {
        $this->registry->markRouteNeutral($this->pattern);

        return $this;
    }

    /**
     * @param  string|string[]  $versions
     */
    public function hasApiVersion(string|array $versions): self
    {
        $this->registry->addRouteVersions($this->pattern, is_array($versions) ? $versions : [$versions]);

        return $this;
    }
}
