<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Services;

use Illuminate\Http\Request;
use ShahGhasiAdil\LaravelApiVersioning\Exceptions\UnsupportedVersionException;

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
        /** @var string|null $version */
        $version = null;

        /** @var array<string, array<string, mixed>> $detectionMethods */
        $detectionMethods = $this->config['detection_methods'];

        // Try each detection method in order
        foreach ($detectionMethods as $method => $config) {
            $enabled = $config['enabled'] ?? false;
            if (! is_bool($enabled) || ! $enabled) {
                continue;
            }

            $version = match ($method) {
                'header' => $request->header((string) ($config['header_name'] ?? 'X-API-Version')),
                'query' => $request->query((string) ($config['parameter_name'] ?? 'api-version')),
                'path' => $this->extractVersionFromPath($request, $config),
                'media_type' => $this->extractVersionFromMediaType($request, $config),
                default => null
            };

            if (is_string($version) && $version !== '') {
                break;
            }
            $version = null;
        }

        // Fall back to default version
        /** @var string $defaultVersion */
        $defaultVersion = $this->config['default_version'];
        $version = $version ?? $defaultVersion;

        if (! $this->isSupportedVersion($version)) {
            throw new UnsupportedVersionException(
                message: "API version '{$version}' is not supported.",
                supportedVersions: $this->getSupportedVersions(),
                requestedVersion: $version
            );
        }

        return $version;
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
