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
            /** @var bool $requireExplicit */
            $requireExplicit = (bool) ($this->config['version_detection']['require_explicit_version'] ?? false);

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

            $value = match ($method) {
                'header' => $request->header((string) ($config['header_name'] ?? 'X-API-Version')),
                'query' => $request->query((string) ($config['parameter_name'] ?? 'api-version')),
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

        /** @var bool $rejectConflicts */
        $rejectConflicts = (bool) ($this->config['version_detection']['reject_conflicting_versions'] ?? false);

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
     * @param  array<string, mixed>  $config
     */
    private function extractVersionFromMediaType(Request $request, array $config): ?string
    {
        $accept = $request->header('Accept');
        if (! is_string($accept) || $accept === '') {
            return null;
        }

        /** @var string $format */
        $format = $config['format'] ?? 'application/vnd.api+json;version=%s';
        $pattern = str_replace('%s', '(\d+(?:\.\d+)?)', preg_quote($format, '#'));
        if (preg_match('#'.$pattern.'#', $accept, $matches) === 1) {
            return $matches[1];
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
        /** @var array<string, mixed> $formatValidation */
        $formatValidation = $this->config['version_detection']['format_validation'] ?? [];

        if (! (bool) ($formatValidation['enabled'] ?? false)) {
            return true;
        }

        /** @var string $pattern */
        $pattern = $formatValidation['pattern'] ?? '/^\d+(?:\.\d+)*(?:-[a-zA-Z0-9]+)?$/';

        return preg_match($pattern, $version) === 1;
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
