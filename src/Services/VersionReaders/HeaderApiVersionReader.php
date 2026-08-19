<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Services\VersionReaders;

use Illuminate\Http\Request;

/**
 * Reads the API version from a request header (e.g. `X-API-Version`).
 */
final class HeaderApiVersionReader implements ApiVersionReader
{
    public function __construct(
        private readonly string $headerName = 'X-API-Version',
    ) {}

    public function read(Request $request): array
    {
        $value = $request->header($this->headerName);

        return is_string($value) && $value !== '' ? [$value] : [];
    }
}
