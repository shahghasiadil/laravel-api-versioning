<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use ShahGhasiAdil\LaravelApiVersioning\Services\AttributeVersionResolver;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionConfigService;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionManager;

class ApiVersionHealthCommand extends Command
{
    protected $signature = 'api:version:health
                           {--strict : Treat warnings as failures (non-zero exit code)}';

    protected $description = 'Check API versioning configuration health';

    private bool $hasWarnings = false;

    public function __construct(
        private readonly Router $router,
        private readonly VersionManager $versionManager,
        private readonly AttributeVersionResolver $resolver,
        private readonly VersionConfigService $configService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->components->info('Running API Versioning Health Check...');
        $this->newLine();

        $strict = (bool) $this->option('strict');
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
            $this->recordWarning('⚠ No detection methods enabled');
        } else {
            $this->components->info('✓ Enabled detection methods: '.implode(', ', array_keys($enabledMethods)));
        }

        // Check 4: Routes with version attributes
        $allRoutes = $this->router->getRoutes();
        /** @var Route[] $routeArray */
        $routeArray = iterator_to_array($allRoutes, false);
        $routes = collect($routeArray);
        $versionedRoutes = $routes->filter(function (Route $route): bool {
            $versions = $this->resolver->getAllVersionsForRoute($route);

            return $versions !== [];
        });

        if ($versionedRoutes->isEmpty()) {
            $this->recordWarning('⚠ No routes with version attributes found');
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
            $this->recordWarning('⚠ Configured versions not used in any route: '.implode(', ', $orphanedVersions));
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
            $this->recordWarning('⚠ Attribute caching disabled (may impact performance)');
        }

        // Check 7: version_method_mapping keys that aren't in supported_versions
        $mappedVersions = array_keys($this->configService->getVersionMappings());
        $unmappedSupported = array_diff($mappedVersions, $supportedVersions);
        if ($unmappedSupported !== []) {
            $this->recordWarning('⚠ version_method_mapping declares versions not in supported_versions: '.implode(', ', $unmappedSupported));
        } else {
            $this->components->info('✓ version_method_mapping keys are all supported versions');
        }

        // Check 8: cycles in version_inheritance
        $cycle = $this->configService->findInheritanceCycle();
        if ($cycle !== null) {
            $this->components->error('✗ version_inheritance contains a cycle: '.implode(' -> ', $cycle));
            $hasErrors = true;
        } else {
            $this->components->info('✓ version_inheritance has no cycles');
        }

        $this->newLine();

        if ($hasErrors || ($strict && $this->hasWarnings)) {
            $this->components->error($hasErrors
                ? 'Health check failed with errors'
                : 'Health check failed: warnings present and --strict was given');

            return self::FAILURE;
        }

        $this->components->info('✅ All health checks passed!');

        return self::SUCCESS;
    }

    private function recordWarning(string $message): void
    {
        $this->hasWarnings = true;
        $this->components->warn($message);
    }
}
