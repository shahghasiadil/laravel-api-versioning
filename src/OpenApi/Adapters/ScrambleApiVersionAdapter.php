<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\OpenApi\Adapters;

use ShahGhasiAdil\LaravelApiVersioning\OpenApi\ApiVersionDescriptionProvider;
use ShahGhasiAdil\LaravelApiVersioning\ValueObjects\ApiVersionDescription;

/**
 * Registers one dedoc/scramble API document per version this application
 * serves, deriving each document's `info` from {@see ApiVersionDescription}
 * so deprecated versions carry that in their generated docs without the
 * application repeating what {@see ApiVersionDescriptionProvider} already
 * knows.
 *
 * Scramble is never required by this package -- it's referenced only by
 * class name string, guarded by {@see self::isAvailable()}, so composer.json
 * carries no dependency on it either way.
 *
 * The application still owns exposing each registered API (choosing UI /
 * document routes via `->expose(...)`) and, if it versions by URL path,
 * scoping each document to its routes via `api_path` in $configOverrides
 * or the per-version override returned by $pathForVersion -- this adapter
 * only handles "which versions exist and which are deprecated".
 */
final class ScrambleApiVersionAdapter
{
    private const SCRAMBLE_CLASS = 'Dedoc\\Scramble\\Scramble';

    public static function isAvailable(): bool
    {
        return class_exists(self::SCRAMBLE_CLASS);
    }

    /**
     * @param  array<string, mixed>  $configOverrides  Merged into every registered API's config, before the per-version info.
     * @param  (callable(string): string)|null  $pathForVersion  Given a version, returns the `api_path` to scope that version's document to.
     * @return array<string, mixed> The Scramble API registration object for each version, keyed by version, so callers can call ->expose() on it. Empty if Scramble isn't installed.
     */
    public static function register(
        ApiVersionDescriptionProvider $provider,
        array $configOverrides = [],
        ?callable $pathForVersion = null,
    ): array {
        if (! self::isAvailable()) {
            return [];
        }

        $registered = [];

        foreach ($provider->describe() as $description) {
            $registered[$description->version] = self::registerVersion($description, $configOverrides, $pathForVersion);
        }

        return $registered;
    }

    /**
     * @param  array<string, mixed>  $configOverrides
     * @param  (callable(string): string)|null  $pathForVersion
     */
    private static function registerVersion(
        ApiVersionDescription $description,
        array $configOverrides,
        ?callable $pathForVersion,
    ): mixed {
        $info = array_filter([
            'version' => $description->version,
            'description' => self::deprecationNotice($description),
        ], fn (mixed $value): bool => $value !== null);

        $config = array_replace_recursive($configOverrides, ['info' => $info]);

        if ($pathForVersion !== null) {
            $config['api_path'] = $pathForVersion($description->version);
        }

        $scrambleClass = self::SCRAMBLE_CLASS;

        return $scrambleClass::registerApi($description->version, $config);
    }

    private static function deprecationNotice(ApiVersionDescription $description): ?string
    {
        if (! $description->isDeprecated) {
            return null;
        }

        $sunset = $description->sunsetPolicy?->formattedDate();

        return $sunset !== null
            ? "This API version is deprecated and will sunset on {$sunset}."
            : 'This API version is deprecated.';
    }
}
