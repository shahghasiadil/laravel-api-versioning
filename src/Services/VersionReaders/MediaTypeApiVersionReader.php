<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Services\VersionReaders;

use Illuminate\Http\Request;

/**
 * Reads the API version from a media-type parameter (e.g.
 * `application/vnd.api+json;version=2.0`) on the `Accept` header, falling
 * back to `Content-Type` -- relevant on POST/PUT/PATCH, which don't send
 * `Accept` with a version.
 *
 * Each header is parsed as a genuine list of media-type entries: split on
 * `,` for the entries, then on `;` for each entry's parameters, with
 * quoted parameter values (`version="2.0"`) unquoted and q-values
 * honored -- entries are returned in descending q-value order.
 */
final class MediaTypeApiVersionReader implements ApiVersionReader
{
    /**
     * @param  string[]  $headers  Headers to check, in order.
     */
    public function __construct(
        private readonly string $parameter = 'version',
        private readonly array $headers = ['Accept', 'Content-Type'],
    ) {}

    public function read(Request $request): array
    {
        foreach ($this->headers as $headerName) {
            $header = $request->header($headerName);
            if (is_string($header) && $header !== '') {
                $values = $this->readFromHeader($header);
                if ($values !== []) {
                    return $values;
                }
            }
        }

        return [];
    }

    /**
     * @return string[]
     */
    private function readFromHeader(string $header): array
    {
        $entries = [];

        foreach (explode(',', $header) as $index => $rawEntry) {
            $segments = array_map('trim', explode(';', $rawEntry));
            array_shift($segments); // drop the media type itself (e.g. application/json)

            $q = 1.0;
            $params = [];

            foreach ($segments as $segment) {
                if ($segment === '' || ! str_contains($segment, '=')) {
                    continue;
                }

                [$key, $value] = array_map('trim', explode('=', $segment, 2));
                $value = trim($value, "\"'");
                $key = strtolower($key);

                if ($key === 'q') {
                    $q = is_numeric($value) ? (float) $value : 1.0;
                } else {
                    $params[$key] = $value;
                }
            }

            $entries[] = ['q' => $q, 'params' => $params, 'order' => $index];
        }

        usort($entries, function (array $a, array $b): int {
            $byQ = $b['q'] <=> $a['q'];

            return $byQ !== 0 ? $byQ : $a['order'] <=> $b['order'];
        });

        $values = [];
        foreach ($entries as $entry) {
            /** @var array<string, string> $params */
            $params = $entry['params'];
            if (isset($params[$this->parameter]) && $params[$this->parameter] !== '') {
                $values[] = $params[$this->parameter];
            }
        }

        return $values;
    }
}
