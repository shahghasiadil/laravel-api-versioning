<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Http;

use Illuminate\Http\Request;
use ShahGhasiAdil\LaravelApiVersioning\Traits\HasApiVersionAttributes;
use ShahGhasiAdil\LaravelApiVersioning\ValueObjects\VersionInfo;

/**
 * Registers Request::apiVersion()/apiVersionInfo()/isApiVersionDeprecated()
 * macros, so version info resolved by the versioning middleware is reachable
 * from anywhere a Request instance is available -- form requests, jobs,
 * resources, custom middleware -- not just controllers using
 * {@see HasApiVersionAttributes}.
 */
class RequestMacros
{
    public static function register(): void
    {
        Request::macro('apiVersion', function (): ?string {
            /** @var Request $this */
            $version = $this->attributes->get('api_version');

            return is_string($version) ? $version : null;
        });

        Request::macro('apiVersionInfo', function (): ?VersionInfo {
            /** @var Request $this */
            $versionInfo = $this->attributes->get('api_version_info');

            return $versionInfo instanceof VersionInfo ? $versionInfo : null;
        });

        Request::macro('isApiVersionDeprecated', function (): bool {
            /** @var Request $this */
            $versionInfo = $this->attributes->get('api_version_info');

            return $versionInfo instanceof VersionInfo && $versionInfo->isDeprecated;
        });
    }
}
