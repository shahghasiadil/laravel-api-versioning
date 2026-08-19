<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Routing;

use Closure;
use Illuminate\Routing\Router;

/**
 * Returned by the `Route::apiVersion([...])` macro. Registers the routes
 * in `group()` under a `v{version}` prefix constrained to exactly the
 * given version list, with the versioning middleware applied -- so a
 * single route template serves every declared version instead of
 * duplicating the route per version.
 *
 * ```php
 * Route::apiVersion(['1.0', '2.0'])->group(function () {
 *     Route::apiResource('users', UserController::class);
 * });
 * ```
 */
final class ApiVersionRouteGroup
{
    /**
     * @param  string[]  $versions
     */
    public function __construct(
        private readonly Router $router,
        private readonly array $versions,
    ) {}

    /**
     * @param  Closure|string  $callback  A route-registering closure, or the path to a routes file.
     */
    public function group(Closure|string $callback): void
    {
        $this->router->group([
            'prefix' => 'v{version}',
            'where' => ['version' => ApiVersionRouteConstraint::exactPattern($this->versions)],
            'middleware' => 'api.version',
        ], $callback);
    }
}
