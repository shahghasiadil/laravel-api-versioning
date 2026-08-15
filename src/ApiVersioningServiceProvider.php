<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use ShahGhasiAdil\LaravelApiVersioning\Console\Commands\ApiCacheClearCommand;
use ShahGhasiAdil\LaravelApiVersioning\Console\Commands\ApiVersionConfigCommand;
use ShahGhasiAdil\LaravelApiVersioning\Console\Commands\ApiVersionHealthCommand;
use ShahGhasiAdil\LaravelApiVersioning\Console\Commands\ApiVersionsCommand;
use ShahGhasiAdil\LaravelApiVersioning\Console\Commands\MakeVersionedControllerCommand;
use ShahGhasiAdil\LaravelApiVersioning\Conventions\ConventionRegistry;
use ShahGhasiAdil\LaravelApiVersioning\Http\RequestMacros;
use ShahGhasiAdil\LaravelApiVersioning\Middleware\AttributeApiVersionMiddleware;
use ShahGhasiAdil\LaravelApiVersioning\Services\AttributeCacheService;
use ShahGhasiAdil\LaravelApiVersioning\Services\AttributeVersionResolver;
use ShahGhasiAdil\LaravelApiVersioning\Services\SunsetPolicyManager;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionComparator;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionConfigService;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionManager;

class ApiVersioningServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/api-versioning.php',
            'api-versioning'
        );

        $this->app->singleton(VersionManager::class, function (Application $app): VersionManager {
            /** @var Repository $configRepo */
            $configRepo = $app->make('config');
            /** @var array<string, mixed> $config */
            $config = $configRepo->get('api-versioning', []);

            return new VersionManager($config);
        });

        $this->app->singleton(AttributeCacheService::class, function (Application $app): AttributeCacheService {
            /** @var Repository $configRepo */
            $configRepo = $app->make('config');
            /** @var array{enabled?: bool|string, ttl?: int|string} $config */
            $config = $configRepo->get('api-versioning.cache', []);

            return new AttributeCacheService(
                enabled: (bool) ($config['enabled'] ?? true),
                ttl: (int) ($config['ttl'] ?? 3600)
            );
        });

        $this->app->singleton(AttributeVersionResolver::class, function (Application $app): AttributeVersionResolver {
            return new AttributeVersionResolver(
                $app->make(VersionManager::class),
                $app->make(AttributeCacheService::class),
                $app->make(ConventionRegistry::class),
            );
        });

        $this->app->singleton(VersionConfigService::class, function (Application $app): VersionConfigService {
            return new VersionConfigService;
        });

        $this->app->singleton(VersionComparator::class, function (Application $app): VersionComparator {
            return new VersionComparator;
        });

        $this->app->singleton(SunsetPolicyManager::class, function (Application $app): SunsetPolicyManager {
            return new SunsetPolicyManager;
        });

        $this->app->singleton(ConventionRegistry::class, function (Application $app): ConventionRegistry {
            return new ConventionRegistry;
        });

        // Registered (not resolved) here: VersionManager must stay lazily
        // resolved, so its config snapshot is taken at first real use, not
        // at container-wiring time. AttributeVersionResolver is likewise
        // only resolved lazily, inside the closure, when a route actually
        // needs its implemented-versions list -- avoiding a resolution
        // cycle between the two singletons (AttributeVersionResolver
        // itself depends on VersionManager).
        $this->app->resolving(VersionManager::class, function (VersionManager $versionManager, Application $app): void {
            $versionManager->setRouteVersionsProvider(
                static fn (Route $route): array => $app->make(AttributeVersionResolver::class)->getImplementedVersionsForRoute($route)
            );
        });
    }

    /**
     * Cheap, opt-in ('validate_on_boot') sanity checks that catch a broken
     * config before the first request hits it. Never runs in production,
     * regardless of the config value, so this never adds boot-time cost to
     * a production deployment.
     */
    private function validateOnBoot(): void
    {
        if ($this->app->environment('production')) {
            return;
        }

        /** @var mixed $enabled */
        $enabled = config('api-versioning.validate_on_boot', false);
        if (! $enabled) {
            return;
        }

        /** @var VersionManager $versionManager */
        $versionManager = $this->app->make(VersionManager::class);
        /** @var VersionConfigService $configService */
        $configService = $this->app->make(VersionConfigService::class);

        $default = $versionManager->getDefaultVersion();
        if (! in_array($default, $versionManager->getSupportedVersions(), true)) {
            logger()->warning("[laravel-api-versioning] default_version '{$default}' is not in supported_versions.");
        }

        $cycle = $configService->findInheritanceCycle();
        if ($cycle !== null) {
            logger()->warning('[laravel-api-versioning] version_inheritance contains a cycle: '.implode(' -> ', $cycle));
        }
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/api-versioning.php' => config_path('api-versioning.php'),
        ], 'config');

        /** @var Router $router */
        $router = $this->app->make('router');
        $router->aliasMiddleware('api.version', AttributeApiVersionMiddleware::class);

        RequestMacros::register();

        $this->bootOctaneMemoryCacheReset();
        $this->validateOnBoot();

        if ($this->app->runningInConsole()) {
            $this->commands([
                ApiVersionsCommand::class,
                ApiVersionConfigCommand::class,
                ApiVersionHealthCommand::class,
                ApiCacheClearCommand::class,
                MakeVersionedControllerCommand::class,
            ]);
        }
    }

    /**
     * Under Octane, workers persist across requests, so
     * {@see AttributeVersionResolver}'s static in-process memory cache
     * would otherwise survive from one request to the next and never see
     * a mid-process config or attribute change. Reset it on both request
     * boundaries when Octane is installed; a no-op otherwise.
     */
    private function bootOctaneMemoryCacheReset(): void
    {
        // Referenced by string, not ::class: laravel/octane is an optional
        // peer, not a dependency of this package, so its classes don't
        // exist (and shouldn't be required to exist) outside an Octane
        // deployment or static analysis.
        $requestReceived = 'Laravel\Octane\Events\RequestReceived';
        $requestTerminated = 'Laravel\Octane\Events\RequestTerminated';

        if (! class_exists($requestReceived)) {
            return;
        }

        /** @var Dispatcher $events */
        $events = $this->app->make('events');
        $reset = static function (): void {
            AttributeVersionResolver::resetMemoryCache();
        };

        $events->listen($requestReceived, $reset);
        $events->listen($requestTerminated, $reset);
    }
}
