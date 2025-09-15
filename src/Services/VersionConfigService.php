<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Services;

class VersionConfigService
{
    private readonly array $config;

    public function __construct()
    {
        $config = config('api-versioning', []);
        $this->config = is_array($config) ? $config : [];
    }

    /**
     * Get the method name for a specific version
     */
    public function getMethodForVersion(string $version): string
    {
        $versionMapping = $this->config['version_method_mapping'] ?? [];

        if (isset($versionMapping[$version])) {
            return $versionMapping[$version];
        }

        return $this->config['default_method'] ?? 'toArrayDefault';
    }

    /**
     * Get inheritance chain for a version
     *
     * @return string[]
     */
    public function getInheritanceChain(string $version): array
    {
        $chain = [];
        $visited = [];
        $inheritance = $this->config['version_inheritance'] ?? [];
        $currentVersion = $version;

        while (isset($inheritance[$currentVersion])) {
            // Prevent infinite loops by checking if we've seen this version before
            if (in_array($currentVersion, $visited, true)) {
                break;
            }

            $visited[] = $currentVersion;
            $parentVersion = $inheritance[$currentVersion];

            // Also check if the parent would create a cycle
            if (in_array($parentVersion, $visited, true)) {
                break;
            }

            $chain[] = $parentVersion;
            $currentVersion = $parentVersion;
        }

        return $chain;
    }

    /**
     * Get all supported versions from config
     *
     * @return string[]
     */
    public function getSupportedVersions(): array
    {
        return $this->config['supported_versions'] ?? [];
    }

    /**
     * Check if version has a specific method mapping
     */
    public function hasVersionMapping(string $version): bool
    {
        $versionMapping = $this->config['version_method_mapping'] ?? [];

        return isset($versionMapping[$version]);
    }

    /**
     * Get version mappings for debugging
     *
     * @return array<string, string>
     */
    public function getVersionMappings(): array
    {
        return $this->config['version_method_mapping'] ?? [];
    }

    /**
     * Get version inheritance mappings
     *
     * @return array<string, string>
     */
    public function getVersionInheritance(): array
    {
        return $this->config['version_inheritance'] ?? [];
    }

    public function getDefaultMethod(): string
    {
        return $this->config['default_method'] ?? 'toArrayDefault';
    }
}
