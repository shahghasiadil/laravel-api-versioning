<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Services\VersionReaders;

use Illuminate\Http\Request;

/**
 * Reads the API version from a query string parameter (e.g. `api-version`).
 */
final class QueryStringApiVersionReader implements ApiVersionReader
{
    public function __construct(
        private readonly string $parameterName = 'api-version',
    ) {}

    public function read(Request $request): array
    {
        $value = $request->query($this->parameterName);

        return is_string($value) && $value !== '' ? [$value] : [];
    }
}
