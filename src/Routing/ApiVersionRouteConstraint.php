<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Routing;

use ShahGhasiAdil\LaravelApiVersioning\Services\VersionReaders\UrlSegmentApiVersionReader;

/**
 * A route parameter constraint for a `{version}` URL segment, so one route
 * template can serve every version a controller declares instead of
 * duplicating the route per version:
 *
 * ```php
 * Route::pattern('version', ApiVersionRouteConstraint::PATTERN);
 *
 * Route::prefix('api/v{version}')->middleware('api.version')->group(function () {
 *     Route::apiResource('users', UserController::class);
 * });
 * ```
 *
 * This is registered automatically by ApiVersioningServiceProvider::boot(),
 * so calling it yourself is only necessary to override the pattern.
 */
final class ApiVersionRouteConstraint
{
    /**
     * Matches the same version shapes {@see UrlSegmentApiVersionReader}
     * extracts from a path: `1`, `1.0`, `2.1.0`, `1.0-beta`.
     */
    public const string PATTERN = '\d+(?:\.\d+)*(?:-[a-zA-Z0-9]+)?';

    /**
     * An exact-alternation pattern matching only the given versions, for
     * scoping a single route group to a specific version list (see
     * `Route::apiVersion([...])->group(...)`) rather than every
     * syntactically-valid version.
     *
     * @param  string[]  $versions
     */
    public static function exactPattern(array $versions): string
    {
        $escaped = array_map(static fn (string $version): string => preg_quote($version, '#'), $versions);

        return implode('|', $escaped);
    }
}
