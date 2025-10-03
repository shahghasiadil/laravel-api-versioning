<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning;

use Illuminate\Support\ServiceProvider;
use ShahGhasiAdil\LaravelApiVersioning\Console\Commands\ApiCacheClearCommand;
use ShahGhasiAdil\LaravelApiVersioning\Console\Commands\ApiVersionConfigCommand;
use ShahGhasiAdil\LaravelApiVersioning\Console\Commands\ApiVersionHealthCommand;
use ShahGhasiAdil\LaravelApiVersioning\Console\Commands\ApiVersionsCommand;
use ShahGhasiAdil\LaravelApiVersioning\Console\Commands\GenerateOpenApiCommand;
use ShahGhasiAdil\LaravelApiVersioning\Console\Commands\MakeVersionedControllerCommand;
use ShahGhasiAdil\LaravelApiVersioning\Middleware\AttributeApiVersionMiddleware;
use ShahGhasiAdil\LaravelApiVersioning\Middleware\VersionedRateLimitMiddleware;
use ShahGhasiAdil\LaravelApiVersioning\Services\AttributeCacheService;
use ShahGhasiAdil\LaravelApiVersioning\Services\AttributeVersionResolver;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionComparator;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionConfigService;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionManager;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionNegotiator;

class ApiVersioningServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/api-versioning.php',
            'api-versioning'
        );

        $this->app->singleton(VersionManager::class, function ($app): VersionManager {
            return new VersionManager($app['config']->get('api-versioning', []));
        });

        $this->app->singleton(AttributeCacheService::class, function ($app): AttributeCacheService {
            $config = $app['config']->get('api-versioning.cache', []);

            return new AttributeCacheService(
                enabled: $config['enabled'] ?? true,
                ttl: $config['ttl'] ?? 3600
            );
        });

        $this->app->singleton(AttributeVersionResolver::class, function ($app): AttributeVersionResolver {
            return new AttributeVersionResolver(
                $app->make(VersionManager::class),
                $app->make(AttributeCacheService::class)
            );
        });

        $this->app->singleton(VersionConfigService::class, function ($app): VersionConfigService {
            return new VersionConfigService;
        });

        $this->app->singleton(VersionComparator::class, function ($app): VersionComparator {
            return new VersionComparator;
        });

        $this->app->singleton(VersionNegotiator::class, function ($app): VersionNegotiator {
            return new VersionNegotiator(
                $app->make(VersionManager::class),
                $app->make(VersionComparator::class),
                $app['config']->get('api-versioning', [])
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/api-versioning.php' => config_path('api-versioning.php'),
        ], 'config');

        $this->app['router']->aliasMiddleware('api.version', AttributeApiVersionMiddleware::class);
        $this->app['router']->aliasMiddleware('api.version.ratelimit', VersionedRateLimitMiddleware::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                ApiVersionsCommand::class,
                ApiVersionConfigCommand::class,
                ApiVersionHealthCommand::class,
                ApiCacheClearCommand::class,
                GenerateOpenApiCommand::class,
                MakeVersionedControllerCommand::class,
            ]);
        }
    }
}
