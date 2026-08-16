<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\OpenApi\Adapters;

use ShahGhasiAdil\LaravelApiVersioning\OpenApi\ApiVersionDescriptionProvider;
use ShahGhasiAdil\LaravelApiVersioning\ValueObjects\ApiVersionDescription;

/**
 * Builds a `documentations` array, keyed by version, for
 * darkaonline/l5-swagger's multi-documentation config
 * (`config/l5-swagger.php`'s `documentations` key). L5-Swagger has no
 * runtime registration API to call into -- it scans annotation paths ahead
 * of time via `l5-swagger:generate` -- so this adapter only produces
 * config, it never touches the l5-swagger package directly and carries no
 * dependency on it.
 *
 * The application still owns where each version's annotated controllers
 * live on disk (`$annotationsByVersion`) -- this package has no way to
 * infer that -- and is expected to merge the returned array into its own
 * `config/l5-swagger.php` `documentations` entry, e.g.:
 *
 * ```php
 * 'documentations' => L5SwaggerApiVersionAdapter::documentationsConfig(
 *     app(ApiVersionDescriptionProvider::class),
 *     ['1.0' => [app_path('Http/Controllers/Api/V1')], '2.0' => [app_path('Http/Controllers/Api/V2')]],
 * ),
 * ```
 */
final class L5SwaggerApiVersionAdapter
{
    private const L5_SWAGGER_CLASS = 'L5Swagger\\L5SwaggerServiceProvider';

    public static function isAvailable(): bool
    {
        return class_exists(self::L5_SWAGGER_CLASS);
    }

    /**
     * @param  array<string, string[]>  $annotationsByVersion  Version => absolute paths to scan for annotations. Falls back to app/Http/Controllers when a version has no entry.
     * @return array<string, array<string, mixed>>
     */
    public static function documentationsConfig(
        ApiVersionDescriptionProvider $provider,
        array $annotationsByVersion = [],
    ): array {
        $documentations = [];

        foreach ($provider->describe() as $description) {
            $documentations[self::documentationKey($description->version)] = self::documentationConfig(
                $description,
                $annotationsByVersion[$description->version] ?? [app_path('Http/Controllers')],
            );
        }

        return $documentations;
    }

    /**
     * @param  string[]  $annotationPaths
     * @return array<string, mixed>
     */
    private static function documentationConfig(ApiVersionDescription $description, array $annotationPaths): array
    {
        $title = sprintf(
            'API Documentation v%s%s',
            $description->version,
            $description->isDeprecated ? ' (Deprecated)' : '',
        );

        $sunset = $description->sunsetPolicy?->formattedDate();

        return [
            'api' => array_filter([
                'title' => $title,
                'description' => $description->isDeprecated && $sunset !== null
                    ? "This API version is deprecated and will sunset on {$sunset}."
                    : ($description->isDeprecated ? 'This API version is deprecated.' : null),
            ], fn (mixed $value): bool => $value !== null),
            'routes' => [
                'api' => 'api/documentation/'.$description->version,
            ],
            'paths' => [
                'annotations' => $annotationPaths,
            ],
        ];
    }

    private static function documentationKey(string $version): string
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9]+/', '_', $version);

        return 'api_v_'.($sanitized ?? $version);
    }
}
