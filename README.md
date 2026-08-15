# Laravel API Versioning

[![Latest Version on Packagist](https://img.shields.io/packagist/v/shahghasiadil/laravel-api-versioning.svg?style=flat-square)](https://packagist.org/packages/shahghasiadil/laravel-api-versioning)
[![Total Downloads](https://img.shields.io/packagist/dt/shahghasiadil/laravel-api-versioning.svg?style=flat-square)](https://packagist.org/packages/shahghasiadil/laravel-api-versioning)
[![License](https://img.shields.io/packagist/l/shahghasiadil/laravel-api-versioning.svg?style=flat-square)](https://packagist.org/packages/shahghasiadil/laravel-api-versioning)

Attribute-based API versioning for Laravel, with version detection, deprecation metadata, versioned resources, and route inspection commands.

## Requirements

- PHP 8.2+
- Laravel 10, 11, 12 or 13

## Installation

You can install the package via composer:

```bash
composer require shahghasiadil/laravel-api-versioning
```

Publish the config file:

```bash
php artisan vendor:publish --provider="ShahGhasiAdil\LaravelApiVersioning\ApiVersioningServiceProvider" --tag="config"
```

## Quick Start

### 1. Configure supported versions

Edit `config/api-versioning.php`:

```php
return [
    'default_version' => '2.0',
    'supported_versions' => ['1.0', '1.1', '2.0', '2.1'],

    'detection_methods' => [
        'header' => ['enabled' => true, 'header_name' => 'X-API-Version'],
        'query' => ['enabled' => true, 'parameter_name' => 'api-version'],
        'path' => ['enabled' => true, 'prefix' => 'api/v'],
        'media_type' => ['enabled' => false, 'format' => 'application/vnd.api+json;version=%s'],
    ],
];
```

### 2. Apply middleware to API routes

`api.version` middleware alias is registered by the package.

```php
use Illuminate\Support\Facades\Route;

Route::middleware('api.version')->group(function () {
    Route::apiResource('users', UserController::class);
});
```

### 3. Add version attributes to your controller

```php
use ShahGhasiAdil\LaravelApiVersioning\Attributes\ApiVersion;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\Deprecated;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\MapToApiVersion;
use ShahGhasiAdil\LaravelApiVersioning\Traits\HasApiVersionAttributes;

#[ApiVersion(['2.0', '2.1'])]
class UserController extends Controller
{
    use HasApiVersionAttributes;

    public function index(): JsonResponse
    {
        return response()->json([
            'version' => $this->getCurrentApiVersion(),
            'deprecated' => $this->isVersionDeprecated(),
        ]);
    }

    #[MapToApiVersion(['2.1'])]
    public function store(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Created']);
    }

    #[MapToApiVersion(['2.0'])]
    #[Deprecated(message: 'Use store() instead', replacedBy: '2.1', sunsetDate: '2026-12-31')]
    public function create(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Deprecated endpoint']);
    }
}
```

### 4. Call your API with a version

```bash
# Header
curl -H "X-API-Version: 2.0" https://api.example.com/api/users

# Query string
curl "https://api.example.com/api/users?api-version=2.0"

# Path-based
curl https://api.example.com/api/v2.0/users

# Media type
curl -H "Accept: application/vnd.api+json;version=2.0" https://api.example.com/api/users
```

## Features

- Controller and method level attributes (`ApiVersion`, `MapToApiVersion`)
- Per-version deprecation, and advertising versions implemented elsewhere (`AdvertiseApiVersions`)
- Version-neutral endpoints (`ApiVersionNeutral`)
- Deprecation metadata (`Deprecated` with message, sunset date, replacement)
- Multiple version detection methods (header, query, path, media type)
- Response headers for active/supported/deprecated versions
- Version-aware resources and resource collections
- Version inheritance and method mapping for resources
- RFC 7807 problem+json error responses for unsupported versions
- Built-in version comparison utilities
- Cache layer for attribute resolution
- Artisan commands for generation, inspection, config and health checks
- Testing base class with version request/assert helpers

## Attributes

### `ApiVersion`

Use on a controller or method.

```php
#[ApiVersion('2.0')]
#[ApiVersion(['1.0', '1.1', '2.0'])]
```

Stack multiple `#[ApiVersion(...)]` attributes to deprecate only *some* of the
versions a single controller/method serves, instead of the whole endpoint.
This is different from a class/method-level `#[Deprecated]` attribute, which
applies to every version the endpoint serves:

```php
#[ApiVersion('1.0', deprecated: true, sunset: '2026-06-30', replacedBy: '2.0')]
#[ApiVersion('2.0')]
class OrderController extends Controller
{
    // requests for 1.0 get the deprecation headers; requests for 2.0 don't.
}
```

Add a `#[Deprecated(message: '...')]` attribute alongside it to supply a
human-readable deprecation message for the deprecated version(s) — the
message applies only to versions marked `deprecated: true` on their own
`#[ApiVersion]`/`#[MapToApiVersion]` attribute when at least one such
attribute is used this way.

### `MapToApiVersion`

Use on a method to map it to specific versions. Accepts the same
`deprecated`, `sunset`, and `replacedBy` parameters as `#[ApiVersion]`.

```php
#[MapToApiVersion(['2.0', '2.1'])]

#[MapToApiVersion('1.0', deprecated: true, sunset: '2026-06-30', replacedBy: '2.0')]
```

### `ApiVersionNeutral`

Marks controller/method as version-neutral (available in all supported versions).

```php
use ShahGhasiAdil\LaravelApiVersioning\Attributes\ApiVersionNeutral;

#[ApiVersionNeutral]
class HealthController extends Controller
{
    // ...
}
```

### `Deprecated`

Add deprecation metadata to controller/method.

```php
#[Deprecated(
    message: 'Use v2 endpoint',
    sunsetDate: '2026-12-31',
    replacedBy: '2.0'
)]
```

### `AdvertiseApiVersions`

Declares that a version exists and is implemented *elsewhere* — another
service, another package, a gateway route — rather than by this
controller/method. Unlike `#[ApiVersion]`, an advertised version is never
resolved by the endpoint that declares it; it only appears in discovery
data (`api-supported-versions`, `X-API-Route-Versions`, `getAllVersionsForRoute()`,
the `api:versions` command) so clients and API explorers know it exists.

```php
#[ApiVersion('2.0')]
#[AdvertiseApiVersions('3.0')]           // implemented by a different service
class OrderController extends Controller
{
    // requests for 2.0 are handled here; requests for 3.0 still 400,
    // but every response's headers list 3.0 as a version this API has.
}
```

Accepts the same `deprecated`, `sunset`, and `replacedBy` parameters as
`#[ApiVersion]`, so an advertised version can also report as deprecated.

## Response Headers

When middleware is active, responses can include two sets of headers,
controlled independently via `config('api-versioning.reporting')`:

**Standard headers** (`reporting.standard_headers`, default `true`) — scoped
to the current endpoint, matching common REST API versioning conventions:

```http
api-supported-versions: 2.0, 2.1
api-deprecated-versions: 2.0
```

**Legacy headers** (`reporting.legacy_headers`, default `true`) — this
package's original headers, kept for backward compatibility:

```http
X-API-Version: 2.0
X-API-Supported-Versions: 1.0, 1.1, 2.0, 2.1
X-API-Route-Versions: 2.0, 2.1
X-API-Deprecated: true
X-API-Deprecation-Message: Use store() instead
X-API-Sunset: 2026-12-31
X-API-Replaced-By: 2.1
```

Note the difference in scope: `api-supported-versions`/`X-API-Route-Versions`
report what *this endpoint* supports, while `X-API-Supported-Versions`
reports your application's entire `supported_versions` config list.
`X-API-Version` is always emitted regardless of these settings.

### Sunset Policies (RFC 8594 / RFC 8288)

When `reporting.standard_headers` is enabled, a deprecated version with a
sunset date also gets a standards-compliant `Sunset` header — an HTTP-date,
per [RFC 8594](https://www.rfc-editor.org/rfc/rfc8594) — instead of the raw
string in the legacy `X-API-Sunset` header:

```http
Sunset: Tue, 30 Jun 2026 23:59:59 GMT
```

This works with no config: any version deprecated via
`#[Deprecated(sunsetDate: '...')]` or `#[ApiVersion(sunset: '...')]` gets it
for free. To also attach an [RFC 8288](https://www.rfc-editor.org/rfc/rfc8288)
`Link` header pointing to a migration guide, or to sunset a version without
touching a controller, declare a policy in config:

```php
'sunset_policies' => [
    '1.0' => [
        'date' => '2026-06-30',
        'link' => 'https://docs.example.com/migrating-to-v2',
        'link_type' => 'text/html',
        'link_title' => 'Migration guide',
    ],
],
```

```http
Sunset: Tue, 30 Jun 2026 00:00:00 GMT
Link: <https://docs.example.com/migrating-to-v2>; rel="sunset"; type="text/html"; title="Migration guide"
```

A config policy takes precedence over an attribute-resolved sunset date for
the same version.

## Versioned Resources

### `VersionedJsonResource`

Extend `VersionedJsonResource` and implement `toArrayDefault()`.
Then optionally add version-specific methods (`toArrayV1`, `toArrayV11`, `toArrayV2`, `toArrayV21`) or custom mappings via config.

```php
use Illuminate\Http\Request;
use ShahGhasiAdil\LaravelApiVersioning\Http\Resources\VersionedJsonResource;

class UserResource extends VersionedJsonResource
{
    protected function toArrayV1(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name];
    }

    protected function toArrayV2(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
        ];
    }

    protected function toArrayDefault(Request $request): array
    {
        return $this->toArrayV2($request);
    }
}
```

### `VersionedResourceCollection`

Extend `VersionedResourceCollection` for version-aware list payloads.

```php
use Illuminate\Http\Request;
use ShahGhasiAdil\LaravelApiVersioning\Http\Resources\VersionedResourceCollection;

class UserCollection extends VersionedResourceCollection
{
    protected function toArrayV1(Request $request): array
    {
        return ['data' => $this->collection];
    }

    protected function toArrayV2(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => ['count' => $this->collection->count()],
        ];
    }

    protected function toArrayDefault(Request $request): array
    {
        return $this->toArrayV2($request);
    }
}
```

## Version Comparison Helpers

In controllers/resources using `HasApiVersionAttributes`:

- `getCurrentApiVersion()`
- `isVersionDeprecated()`
- `getDeprecationMessage()`
- `getSunsetDate()`
- `getReplacedByVersion()`
- `isVersionGreaterThan()`
- `isVersionGreaterThanOrEqual()`
- `isVersionLessThan()`
- `isVersionLessThanOrEqual()`
- `isVersionBetween()`

Direct service usage:

```php
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionComparator;

$comparator = app(VersionComparator::class);
$comparator->isGreaterThan('2.0', '1.0');
$comparator->satisfies('2.1', '^2.0');
```

## Error Format (RFC 7807)

Version problems return `application/problem+json` with a machine-readable
`code` alongside the human-readable `title`:

```json
{
  "type": "https://tools.ietf.org/html/rfc7231#section-6.5.1",
  "title": "Unsupported API Version",
  "status": 400,
  "detail": "API version '3.0' is not supported for this endpoint.",
  "code": "UnsupportedApiVersion",
  "requested_version": "3.0",
  "supported_versions": ["1.0", "1.1", "2.0", "2.1"],
  "endpoint_versions": ["2.0", "2.1"]
}
```

Optional `documentation` is included when `api-versioning.documentation.base_url` is set.

### Detection strictness

By default, version detection is lenient: an unspecified version silently
falls back to `default_version`, conflicting detection methods silently use
whichever is listed first in `detection_methods`, and any non-empty string
is treated as a candidate version. Three independent, opt-in flags under
`api-versioning.version_detection` tighten this and surface distinct
problem types instead:

```php
'version_detection' => [
    'require_explicit_version' => false,      // true => 400 "Unspecified API Version" instead of assuming default_version
    'reject_conflicting_versions' => false,   // true => 400 "Ambiguous API Version" instead of first-match-wins
    'format_validation' => [
        'enabled' => false,                   // true => 400 "Invalid API Version" for values that don't match 'pattern'
        'pattern' => '/^\d+(?:\.\d+)*(?:-[a-zA-Z0-9]+)?$/',
    ],
],
```

Each has its own `code`: `ApiVersionUnspecified`, `AmbiguousApiVersion`
(with a `conflicts` map of detection method → detected value), and
`InvalidApiVersion`.

## Artisan Commands

Generate controller stub with attributes:

```bash
php artisan make:versioned-controller UserController --api-version=2.0
php artisan make:versioned-controller V1UserController --api-version=1.0 --deprecated --sunset=2026-12-31 --replaced-by=2.0
```

Inspect routes and versions:

```bash
php artisan api:versions
php artisan api:versions --route=users
php artisan api:versions --api-version=2.0
php artisan api:versions --deprecated
php artisan api:versions --compact
php artisan api:versions --json
```

Check configuration health:

```bash
php artisan api:version:health
```

Show config overview:

```bash
php artisan api:version-config --show
php artisan api:version-config --add-version=2.2 --method=toArrayV22
```

Clear attribute cache:

```bash
php artisan api:cache:clear
```

## Testing Helpers

Use `ShahGhasiAdil\LaravelApiVersioning\Testing\ApiVersionTestCase` in package/app tests.

Available helpers include:

- Requests: `getWithVersion`, `getWithVersionQuery`, `postWithVersion`, `putWithVersion`, `deleteWithVersion`
- Assertions: `assertApiVersion`, `assertApiVersionDeprecated`, `assertApiVersionNotDeprecated`, `assertSupportedVersions`, `assertRouteVersions`, `assertDeprecationMessage`, `assertReplacedBy`

## Configuration Reference

Main file: `config/api-versioning.php`

```php
return [
    'default_version' => '1.0',

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

    // See "Detection strictness" above. All disabled by default.
    'version_detection' => [
        'require_explicit_version' => false,
        'reject_conflicting_versions' => false,
        'format_validation' => [
            'enabled' => false,
            'pattern' => '/^\d+(?:\.\d+)*(?:-[a-zA-Z0-9]+)?$/',
        ],
    ],

    // 'neutral' (default) or 'reject'. See "Closure Routes" below.
    'closure_routes' => 'neutral',

    'supported_versions' => ['1.0', '1.1', '2.0', '2.1'],

    'version_method_mapping' => [
        '1.0' => 'toArrayV1',
        '1.1' => 'toArrayV11',
        '2.0' => 'toArrayV2',
        '2.1' => 'toArrayV21',
    ],

    'version_inheritance' => [
        '1.1' => '1.0',
        '2.1' => '2.0',
    ],

    'default_method' => 'toArrayDefault',

    // See "Response Headers" above. Both default to true.
    'reporting' => [
        'standard_headers' => true,
        'legacy_headers' => true,
    ],

    'documentation' => [
        'base_url' => env('API_DOCUMENTATION_URL'),
    ],

    'cache' => [
        'enabled' => env('API_VERSIONING_CACHE_ENABLED', true),
        'ttl' => env('API_VERSIONING_CACHE_TTL', 3600),
    ],
];
```

### Closure Routes

Routes defined with a `Closure` have no controller class to carry
`#[ApiVersion]`/`#[Deprecated]` attributes on. By default (`'closure_routes'
=> 'neutral'`) they behave like `#[ApiVersionNeutral]` and respond to every
version in `supported_versions`:

```php
Route::middleware('api.version')->get('/api/ping', fn () => response()->json(['pong' => true]));
```

Set `'closure_routes' => 'reject'` to restore this package's original
behavior of returning a 400 "Unsupported API Version" for every request to
a closure route.

Environment variables:

```env
API_DOCUMENTATION_URL=https://docs.example.com/api
API_VERSIONING_CACHE_ENABLED=true
API_VERSIONING_CACHE_TTL=3600
```

## Development

Run tests:

```bash
composer test
```

Run static analysis:

```bash
composer analyse
```

Format code:

```bash
composer format
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Security Vulnerabilities

If you discover any security-related issues, please email adil.shahghasi@gmail.com.

## Credits

- [Shahghasi Adil](https://github.com/shahghasiadil)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [LICENSE.md](LICENSE.md) for details.
