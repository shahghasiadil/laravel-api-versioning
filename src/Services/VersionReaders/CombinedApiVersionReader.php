<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Services\VersionReaders;

use Illuminate\Http\Request;

/**
 * Aggregates a set of named readers, checked in the order given.
 *
 * {@see readByReader()} is the primary entry point for callers (like
 * `VersionManager`) that need to know *which* reader produced *which*
 * value -- e.g. to report exactly what disagreed when versions conflict.
 * {@see read()} (satisfying {@see ApiVersionReader}) simply flattens every
 * reader's first result, for callers that only want the raw values.
 */
final class CombinedApiVersionReader implements ApiVersionReader
{
    /**
     * @param  array<string, ApiVersionReader>  $readers  Keyed by a stable name (used for reporting).
     */
    public function __construct(
        private readonly array $readers,
    ) {}

    /**
     * @return array<string, string> First value found per reader name; readers that found nothing are omitted.
     */
    public function readByReader(Request $request): array
    {
        $found = [];

        foreach ($this->readers as $name => $reader) {
            $values = $reader->read($request);
            if ($values !== []) {
                $found[$name] = $values[0];
            }
        }

        return $found;
    }

    public function read(Request $request): array
    {
        return array_values($this->readByReader($request));
    }
}
