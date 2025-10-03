<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use ShahGhasiAdil\LaravelApiVersioning\Services\AttributeVersionResolver;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionManager;

class GenerateOpenApiCommand extends Command
{
    protected $signature = 'api:openapi:generate
                           {--api-version= : Generate for specific version}
                           {--output= : Output file path}
                           {--format=json : Output format (json or yaml)}';

    protected $description = 'Generate OpenAPI/Swagger documentation for API versions';

    public function __construct(
        private readonly Router $router,
        private readonly VersionManager $versionManager,
        private readonly AttributeVersionResolver $resolver
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $version = $this->option('api-version') ?? $this->versionManager->getDefaultVersion();
        $output = $this->option('output') ?? storage_path("api-docs/openapi-{$version}.json");
        $format = $this->option('format');

        $this->components->info("Generating OpenAPI documentation for version {$version}...");

        $openApiDoc = $this->generateOpenApiDocument($version);

        // Ensure directory exists
        $directory = dirname($output);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Save the documentation
        $content = $format === 'yaml'
            ? $this->toYaml($openApiDoc)
            : json_encode($openApiDoc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        file_put_contents($output, $content);

        $this->components->info("OpenAPI documentation generated: {$output}");
        $this->components->info('Total endpoints: '.count($openApiDoc['paths'] ?? []));

        return self::SUCCESS;
    }

    /**
     * Generate OpenAPI document structure
     *
     * @return array<string, mixed>
     */
    private function generateOpenApiDocument(string $version): array
    {
        $routes = $this->getRoutesForVersion($version);

        return [
            'openapi' => '3.0.0',
            'info' => [
                'title' => config('app.name').' API',
                'version' => $version,
                'description' => "API documentation for version {$version}",
            ],
            'servers' => [
                [
                    'url' => config('app.url').'/api',
                    'description' => 'API Server',
                ],
            ],
            'paths' => $this->generatePaths($routes, $version),
            'components' => [
                'schemas' => [],
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT',
                    ],
                ],
            ],
        ];
    }

    /**
     * Get routes for a specific version
     */
    private function getRoutesForVersion(string $version): array
    {
        $routes = collect($this->router->getRoutes())->filter(function ($route) use ($version) {
            $versions = $this->resolver->getAllVersionsForRoute($route);

            return in_array($version, $versions, true);
        });

        return $routes->all();
    }

    /**
     * Generate paths section of OpenAPI document
     *
     * @return array<string, mixed>
     */
    private function generatePaths(array $routes, string $version): array
    {
        $paths = [];

        foreach ($routes as $route) {
            $uri = '/'.ltrim($route->uri(), '/');
            $methods = array_diff($route->methods(), ['HEAD']);

            foreach ($methods as $method) {
                $method = strtolower($method);

                if (! isset($paths[$uri])) {
                    $paths[$uri] = [];
                }

                $paths[$uri][$method] = $this->generateOperation($route, $method, $version);
            }
        }

        return $paths;
    }

    /**
     * Generate operation object for a route
     *
     * @return array<string, mixed>
     */
    private function generateOperation($route, string $method, string $version): array
    {
        $action = $route->getActionName();
        $summary = $this->generateSummary($route, $method);

        $operation = [
            'summary' => $summary,
            'operationId' => $this->generateOperationId($route, $method),
            'tags' => $this->extractTags($route),
            'parameters' => $this->generateParameters($route),
            'responses' => [
                '200' => [
                    'description' => 'Successful response',
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                            ],
                        ],
                    ],
                ],
                '400' => [
                    'description' => 'Bad request',
                    'content' => [
                        'application/problem+json' => [
                            'schema' => [
                                '$ref' => '#/components/schemas/ProblemDetails',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // Check if route is deprecated
        $versionInfo = $this->resolver->resolveVersionForRoute($route, $version);
        if ($versionInfo !== null && $versionInfo->isDeprecated) {
            $operation['deprecated'] = true;
            if ($versionInfo->deprecationMessage !== null) {
                $operation['description'] = $versionInfo->deprecationMessage;
            }
        }

        return $operation;
    }

    /**
     * Generate summary from route
     */
    private function generateSummary($route, string $method): string
    {
        $action = $route->getActionName();
        $parts = explode('@', $action);
        $methodName = $parts[1] ?? 'handle';

        return ucfirst($method).' '.Str::headline($methodName);
    }

    /**
     * Generate operation ID
     */
    private function generateOperationId($route, string $method): string
    {
        $action = $route->getActionName();
        $parts = explode('@', $action);
        $controller = class_basename($parts[0] ?? 'Controller');
        $methodName = $parts[1] ?? 'handle';

        return Str::camel($controller.'_'.$methodName.'_'.$method);
    }

    /**
     * Extract tags from route
     *
     * @return array<string>
     */
    private function extractTags($route): array
    {
        $action = $route->getActionName();
        $parts = explode('@', $action);
        $controller = class_basename($parts[0] ?? 'Default');

        return [str_replace('Controller', '', $controller)];
    }

    /**
     * Generate parameters for route
     *
     * @return array<array<string, mixed>>
     */
    private function generateParameters($route): array
    {
        $parameters = [];

        // Extract path parameters
        preg_match_all('/{([^}]+)}/', $route->uri(), $matches);
        foreach ($matches[1] as $param) {
            $parameters[] = [
                'name' => $param,
                'in' => 'path',
                'required' => true,
                'schema' => [
                    'type' => 'string',
                ],
            ];
        }

        return $parameters;
    }

    /**
     * Convert array to YAML (basic implementation)
     */
    private function toYaml(array $data, int $indent = 0): string
    {
        $yaml = '';
        $spaces = str_repeat('  ', $indent);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $yaml .= $spaces.$key.":\n";
                $yaml .= $this->toYaml($value, $indent + 1);
            } else {
                $yaml .= $spaces.$key.': '.json_encode($value)."\n";
            }
        }

        return $yaml;
    }
}
