<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning;

use Illuminate\Support\ServiceProvider;
use ShahGhasiAdil\LaravelApiVersioning\Console\Commands\ApiCacheClearCommand;
use ShahGhasiAdil\LaravelApiVersioning\Console\Commands\ApiVersionConfigCommand;
use ShahGhasiAdil\LaravelApiVersioning\Console\Commands\ApiVersionHealthCommand;
use ShahGhasiAdil\LaravelApiVersioning\Console\Commands\ApiVersionsCommand;
use ShahGhasiAdil\LaravelApiVersioning\Console\Commands\MakeVersionedControllerCommand;
use ShahGhasiAdil\LaravelApiVersioning\Middleware\AttributeApiVersionMiddleware;
use ShahGhasiAdil\LaravelApiVersioning\Services\AttributeCacheService;
use ShahGhasiAdil\LaravelApiVersioning\Services\AttributeVersionResolver;
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

        $this->app->singleton(VersionManager::class, function (\Illuminate\Contracts\Foundation\Application $app): VersionManager {
            /** @var \Illuminate\Config\Repository $configRepo */
            $configRepo = $app->make('config');
            /** @var array<string, mixed> $config */
            $config = $configRepo->get('api-versioning', []);

            return new VersionManager($config);
        });

        $this->app->singleton(AttributeCacheService::class, function (\Illuminate\Contracts\Foundation\Application $app): AttributeCacheService {
            /** @var \Illuminate\Config\Repository $configRepo */
            $configRepo = $app->make('config');
            /** @var array{enabled?: bool|string, ttl?: int|string} $config */
            $config = $configRepo->get('api-versioning.cache', []);

            return new AttributeCacheService(
                enabled: (bool) ($config['enabled'] ?? true),
                ttl: (int) ($config['ttl'] ?? 3600)
            );
        });

        $this->app->singleton(AttributeVersionResolver::class, function (\Illuminate\Contracts\Foundation\Application $app): AttributeVersionResolver {
            return new AttributeVersionResolver(
                $app->make(VersionManager::class),
                $app->make(AttributeCacheService::class)
            );
        });

        $this->app->singleton(VersionConfigService::class, function (\Illuminate\Contracts\Foundation\Application $app): VersionConfigService {
            return new VersionConfigService;
        });

        $this->app->singleton(VersionComparator::class, function (\Illuminate\Contracts\Foundation\Application $app): VersionComparator {
            return new VersionComparator;
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/api-versioning.php' => config_path('api-versioning.php'),
        ], 'config');

        /** @var \Illuminate\Routing\Router $router */
        $router = $this->app->make('router');
        $router->aliasMiddleware('api.version', AttributeApiVersionMiddleware::class);

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
}
