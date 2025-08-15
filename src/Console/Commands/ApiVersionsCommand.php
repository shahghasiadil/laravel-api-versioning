<?php

namespace ShahGhasiAdil\LaravelApiVersioning\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Router;
use ShahGhasiAdil\LaravelApiVersioning\Services\AttributeVersionResolver;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionManager;

class ApiVersionsCommand extends Command
{
    protected $signature = 'api:versions
                           {--route= : Filter by specific route pattern}
                           {--version= : Filter by specific version}
                           {--deprecated : Show only deprecated endpoints}';

    protected $description = 'Display API versioning information for all routes';

    public function __construct(
        private Router $router,
        private VersionManager $versionManager,
        private AttributeVersionResolver $resolver
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $routes = collect($this->router->getRoutes())->filter(function ($route) {
            return str_contains($route->uri(), 'api/');
        });

        if ($this->option('route')) {
            $pattern = $this->option('route');
            $routes = $routes->filter(fn ($route) => str_contains($route->uri(), $pattern));
        }

        $headers = ['Method', 'URI', 'Controller', 'Versions', 'Deprecated', 'Sunset Date'];
        $rows = [];

        foreach ($routes as $route) {
            $methods = implode('|', $route->methods());
            $uri = $route->uri();
            $action = $route->getActionName();

            $allVersions = $this->resolver->getAllVersionsForRoute($route);
            $versionsStr = implode(', ', $allVersions);

            if ($this->option('version') && ! in_array($this->option('version'), $allVersions)) {
                continue;
            }

            $deprecatedInfo = '';
            $sunsetDate = '';

            // Check if any version is deprecated
            foreach ($allVersions as $version) {
                try {
                    $versionInfo = $this->resolver->resolveVersionForRoute($route, $version);
                    if ($versionInfo && $versionInfo->isDeprecated) {
                        $deprecatedInfo = 'Yes';
                        $sunsetDate = $versionInfo->sunsetDate ?? 'Not set';
                        break;
                    }
                } catch (\Exception $e) {
                    // Skip if version resolution fails
                }
            }

            if ($this->option('deprecated') && $deprecatedInfo !== 'Yes') {
                continue;
            }

            $rows[] = [
                $methods,
                $uri,
                $action,
                $versionsStr ?: 'None',
                $deprecatedInfo ?: 'No',
                $sunsetDate ?: '-',
            ];
        }

        if (empty($rows)) {
            $this->info('No matching routes found.');

            return self::SUCCESS;
        }

        $this->table($headers, $rows);

        $this->newLine();
        $this->info('Supported API Versions: '.implode(', ', $this->versionManager->getSupportedVersions()));

        return self::SUCCESS;
    }
}
