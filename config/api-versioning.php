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
    | application's supported_versions list.
    |
    | 'legacy_headers' keeps emitting this package's original `X-API-*`
    | headers (X-API-Supported-Versions, X-API-Route-Versions, X-API-
    | Deprecated, etc.) for backward compatibility. Disable it once your
    | clients have migrated to the standard headers above.
    |
    */
    'reporting' => [
        'standard_headers' => true,
        'legacy_headers' => true,
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
];
