<?php

namespace ShahGhasiAdil\LaravelApiVersioning\Services;

class VersionConfigService
{
    private array $config;

    public function __construct()
    {
        $this->config = config('api-versioning', []);
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
     */
    public function getInheritanceChain(string $version): array
    {
        $chain = [];
        $inheritance = $this->config['version_inheritance'] ?? [];
        $currentVersion = $version;

        while (isset($inheritance[$currentVersion])) {
            $parentVersion = $inheritance[$currentVersion];
            $chain[] = $parentVersion;
            $currentVersion = $parentVersion;
        }

        return $chain;
    }

    /**
     * Get all supported versions from config
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
     */
    public function getVersionMappings(): array
    {
        return $this->config['version_method_mapping'] ?? [];
    }
}
