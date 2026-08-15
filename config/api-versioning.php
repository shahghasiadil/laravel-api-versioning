<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default API Version
    |--------------------------------------------------------------------------
    |
    | The default version to use when no version is specified in the request.
    | This should be a string representing your current stable API version.
    |
    */
    'default_version' => '1.0',

    /*
    |--------------------------------------------------------------------------
    | Version Detection Methods
    |--------------------------------------------------------------------------
    |
    | Configure how API versions should be detected from incoming requests.
    | Multiple methods can be enabled simultaneously, and they will be
    | checked in the order they are defined here.
    |
    */
    'detection_methods' => [
        'header' => [
            'enabled' => true,
            'header_name' => 'X-API-Version',
        ],
        'query' => [
            'enabled' => true,
            'parameter_name' => 'api-version',
        ],
        'path' => [
            'enabled' => true,
            'prefix' => 'api/v',
        ],
        'media_type' => [
            'enabled' => false,
            'format' => 'application/vnd.api+json;version=%s',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Version Readers (alternative to 'detection_methods' above)
    |--------------------------------------------------------------------------
    |
    | Every entry under 'detection_methods' above is implemented as a
    | ShahGhasiAdil\LaravelApiVersioning\Services\VersionReaders\ApiVersionReader
    | strategy object under the hood. If you need a reader 'detection_methods'
    | can't express -- a different header set, a custom source (a subdomain,
    | a JWT claim), or just explicit control over reader order -- list reader
    | classes here instead. When non-empty, this list is used in place of
    | 'detection_methods' entirely; each value is passed to the reader's
    | constructor as named arguments.
    |
    | 'readers' => [
    |     \ShahGhasiAdil\LaravelApiVersioning\Services\VersionReaders\HeaderApiVersionReader::class => [
    |         'headerName' => 'X-API-Version',
    |     ],
    |     \ShahGhasiAdil\LaravelApiVersioning\Services\VersionReaders\QueryStringApiVersionReader::class => [
    |         'parameterName' => 'api-version',
    |     ],
    |     \ShahGhasiAdil\LaravelApiVersioning\Services\VersionReaders\UrlSegmentApiVersionReader::class => [
    |         'prefix' => 'api/v',
    |     ],
    |     // A custom reader, e.g. reading the version from a JWT claim:
    |     // \App\Versioning\JwtClaimApiVersionReader::class => ['claim' => 'api_version'],
    | ],
    |
    */
    'readers' => [],

    /*
    |--------------------------------------------------------------------------
    | Version Detection Strictness
    |--------------------------------------------------------------------------
    |
    | Fine-tune how strictly incoming version values are validated. Each of
    | these defaults to disabled, preserving this package's original,
    | lenient behavior; enable them individually to adopt stricter semantics.
    |
    | - 'require_explicit_version': when true, a request that specifies no
    |   version at all returns a 400 "Unspecified API Version" error instead
    |   of silently assuming 'default_version' above.
    |
    | - 'reject_conflicting_versions': when true, a request where multiple
    |   enabled detection methods disagree (e.g. the header says 1.0 but the
    |   query string says 2.0) returns a 400 "Ambiguous API Version" error
    |   instead of silently using whichever method is listed first in
    |   'detection_methods' above.
    |
    | - 'format_validation': when enabled, a detected version value that
    |   doesn't match 'pattern' returns a 400 "Invalid API Version" error
    |   instead of falling through to the less specific "unsupported
    |   version" error.
    |
    */
    'version_detection' => [
        'require_explicit_version' => false,
        'reject_conflicting_versions' => false,
        'format_validation' => [
            'enabled' => false,
            'pattern' => '/^\d+(?:\.\d+)*(?:-[a-zA-Z0-9]+)?$/',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Closure Routes
    |--------------------------------------------------------------------------
    |
    | Routes defined with a Closure (or any callable without a controller
    | class) have no class/method to carry #[ApiVersion] or #[Deprecated]
    | attributes on, so there is nothing for the resolver to check.
    |
    | 'neutral' (default) treats them like #[ApiVersionNeutral]: they
    | respond to every version in 'supported_versions' below.
    |
    | 'reject' restores this package's original behavior of returning a
    | 400 "Unsupported API Version" for every request to a closure route.
    |
    */
    'closure_routes' => 'neutral',

    /*
    |--------------------------------------------------------------------------
    | Supported API Versions
    |--------------------------------------------------------------------------
    |
    | List all the API versions that your application supports.
    | Requests for unsupported versions will return a 400 error.
    |
    */
    'supported_versions' => [
        '1.0',
        '1.1',
        '2.0',
        '2.1',
    ],

    /*
    |--------------------------------------------------------------------------
    | Version Method Mapping
    |--------------------------------------------------------------------------
    |
    | Define how API versions map to resource transformation methods.
    | This allows you to configure version inheritance and method mapping.
    |
    */
    'version_method_mapping' => [
        '1.0' => 'toArrayV1',
        '1.1' => 'toArrayV11',
        '2.0' => 'toArrayV2',
        '2.1' => 'toArrayV21',
    ],

    /*
    |--------------------------------------------------------------------------
    | Version Inheritance
    |--------------------------------------------------------------------------
    |
    | Define which versions inherit from other versions when a specific
    | method doesn't exist. This creates a fallback chain.
    |
    */
    'version_inheritance' => [
        '1.1' => '1.0',  // v1.1 falls back to v1.0 if method doesn't exist
        '2.1' => '2.0',  // v2.1 falls back to v2.0 if method doesn't exist
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Fallback Method
    |--------------------------------------------------------------------------
    |
    | The method to call when no specific version method is found
    | and no inheritance chain can resolve it.
    |
    */
    'default_method' => 'toArrayDefault',

    /*
    |--------------------------------------------------------------------------
    | Version Reporting Headers
    |--------------------------------------------------------------------------
    |
    | Controls which response headers advertise version support.
    |
    | 'standard_headers' emits the endpoint-scoped `api-supported-versions`
    | and `api-deprecated-versions` headers, matching common REST API
    | versioning conventions (e.g. ASP.NET's API Versioning library). These
    | report the versions *this specific endpoint* serves, not your whole
    | application's supported_versions list. It also governs the RFC 8594
    | `Sunset` and RFC 8288 `Link` headers described under "Sunset Policies"
    | below.
    |
    | 'legacy_headers' keeps emitting this package's original `X-API-*`
    | headers (X-API-Supported-Versions, X-API-Route-Versions, X-API-
    | Deprecated, X-API-Sunset, etc.) for backward compatibility. Disable it
    | once your clients have migrated to the standard headers above.
    |
    */
    'reporting' => [
        'standard_headers' => true,
        'legacy_headers' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sunset Policies
    |--------------------------------------------------------------------------
    |
    | Declare when a version will stop responding entirely, independent of
    | (and in addition to) any #[Deprecated] attribute. When
    | 'reporting.standard_headers' is enabled, a version with a resolvable
    | policy gets an RFC 8594 `Sunset` header (an HTTP-date) and, if 'link'
    | is set, an RFC 8288 `Link: <url>; rel="sunset"` header pointing to a
    | migration guide or changelog.
    |
    | A version deprecated via #[Deprecated(sunsetDate: '...')] or
    | #[ApiVersion(sunset: '...')] already gets a Sunset header for free,
    | with no config needed; declare a policy here only to also attach a
    | link, or to sunset a version without a code change.
    |
    | 'date' accepts anything PHP's DateTimeImmutable can parse (e.g.
    | 'Y-m-d', 'Y-m-d\TH:i:sP', or a relative expression).
    |
    */
    'sunset_policies' => [
        // '1.0' => [
        //     'date' => '2026-06-30',
        //     'link' => 'https://docs.example.com/migrating-to-v2',
        //     'link_type' => 'text/html',
        //     'link_title' => 'Migration guide',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Documentation
    |--------------------------------------------------------------------------
    |
    | URL to your API documentation. This will be included in error responses
    | to help developers find more information about supported versions.
    |
    */
    'documentation' => [
        'base_url' => env('API_DOCUMENTATION_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Enable caching of attribute resolution to improve performance.
    | Caching significantly reduces reflection overhead on high-traffic APIs.
    |
    */
    'cache' => [
        'enabled' => env('API_VERSIONING_CACHE_ENABLED', true),
        'ttl' => env('API_VERSIONING_CACHE_TTL', 3600), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Validate On Boot
    |--------------------------------------------------------------------------
    |
    | When enabled, a handful of cheap configuration checks (default
    | version present in supported_versions, no cycle in
    | version_inheritance) run once during application boot and log a
    | warning if they fail -- catching a broken config before the first
    | request hits it, instead of only via 'api:version:health'.
    |
    | Defaults to APP_DEBUG so it's on in local/testing and off in
    | production, where it's skipped entirely regardless of this setting
    | to avoid adding any boot-time cost to production requests.
    |
    */
    'validate_on_boot' => env('API_VERSIONING_VALIDATE_ON_BOOT', env('APP_DEBUG', false)),
];
