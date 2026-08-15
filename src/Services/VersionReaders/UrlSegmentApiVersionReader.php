<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Services\VersionReaders;

use Illuminate\Http\Request;

/**
 * Reads the API version from a path segment (e.g. `api/v2.1/users` -> `2.1`).
 */
final class UrlSegmentApiVersionReader implements ApiVersionReader
{
    public function __construct(
        private readonly string $prefix = 'api/v',
    ) {}

    public function read(Request $request): array
    {
        $path = $request->path();

        // Handle various path patterns:
        // - api/v1.0/users
        // - api/v2.1.0/users
        // - api/v2/users
        // - v1.0/users (without api prefix)
        $pattern = '#^'.preg_quote($this->prefix, '#').'(\d+(?:\.\d+)*(?:-[a-zA-Z0-9]+)?)(?:/|$)#';

        return preg_match($pattern, $path, $matches) === 1 ? [$matches[1]] : [];
    }
}
