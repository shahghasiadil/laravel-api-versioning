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
];
