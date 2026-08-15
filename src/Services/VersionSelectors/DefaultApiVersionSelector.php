<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Services\VersionSelectors;

/**
 * Always the configured 'default_version'. This package's original,
 * always-on behavior.
 */
final class DefaultApiVersionSelector implements ApiVersionSelector
{
    public function select(array $implementedVersions, string $defaultVersion): string
    {
        return $defaultVersion;
    }
}
