<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Services\VersionSelectors;

use ShahGhasiAdil\LaravelApiVersioning\Services\VersionComparator;
use ShahGhasiAdil\LaravelApiVersioning\ValueObjects\ApiVersion;

/**
 * The lowest *non-prerelease* version the matched route implements. Falls
 * back to 'default_version' when the route implements nothing (or only
 * prereleases) or couldn't be determined.
 */
final class LowestImplementedApiVersionSelector implements ApiVersionSelector
{
    public function select(array $implementedVersions, string $defaultVersion): string
    {
        $stable = array_values(array_filter(
            $implementedVersions,
            fn (string $version): bool => ApiVersion::tryParse($version)?->isPrerelease() !== true
        ));

        if ($stable === []) {
            return $defaultVersion;
        }

        $sorted = (new VersionComparator)->sort($stable);

        return (string) $sorted[0];
    }
}
