<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Services\VersionReaders;

use Illuminate\Http\Request;

/**
 * A strategy for locating API version values in an incoming request.
 *
 * Unlike a simple "first match wins" extractor, a reader returns *every*
 * value it finds so callers (see {@see CombinedApiVersionReader} and
 * `VersionManager`) can detect disagreement between sources instead of
 * silently picking one.
 */
interface ApiVersionReader
{
    /**
     * @return string[] Every version value this reader found, in the order
     *                  it found them. Empty when nothing was found.
     */
    public function read(Request $request): array;
}
