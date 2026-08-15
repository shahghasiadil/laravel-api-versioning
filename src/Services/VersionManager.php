<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Services;

use Illuminate\Http\Request;
use ShahGhasiAdil\LaravelApiVersioning\Exceptions\UnsupportedVersionException;
use ShahGhasiAdil\LaravelApiVersioning\Exceptions\VersionProblemReason;

class VersionManager
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config
    ) {}

    public function detectVersionFromRequest(Request $request): string
    {
        $detected = $this->collectDetectedVersions($request);

        $version = $this->resolveDetectedVersion($detected);

        if ($version === null) {
            $requireExplicit = (bool) ($this->versionDetectionConfig()['require_explicit_version'] ?? false);

            if ($requireExplicit) {
                throw new UnsupportedVersionException(
                    message: 'No API version was specified and none could be assumed.',
                    supportedVersions: $this->getSupportedVersions(),
                    requestedVersion: null,
                    reason: VersionProblemReason::Unspecified,
                );
            }

            /** @var string $version */
            $version = $this->config['default_version'];
        }

        if (! $this->isValidVersionFormat($version)) {
            throw new UnsupportedVersionException(
                message: "API version '{$version}' is not a validly formatted version.",
                supportedVersions: $this->getSupportedVersions(),
                requestedVersion: $version,
                reason: VersionProblemReason::Invalid,
            );
        }

        if (! $this->isSupportedVersion($version)) {
            throw new UnsupportedVersionException(
                message: "API version '{$version}' is not supported.",
                supportedVersions: $this->getSupportedVersions(),
                requestedVersion: $version,
            );
        }

        return $version;
    }

    /**
     * Collect the version value produced by each enabled detection method,
     * in 'detection_methods' config order. Only non-empty values are included.
     *
     * @return array<string, string>
     */
    private function collectDetectedVersions(Request $request): array
    {
        /** @var array<string, array<string, mixed>> $detectionMethods */
        $detectionMethods = $this->config['detection_methods'];
        $detected = [];

        foreach ($detectionMethods as $method => $config) {
            $enabled = $config['enabled'] ?? false;
            if (! is_bool($enabled) || ! $enabled) {
                continue;
            }

            /** @var mixed $headerName */
            $headerName = $config['header_name'] ?? 'X-API-Version';
            /** @var mixed $parameterName */
            $parameterName = $config['parameter_name'] ?? 'api-version';

            $value = match ($method) {
                'header' => $request->header(is_string($headerName) ? $headerName : 'X-API-Version'),
                'query' => $request->query(is_string($parameterName) ? $parameterName : 'api-version'),
                'path' => $this->extractVersionFromPath($request, $config),
                'media_type' => $this->extractVersionFromMediaType($request, $config),
                default => null
            };

            if (is_string($value) && $value !== '') {
                $detected[$method] = $value;
            }
        }

        return $detected;
    }

    /**
     * Resolve a single version value from the per-method detection results.
     *
     * When every method agrees (or only one produced a value), that value is
     * used. When methods disagree, behavior depends on
     * 'version_detection.reject_conflicting_versions': by default (false)
     * the first method's value wins, in 'detection_methods' config order —
     * this package's original behavior. When enabled, disagreement throws
     * an Ambiguous version problem instead of silently picking one.
     *
     * @param  array<string, string>  $detected
     */
    private function resolveDetectedVersion(array $detected): ?string
    {
        if ($detected === []) {
            return null;
        }

        $distinctValues = array_values(array_unique($detected));

        if (count($distinctValues) === 1) {
            return $distinctValues[0];
        }

        $rejectConflicts = (bool) ($this->versionDetectionConfig()['reject_conflicting_versions'] ?? false);

        if ($rejectConflicts) {
            throw new UnsupportedVersionException(
                message: 'Multiple, conflicting API versions were specified in the same request.',
                supportedVersions: $this->getSupportedVersions(),
                requestedVersion: null,
                reason: VersionProblemReason::Ambiguous,
                context: ['conflicts' => $detected],
            );
        }

        /** @var string $first */
        $first = reset($detected);

        return $first;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function extractVersionFromPath(Request $request, array $config): ?string
    {
        $path = $request->path();
        /** @var string $prefix */
        $prefix = $config['prefix'] ?? 'api/v';

        // Handle various path patterns:
        // - api/v1.0/users
        // - api/v2.1.0/users
        // - api/v2/users
        // - v1.0/users (without api prefix)
        $pattern = '#^'.preg_quote($prefix, '#').'(\d+(?:\.\d+)*(?:-[a-zA-Z0-9]+)?)(?:/|$)#';

        if (preg_match($pattern, $path, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extract a version from the media-type parameter (e.g.
     * `application/vnd.api+json;version=2.0`) on the `Accept` header, and,
     * if not found there, the `Content-Type` header — relevant on
     * POST/PUT/PATCH requests, which don't send `Accept` with a version.
     *
     * Each header is parsed as a genuine list of media-type entries: split
     * on `,` for the entries, then on `;` for each entry's parameters,
     * with quoted parameter values (`version="2.0"`) unquoted and q-values
     * honored — entries are checked in descending q-value order, so
     * `application/json;version=1.0;q=0.9, application/json;version=2.0`
     * (implicit q=1.0) resolves to `2.0`.
     *
     * @param  array<string, mixed>  $config
     */
    private function extractVersionFromMediaType(Request $request, array $config): ?string
    {
        /** @var mixed $parameterRaw */
        $parameterRaw = $config['parameter'] ?? null;
        $parameter = is_string($parameterRaw) && $parameterRaw !== '' ? $parameterRaw : null;

        if ($parameter === null) {
            /** @var mixed $formatRaw */
            $formatRaw = $config['format'] ?? 'application/vnd.api+json;version=%s';
            $format = is_string($formatRaw) ? $formatRaw : 'application/vnd.api+json;version=%s';
            $parameter = preg_match('/([a-zA-Z0-9_-]+)=%s/', $format, $matches) === 1 ? $matches[1] : 'version';
        }

        foreach (['Accept', 'Content-Type'] as $headerName) {
            $header = $request->header($headerName);
            if (is_string($header) && $header !== '') {
                $version = $this->readVersionFromMediaTypeHeader($header, $parameter);
                if ($version !== null) {
                    return $version;
                }
            }
        }

        return null;
    }

    /**
     * Parse a raw `Accept`/`Content-Type` header value into its media-type
     * entries and return the first named parameter's value, preferring
     * higher q-values and otherwise the order the entries appear in.
     */
    private function readVersionFromMediaTypeHeader(string $header, string $parameter): ?string
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

        usort($entries, fn (array $a, array $b): int => $b['q'] <=> $a['q'] ?: $a['order'] <=> $b['order']);

        foreach ($entries as $entry) {
            /** @var array<string, string> $params */
            $params = $entry['params'];
            if (isset($params[$parameter]) && $params[$parameter] !== '') {
                return $params[$parameter];
            }
        }

        return null;
    }

    /**
     * Whether a detected version value matches the configured format.
     * Disabled by default: any non-empty value is considered validly
     * formatted, and simply checked against supported_versions as before.
     */
    private function isValidVersionFormat(string $version): bool
    {
        /** @var mixed $formatValidationRaw */
        $formatValidationRaw = $this->versionDetectionConfig()['format_validation'] ?? [];
        $formatValidation = is_array($formatValidationRaw) ? $formatValidationRaw : [];

        if (! (bool) ($formatValidation['enabled'] ?? false)) {
            return true;
        }

        /** @var mixed $patternRaw */
        $patternRaw = $formatValidation['pattern'] ?? null;
        $pattern = is_string($patternRaw) ? $patternRaw : '/^\d+(?:\.\d+)*(?:-[a-zA-Z0-9]+)?$/';

        return preg_match($pattern, $version) === 1;
    }

    /**
     * @return array<int|string, mixed>
     */
    private function versionDetectionConfig(): array
    {
        /** @var mixed $versionDetection */
        $versionDetection = $this->config['version_detection'] ?? [];

        return is_array($versionDetection) ? $versionDetection : [];
    }

    public function isSupportedVersion(string $version): bool
    {
        /** @var string[] $supportedVersions */
        $supportedVersions = $this->config['supported_versions'];

        return in_array($version, $supportedVersions, true);
    }

    /**
     * @return string[]
     */
    public function getSupportedVersions(): array
    {
        /** @var string[] $supportedVersions */
        $supportedVersions = $this->config['supported_versions'];

        return $supportedVersions;
    }

    public function getDefaultVersion(): string
    {
        /** @var string $defaultVersion */
        $defaultVersion = $this->config['default_version'];

        return $defaultVersion;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getDetectionMethods(): array
    {
        /** @var array<string, array<string, mixed>> $detectionMethods */
        $detectionMethods = $this->config['detection_methods'];

        return $detectionMethods;
    }
}
