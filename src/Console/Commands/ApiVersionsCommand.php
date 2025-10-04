<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Router;
use ShahGhasiAdil\LaravelApiVersioning\Services\AttributeVersionResolver;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionManager;

class ApiVersionsCommand extends Command
{
    protected $signature = 'api:versions
                           {--route= : Filter by specific route pattern}
                           {--api-version= : Filter by specific version}
                           {--deprecated : Show only deprecated endpoints}
                           {--json : Output as JSON}
                           {--compact : Use compact table format}';

    protected $description = 'Display API versioning information for all routes';

    public function __construct(
        private readonly Router $router,
        private readonly VersionManager $versionManager,
        private readonly AttributeVersionResolver $resolver
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $routes = collect($this->router->getRoutes())->filter(function ($route): bool {
            return str_contains($route->uri(), 'api/');
        });

        $routeFilter = $this->option('route');
        if (is_string($routeFilter)) {
            $routes = $routes->filter(fn ($route) => str_contains($route->uri(), $routeFilter));
        }

        $headers = ['Method', 'URI', 'Controller', 'Versions', 'Deprecated', 'Sunset Date'];
        $rows = [];

        foreach ($routes as $route) {
            $methods = implode('|', $route->methods());
            $uri = $route->uri();
            $action = $route->getActionName();

            $allVersions = $this->resolver->getAllVersionsForRoute($route);
            $versionsStr = implode(', ', $allVersions);

            $versionFilter = $this->option('api-version');
            if (is_string($versionFilter) && ! in_array($versionFilter, $allVersions, true)) {
                continue;
            }

            $deprecatedInfo = '';
            $sunsetDate = '';

            // Check if any version is deprecated
            foreach ($allVersions as $version) {
                try {
                    $versionInfo = $this->resolver->resolveVersionForRoute($route, $version);
                    if ($versionInfo !== null && $versionInfo->isDeprecated) {
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
                $versionsStr !== '' ? $versionsStr : 'None',
                $deprecatedInfo !== '' ? $deprecatedInfo : 'No',
                $sunsetDate !== '' ? $sunsetDate : '-',
            ];
        }

        if ($rows === []) {
            if ($this->option('json')) {
                $this->line(json_encode(['routes' => [], 'supported_versions' => $this->versionManager->getSupportedVersions()], JSON_PRETTY_PRINT));
            } else {
                $this->info('No matching routes found.');
            }

            return self::SUCCESS;
        }

        // JSON output
        if ($this->option('json')) {
            $jsonData = [
                'routes' => array_map(function ($row) use ($headers) {
                    return array_combine($headers, $row);
                }, $rows),
                'supported_versions' => $this->versionManager->getSupportedVersions(),
                'total_routes' => count($rows),
            ];

            $this->line(json_encode($jsonData, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        // Compact format
        if ($this->option('compact')) {
            $headers = ['Method', 'URI', 'Versions', 'Deprecated'];
            $rows = array_map(fn ($row) => [$row[0], $row[1], $row[3], $row[4]], $rows);
        }

        $this->table($headers, $rows);

        $this->newLine();
        $this->info('Supported API Versions: '.implode(', ', $this->versionManager->getSupportedVersions()));
        $this->info('Total Routes: '.count($rows));

        return self::SUCCESS;
    }
}
