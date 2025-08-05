<?php

namespace ShahGhasiAdil\LaravelApiVersioning;

use Illuminate\Support\ServiceProvider;
use ShahGhasiAdil\LaravelApiVersioning\Middleware\AttributeApiVersionMiddleware;
use ShahGhasiAdil\LaravelApiVersioning\Services\AttributeVersionResolver;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionManager;

class ApiVersioningServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(VersionManager::class, function ($app) {
            return new VersionManager($app['config']->get('api-versioning', []));
        });

        $this->app->singleton(AttributeVersionResolver::class, function ($app) {
            return new AttributeVersionResolver($app->make(VersionManager::class));
        });
    }

    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/api-versioning.php' => config_path('api-versioning.php'),
        ], 'config');

        $this->app['router']->aliasMiddleware('api.version', AttributeApiVersionMiddleware::class);
    }
}
