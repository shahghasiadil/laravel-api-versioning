<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Services;

class VersionConfigService
{
    /**
     * @var array<string, mixed>
     */
    private readonly array $config;

    public function __construct()
    {
        /** @var array<string, mixed> $config */
        $config = config('api-versioning', []);
        $this->config = is_array($config) ? $config : [];
    }

    /**
     * Get the method name for a specific version
     */
    public function getMethodForVersion(string $version): string
    {
        /** @var array<string, string> $versionMapping */
        $versionMapping = $this->config['version_method_mapping'] ?? [];

        if (isset($versionMapping[$version])) {
            return $versionMapping[$version];
        }

        /** @var string $defaultMethod */
        $defaultMethod = $this->config['default_method'] ?? 'toArrayDefault';

        return $defaultMethod;
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
        /** @var array<string, string> $inheritance */
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
        /** @var string[] $supportedVersions */
        $supportedVersions = $this->config['supported_versions'] ?? [];

        return $supportedVersions;
    }

    /**
     * Check if version has a specific method mapping
     */
    public function hasVersionMapping(string $version): bool
    {
        /** @var array<string, string> $versionMapping */
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
        /** @var array<string, string> $versionMapping */
        $versionMapping = $this->config['version_method_mapping'] ?? [];

        return $versionMapping;
    }

    /**
     * Get version inheritance mappings
     *
     * @return array<string, string>
     */
    public function getVersionInheritance(): array
    {
        /** @var array<string, string> $inheritance */
        $inheritance = $this->config['version_inheritance'] ?? [];

        return $inheritance;
    }

    public function getDefaultMethod(): string
    {
        /** @var string $defaultMethod */
        $defaultMethod = $this->config['default_method'] ?? 'toArrayDefault';

        return $defaultMethod;
    }
}
