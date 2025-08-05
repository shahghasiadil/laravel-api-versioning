<?php

namespace ShahGhasiAdil\LaravelApiVersioning\Services;

use Illuminate\Http\Request;
use ShahGhasiAdil\LaravelApiVersioning\Exceptions\UnsupportedVersionException;

class VersionManager
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function detectVersionFromRequest(Request $request): string
    {
        $version = null;

        // Try each detection method in order
        foreach ($this->config['detection_methods'] as $method => $config) {
            if (!$config['enabled']) {
                continue;
            }

            $version = match($method) {
                'header' => $request->header($config['header_name']),
                'query' => $request->query($config['parameter_name']),
                'path' => $this->extractVersionFromPath($request, $config),
                'media_type' => $this->extractVersionFromMediaType($request, $config),
                default => null
            };

            if ($version) {
                break;
            }
        }

        // Fall back to default version
        $version = $version ?? $this->config['default_version'];

        if (!$this->isSupportedVersion($version)) {
            throw new UnsupportedVersionException("API version '{$version}' is not supported.");
        }

        return $version;
    }

    private function extractVersionFromPath(Request $request, array $config): ?string
    {
        $path = $request->path();
        $prefix = $config['prefix'];

        if (preg_match('#^' . preg_quote($prefix, '#') . '(\d+(?:\.\d+)?)/#', $path, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function extractVersionFromMediaType(Request $request, array $config): ?string
    {
        $accept = $request->header('Accept');
        if (!$accept) {
            return null;
        }

        $pattern = str_replace('%s', '(\d+(?:\.\d+)?)', preg_quote($config['format'], '#'));
        if (preg_match('#' . $pattern . '#', $accept, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function isSupportedVersion(string $version): bool
    {
        return in_array($version, $this->config['supported_versions']);
    }

    public function getSupportedVersions(): array
    {
        return $this->config['supported_versions'];
    }
}
