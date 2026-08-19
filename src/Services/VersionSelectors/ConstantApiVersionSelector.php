<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Services\VersionSelectors;

/**
 * Always a fixed version, ignoring both the matched route and
 * 'default_version'.
 */
final class ConstantApiVersionSelector implements ApiVersionSelector
{
    public function __construct(
        private readonly string $version,
    ) {}

    public function select(array $implementedVersions, string $defaultVersion): string
    {
        return $this->version;
    }
}
