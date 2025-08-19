<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning;

use Illuminate\Support\ServiceProvider;
use ShahGhasiAdil\LaravelApiVersioning\Console\Commands\ApiVersionConfigCommand;
use ShahGhasiAdil\LaravelApiVersioning\Console\Commands\ApiVersionsCommand;
use ShahGhasiAdil\LaravelApiVersioning\Console\Commands\MakeVersionedControllerCommand;
use ShahGhasiAdil\LaravelApiVersioning\Middleware\AttributeApiVersionMiddleware;
use ShahGhasiAdil\LaravelApiVersioning\Services\AttributeVersionResolver;
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

        $this->app->singleton(VersionManager::class, function ($app): VersionManager {
            return new VersionManager($app['config']->get('api-versioning', []));
        });

        $this->app->singleton(AttributeVersionResolver::class, function ($app): AttributeVersionResolver {
            return new AttributeVersionResolver($app->make(VersionManager::class));
        });

        $this->app->singleton(VersionConfigService::class, function ($app): VersionConfigService {
            return new VersionConfigService;
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/api-versioning.php' => config_path('api-versioning.php'),
        ], 'config');

        $this->app['router']->aliasMiddleware('api.version', AttributeApiVersionMiddleware::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                ApiVersionsCommand::class,
                ApiVersionConfigCommand::class,
                MakeVersionedControllerCommand::class,
            ]);
        }
    }
}
