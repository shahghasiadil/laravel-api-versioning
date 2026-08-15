<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Services\VersionSelectors;

use ShahGhasiAdil\LaravelApiVersioning\Services\VersionComparator;
use ShahGhasiAdil\LaravelApiVersioning\ValueObjects\ApiVersion;

/**
 * The highest *non-prerelease* version the matched route implements, so an
 * unversioned client never lands on a beta. Falls back to
 * 'default_version' when the route implements nothing (or only
 * prereleases) or couldn't be determined.
 */
final class CurrentImplementationApiVersionSelector implements ApiVersionSelector
{
    public function select(array $implementedVersions, string $defaultVersion): string
    {
        $stable = $this->stableVersions($implementedVersions);

        if ($stable === []) {
            return $defaultVersion;
        }

        $sorted = (new VersionComparator)->sort($stable);

        return (string) end($sorted);
    }

    /**
     * @param  string[]  $versions
     * @return string[]
     */
    private function stableVersions(array $versions): array
    {
        return array_values(array_filter(
            $versions,
            fn (string $version): bool => ApiVersion::tryParse($version)?->isPrerelease() !== true
        ));
    }
}
