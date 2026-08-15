<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Services\VersionSelectors;

/**
 * A strategy for choosing which API version to assume when a request
 * specifies none and 'assume_default_when_unspecified' allows assuming one
 * at all.
 */
interface ApiVersionSelector
{
    /**
     * @param  string[]  $implementedVersions  The versions the matched route
     *         implements (per AttributeVersionResolver::getAllVersionsForRoute()),
     *         or an empty array when there is no matched route / it couldn't
     *         be determined.
     * @param  string  $defaultVersion  The configured 'default_version'.
     */
    public function select(array $implementedVersions, string $defaultVersion): string;
}
