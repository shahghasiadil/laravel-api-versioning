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
        $allRoutes = $this->router->getRoutes();
        /** @var \Illuminate\Routing\Route[] $routeArray */
        $routeArray = iterator_to_array($allRoutes, false);

        $routes = collect($routeArray)->filter(function (\Illuminate\Routing\Route $route): bool {
            return str_contains($route->uri(), 'api/');
        });

        /** @var string|null $routeFilter */
        $routeFilter = $this->option('route');
        if (is_string($routeFilter) && $routeFilter !== '') {
            $routes = $routes->filter(fn (\Illuminate\Routing\Route $route): bool => str_contains($route->uri(), $routeFilter));
        }

        $headers = ['Method', 'URI', 'Controller', 'Versions', 'Deprecated', 'Sunset Date'];
        $rows = [];

        foreach ($routes as $route) {
            $methods = implode('|', $route->methods());
            $uri = $route->uri();
            $action = $route->getActionName();

            $allVersions = $this->resolver->getAllVersionsForRoute($route);
            $versionsStr = implode(', ', $allVersions);

            /** @var string|null $versionFilter */
            $versionFilter = $this->option('api-version');
            if (is_string($versionFilter) && $versionFilter !== '' && ! in_array($versionFilter, $allVersions, true)) {
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

            if ((bool) $this->option('deprecated') && $deprecatedInfo !== 'Yes') {
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
            if ((bool) $this->option('json')) {
                $encodedJson = json_encode(['routes' => [], 'supported_versions' => $this->versionManager->getSupportedVersions()], JSON_PRETTY_PRINT);
                $this->line($encodedJson !== false ? $encodedJson : '{}');
            } else {
                $this->info('No matching routes found.');
            }

            return self::SUCCESS;
        }

        // JSON output
        if ((bool) $this->option('json')) {
            $jsonData = [
                'routes' => array_map(function (array $row) use ($headers): array {
                    /** @var array<string, string> $combined */
                    $combined = array_combine($headers, $row);

                    return $combined;
                }, $rows),
                'supported_versions' => $this->versionManager->getSupportedVersions(),
                'total_routes' => count($rows),
            ];

            $encodedJson = json_encode($jsonData, JSON_PRETTY_PRINT);
            $this->line($encodedJson !== false ? $encodedJson : '{}');

            return self::SUCCESS;
        }

        // Compact format
        if ((bool) $this->option('compact')) {
            $headers = ['Method', 'URI', 'Versions', 'Deprecated'];
            $rows = array_map(fn (array $row): array => [$row[0], $row[1], $row[3], $row[4]], $rows);
        }

        $this->table($headers, $rows);

        $this->newLine();
        $this->info('Supported API Versions: '.implode(', ', $this->versionManager->getSupportedVersions()));
        $this->info('Total Routes: '.count($rows));

        return self::SUCCESS;
    }
}
