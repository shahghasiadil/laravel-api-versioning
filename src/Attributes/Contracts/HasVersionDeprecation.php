<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Attributes\Contracts;

/**
 * Implemented by version-declaring attributes ({@see HasVersions}) that can
 * mark the versions they declare as deprecated, independently of any other
 * version declared alongside them on the same class or method.
 */
interface HasVersionDeprecation
{
    /**
     * Whether the versions declared by this attribute instance are deprecated.
     */
    public function isDeprecated(): bool;

    /**
     * The sunset date for the versions declared by this attribute instance,
     * if one was given directly on the attribute.
     */
    public function getSunsetDate(): ?string;

    /**
     * The version that replaces the versions declared by this attribute
     * instance, if one was given directly on the attribute.
     */
    public function getReplacedBy(): ?string;
}
