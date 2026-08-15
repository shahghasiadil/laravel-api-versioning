<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Services;

/**
 * @internal Result of merging a set of HasVersions attribute instances found
 *           on a single class or method reflector.
 */
final class VersionAttributeMetadata
{
    /**
     * @param  string[]  $versions  Deduplicated, merged version list.
     * @param  array<string, array{sunset: string|null, replacedBy: string|null}>  $deprecated
     *         Versions explicitly marked deprecated on the attribute that declared them.
     */
    public function __construct(
        public readonly array $versions,
        public readonly array $deprecated,
    ) {}
}
