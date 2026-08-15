<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Services;

use Illuminate\Http\Request;
use ShahGhasiAdil\LaravelApiVersioning\Exceptions\UnsupportedVersionException;
use ShahGhasiAdil\LaravelApiVersioning\Exceptions\VersionProblemReason;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionReaders\ApiVersionReader;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionReaders\CombinedApiVersionReader;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionReaders\HeaderApiVersionReader;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionReaders\MediaTypeApiVersionReader;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionReaders\QueryStringApiVersionReader;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionReaders\UrlSegmentApiVersionReader;

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
     * in config order. Only non-empty values are included.
     *
     * Delegates the actual extraction to {@see ApiVersionReader} strategy
     * objects via {@see CombinedApiVersionReader}, built either from the
     * 'readers' config (a list of reader class => constructor-options) or,
     * when that's absent, as a shim over the legacy 'detection_methods'
     * config -- so both forms keep working.
     *
     * @return array<string, string>
     */
    private function collectDetectedVersions(Request $request): array
    {
        return (new CombinedApiVersionReader($this->buildReaders()))->readByReader($request);
    }

    /**
     * @return array<string, ApiVersionReader>
     */
    private function buildReaders(): array
    {
        /** @var mixed $readersConfig */
        $readersConfig = $this->config['readers'] ?? null;

        if (is_array($readersConfig) && $readersConfig !== []) {
            $readers = [];

            foreach ($readersConfig as $readerClass => $options) {
                if (! is_string($readerClass) || ! is_subclass_of($readerClass, ApiVersionReader::class)) {
                    continue;
                }

                /** @var array<string, mixed> $options */
                $options = is_array($options) ? $options : [];
                $name = class_basename($readerClass);
                /** @var ApiVersionReader $reader */
                $reader = new $readerClass(...$options);
                $readers[$name] = $reader;
            }

            return $readers;
        }

        return $this->buildReadersFromDetectionMethods();
    }

    /**
     * Shim: build the same reader set the legacy 'detection_methods' config
     * described, so existing config files keep working unmodified.
     *
     * @return array<string, ApiVersionReader>
     */
    private function buildReadersFromDetectionMethods(): array
    {
        /** @var array<string, array<string, mixed>> $detectionMethods */
        $detectionMethods = $this->config['detection_methods'];
        $readers = [];

        foreach ($detectionMethods as $method => $config) {
            $enabled = $config['enabled'] ?? false;
            if (! is_bool($enabled) || ! $enabled) {
                continue;
            }

            $reader = match ($method) {
                'header' => new HeaderApiVersionReader($this->stringOption($config, 'header_name', 'X-API-Version')),
                'query' => new QueryStringApiVersionReader($this->stringOption($config, 'parameter_name', 'api-version')),
                'path' => new UrlSegmentApiVersionReader($this->stringOption($config, 'prefix', 'api/v')),
                'media_type' => new MediaTypeApiVersionReader($this->mediaTypeParameterName($config)),
                default => null,
            };

            if ($reader !== null) {
                $readers[$method] = $reader;
            }
        }

        return $readers;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function stringOption(array $config, string $key, string $default): string
    {
        /** @var mixed $value */
        $value = $config[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : $default;
    }

    /**
     * The media-type parameter name to look for, either explicit
     * ('parameter') or derived from the legacy 'format' sprintf pattern
     * (e.g. 'version=%s' => 'version').
     *
     * @param  array<string, mixed>  $config
     */
    private function mediaTypeParameterName(array $config): string
    {
        /** @var mixed $parameterRaw */
        $parameterRaw = $config['parameter'] ?? null;
        if (is_string($parameterRaw) && $parameterRaw !== '') {
            return $parameterRaw;
        }

        /** @var mixed $formatRaw */
        $formatRaw = $config['format'] ?? 'application/vnd.api+json;version=%s';
        $format = is_string($formatRaw) ? $formatRaw : 'application/vnd.api+json;version=%s';

        return preg_match('/([a-zA-Z0-9_-]+)=%s/', $format, $matches) === 1 ? $matches[1] : 'version';
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
