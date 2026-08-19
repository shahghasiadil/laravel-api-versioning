<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Exceptions;

/**
 * The reason an incoming request's API version could not be resolved.
 */
enum VersionProblemReason: string
{
    /** The version is well-formed but not supported (by the app, or by the matched endpoint). */
    case Unsupported = 'unsupported';

    /** No version was specified and 'require_explicit_version' forbids assuming a default. */
    case Unspecified = 'unspecified';

    /** The detected version value doesn't match the configured version format. */
    case Invalid = 'invalid';

    /** Multiple enabled detection methods disagreed on the requested version. */
    case Ambiguous = 'ambiguous';

    public function title(): string
    {
        return match ($this) {
            self::Unsupported => 'Unsupported API Version',
            self::Unspecified => 'Unspecified API Version',
            self::Invalid => 'Invalid API Version',
            self::Ambiguous => 'Ambiguous API Version',
        };
    }

    /**
     * A stable, machine-readable error code, independent of the human-readable title.
     */
    public function code(): string
    {
        return match ($this) {
            self::Unsupported => 'UnsupportedApiVersion',
            self::Unspecified => 'ApiVersionUnspecified',
            self::Invalid => 'InvalidApiVersion',
            self::Ambiguous => 'AmbiguousApiVersion',
        };
    }
}
