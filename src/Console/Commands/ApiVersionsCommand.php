<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Route;
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
                           {--compact : Use compact table format}
                           {--all : Do not filter by the configured API path prefix; list every registered route}';

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
        /** @var Route[] $routeArray */
        $routeArray = iterator_to_array($allRoutes, false);

        $routes = collect($routeArray);

        if (! (bool) $this->option('all')) {
            $pathBase = $this->configuredPathBase();
            $routes = $routes->filter(fn (Route $route): bool => str_starts_with($route->uri(), $pathBase));
        }

        /** @var string|null $routeFilter */
        $routeFilter = $this->option('route');
        if (is_string($routeFilter) && $routeFilter !== '') {
            $routes = $routes->filter(fn (Route $route): bool => str_contains($route->uri(), $routeFilter));
        }

        $headers = ['Method', 'URI', 'Controller', 'Versions', 'Deprecated', 'Sunset Date'];
        $rows = [];

        foreach ($routes as $route) {
            /** @var string[] $routeMethods */
            $routeMethods = $route->methods();
            $methods = implode('|', $routeMethods);
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
            $resolutionError = null;

            // Check if any version is deprecated
            foreach ($allVersions as $version) {
                try {
                    $versionInfo = $this->resolver->resolveVersionForRoute($route, $version);
                    if ($versionInfo !== null && $versionInfo->isDeprecated) {
                        $deprecatedInfo = 'Yes';
                        $sunsetDate = $versionInfo->sunsetDate ?? 'Not set';
                        break;
                    }
                } catch (\Throwable $e) {
                    // Surface resolution failures rather than silently
                    // reporting the route as healthy: a route whose
                    // attributes fail to resolve is not the same thing as
                    // one with no deprecated versions.
                    $resolutionError = $e->getMessage();
                    break;
                }
            }

            if ($resolutionError !== null) {
                $deprecatedInfo = 'ERROR';
                $sunsetDate = $resolutionError;
            }

            if ((bool) $this->option('deprecated') && $deprecatedInfo !== 'Yes' && $resolutionError === null) {
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

    /**
     * The static leading path segment routes are expected to share, derived
     * from the configured path-detection prefix (e.g. 'api/v' => 'api/').
     * Falls back to 'api/' when path detection has no usable prefix
     * configured, matching this command's original behavior.
     */
    private function configuredPathBase(): string
    {
        /** @var mixed $prefix */
        $prefix = config('api-versioning.detection_methods.path.prefix', 'api/v');
        $prefix = is_string($prefix) && $prefix !== '' ? $prefix : 'api/v';

        $lastSlash = strrpos($prefix, '/');

        return $lastSlash !== false ? substr($prefix, 0, $lastSlash + 1) : $prefix;
    }
}
