<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Services;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use ShahGhasiAdil\LaravelApiVersioning\Exceptions\UnsupportedVersionException;
use ShahGhasiAdil\LaravelApiVersioning\Exceptions\VersionProblemReason;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionReaders\ApiVersionReader;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionReaders\CombinedApiVersionReader;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionReaders\HeaderApiVersionReader;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionReaders\MediaTypeApiVersionReader;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionReaders\QueryStringApiVersionReader;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionReaders\UrlSegmentApiVersionReader;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionSelectors\ApiVersionSelector;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionSelectors\ConstantApiVersionSelector;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionSelectors\CurrentImplementationApiVersionSelector;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionSelectors\DefaultApiVersionSelector;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionSelectors\LowestImplementedApiVersionSelector;

class VersionManager
{
    /**
     * Supplies the versions the request's matched route genuinely implements
     * (see AttributeVersionResolver::getImplementedVersionsForRoute()), for
     * the 'current'/'lowest' selectors. Wired up in
     * ApiVersioningServiceProvider::boot() (after both singletons exist,
     * so this stays a plain closure rather than a constructor dependency
     * on AttributeVersionResolver, which would otherwise cycle back here).
     *
     * @var (Closure(Route): string[])|null
     */
    private ?Closure $routeVersionsProvider = null;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config
    ) {}

    /**
     * @param  Closure(Route): string[]  $provider
     */
    public function setRouteVersionsProvider(Closure $provider): void
    {
        $this->routeVersionsProvider = $provider;
    }

    public function detectVersionFromRequest(Request $request): string
    {
        $detected = $this->collectDetectedVersions($request);

        $version = $this->resolveDetectedVersion($detected);

        if ($version === null) {
            if ($this->requiresExplicitVersion()) {
                throw new UnsupportedVersionException(
                    message: 'No API version was specified and none could be assumed.',
                    supportedVersions: $this->getSupportedVersions(),
                    requestedVersion: null,
                    reason: VersionProblemReason::Unspecified,
                );
            }

            $version = $this->selectVersion($request);
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

    /**
     * Whether an unversioned request should be rejected (400
     * ApiVersionUnspecified) instead of having a version assumed for it.
     *
     * 'assume_default_when_unspecified' (default true) is the primary
     * toggle; the older 'version_detection.require_explicit_version' is
     * kept as an alias so existing config files keep working -- either one
     * being set to require an explicit version is enough to require one.
     */
    public function requiresExplicitVersion(): bool
    {
        $requireExplicit = (bool) ($this->versionDetectionConfig()['require_explicit_version'] ?? false);

        /** @var mixed $assumeDefault */
        $assumeDefault = $this->config['assume_default_when_unspecified'] ?? true;

        return $requireExplicit || $assumeDefault === false;
    }

    /**
     * Choose a version to assume for a request that specified none, per
     * the configured 'version_selector' ('default' | 'current' | 'lowest' |
     * 'constant'; defaults to 'default', this package's original
     * always-assume-the-configured-default behavior).
     */
    private function selectVersion(Request $request): string
    {
        $implementedVersions = [];

        if ($this->routeVersionsProvider !== null) {
            $route = $request->route();
            if ($route instanceof Route) {
                $implementedVersions = ($this->routeVersionsProvider)($route);
            }
        }

        return $this->buildSelector()->select($implementedVersions, $this->getDefaultVersion());
    }

    private function buildSelector(): ApiVersionSelector
    {
        /** @var mixed $nameRaw */
        $nameRaw = $this->config['version_selector'] ?? 'default';
        $name = is_string($nameRaw) ? $nameRaw : 'default';

        return match ($name) {
            'current' => new CurrentImplementationApiVersionSelector,
            'lowest' => new LowestImplementedApiVersionSelector,
            'constant' => new ConstantApiVersionSelector($this->constantSelectorVersion()),
            default => new DefaultApiVersionSelector,
        };
    }

    private function constantSelectorVersion(): string
    {
        /** @var mixed $constant */
        $constant = $this->config['version_selector_constant'] ?? null;

        return is_string($constant) && $constant !== '' ? $constant : $this->getDefaultVersion();
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
