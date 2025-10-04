<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Router;
use ShahGhasiAdil\LaravelApiVersioning\Services\AttributeVersionResolver;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionManager;

class ApiVersionHealthCommand extends Command
{
    protected $signature = 'api:version:health';

    protected $description = 'Check API versioning configuration health';

    public function __construct(
        private readonly Router $router,
        private readonly VersionManager $versionManager,
        private readonly AttributeVersionResolver $resolver
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->components->info('Running API Versioning Health Check...');
        $this->newLine();

        $hasErrors = false;

        // Check 1: Supported versions configuration
        $supportedVersions = $this->versionManager->getSupportedVersions();
        if ($supportedVersions === []) {
            $this->components->error('No supported versions configured');
            $hasErrors = true;
        } else {
            $this->components->info('✓ Supported versions: '.implode(', ', $supportedVersions));
        }

        // Check 2: Default version
        $defaultVersion = $this->versionManager->getDefaultVersion();
        if (! in_array($defaultVersion, $supportedVersions, true)) {
            $this->components->error("✗ Default version '{$defaultVersion}' is not in supported versions");
            $hasErrors = true;
        } else {
            $this->components->info("✓ Default version: {$defaultVersion}");
        }

        // Check 3: Detection methods
        $detectionMethods = $this->versionManager->getDetectionMethods();
        $enabledMethods = array_filter($detectionMethods, function (array $config): bool {
            return (bool) ($config['enabled'] ?? false);
        });
        if ($enabledMethods === []) {
            $this->components->warn('⚠ No detection methods enabled');
        } else {
            $this->components->info('✓ Enabled detection methods: '.implode(', ', array_keys($enabledMethods)));
        }

        // Check 4: Routes with version attributes
        $allRoutes = $this->router->getRoutes();
        /** @var \Illuminate\Routing\Route[] $routeArray */
        $routeArray = iterator_to_array($allRoutes, false);
        $routes = collect($routeArray);
        $versionedRoutes = $routes->filter(function (\Illuminate\Routing\Route $route): bool {
            $versions = $this->resolver->getAllVersionsForRoute($route);

            return $versions !== [];
        });

        if ($versionedRoutes->isEmpty()) {
            $this->components->warn('⚠ No routes with version attributes found');
        } else {
            $this->components->info('✓ Found '.$versionedRoutes->count().' versioned routes');
        }

        // Check 5: Orphaned versions
        $usedVersions = [];
        foreach ($versionedRoutes as $route) {
            $versions = $this->resolver->getAllVersionsForRoute($route);
            $usedVersions = array_merge($usedVersions, $versions);
        }
        $usedVersions = array_unique($usedVersions);

        $orphanedVersions = array_diff($supportedVersions, $usedVersions);
        if ($orphanedVersions !== []) {
            $this->components->warn('⚠ Configured versions not used in any route: '.implode(', ', $orphanedVersions));
        }

        $unsupportedVersions = array_diff($usedVersions, $supportedVersions);
        if ($unsupportedVersions !== []) {
            $this->components->error('✗ Routes use unsupported versions: '.implode(', ', $unsupportedVersions));
            $hasErrors = true;
        }

        // Check 6: Cache configuration
        /** @var bool $cacheEnabled */
        $cacheEnabled = config('api-versioning.cache.enabled', true);
        if ($cacheEnabled) {
            $this->components->info('✓ Attribute caching enabled');
        } else {
            $this->components->warn('⚠ Attribute caching disabled (may impact performance)');
        }

        $this->newLine();

        if ($hasErrors) {
            $this->components->error('Health check failed with errors');

            return self::FAILURE;
        }

        $this->components->info('✅ All health checks passed!');

        return self::SUCCESS;
    }
}
