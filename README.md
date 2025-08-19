# Laravel API Versioning

[![Latest Version on Packagist](https://img.shields.io/packagist/v/shahghasiadil/laravel-api-versioning.svg?style=flat-square)](https://packagist.org/packages/shahghasiadil/laravel-api-versioning)
[![Total Downloads](https://img.shields.io/packagist/dt/shahghasiadil/laravel-api-versioning.svg?style=flat-square)](https://packagist.org/packages/shahghasiadil/laravel-api-versioning)
[![License](https://img.shields.io/packagist/l/shahghasiadil/laravel-api-versioning.svg?style=flat-square)](https://packagist.org/packages/shahghasiadil/laravel-api-versioning)

A powerful and elegant attribute-based API versioning solution for Laravel applications with strict type safety and comprehensive deprecation management.

## Features

- 🎯 **Attribute-based versioning** - Use PHP 8+ attributes to define API versions
- 🛡️ **Type-safe** - Full type annotations and strict type checking
- 🔄 **Multiple detection methods** - Header, query parameter, path, and media type detection
- 📦 **Resource versioning** - Smart version-aware JSON resources
- 🚫 **Deprecation support** - Built-in deprecation warnings and sunset dates
- 🔗 **Version inheritance** - Fallback chains for backward compatibility
- 🧪 **Testing utilities** - Comprehensive test helpers
- 📊 **Route inspection** - Commands to analyze your API versioning
- ⚡ **Performance optimized** - Minimal overhead with efficient resolution

## Requirements

- PHP 8.2+
- Laravel 12.0+

## Installation

Install the package via Composer:

```bash
composer require shahghasiadil/laravel-api-versioning
```

Publish the configuration file:

```bash
php artisan vendor:publish --provider="ShahGhasiAdil\LaravelApiVersioning\ApiVersioningServiceProvider" --tag="config"
```

## Quick Start

### 1. Configure Your API Versions

Update `config/api-versioning.php`:

```php
return [
    'default_version' => '2.0',
    'supported_versions' => ['1.0', '1.1', '2.0', '2.1'],
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
    ],
    'version_method_mapping' => [
        '1.0' => 'toArrayV1',
        '1.1' => 'toArrayV11',
        '2.0' => 'toArrayV2',
        '2.1' => 'toArrayV21',
    ],
    'version_inheritance' => [
        '1.1' => '1.0',  // v1.1 falls back to v1.0
        '2.1' => '2.0',  // v2.1 falls back to v2.0
    ],
];
```

### 2. Add Middleware to Routes

```php
// routes/api.php
Route::middleware(['api', 'api.version'])->group(function () {
    Route::apiResource('users', UserController::class);
});
```

### 3. Create Versioned Controllers

Use the built-in command to generate versioned controllers:

```bash
# Create a basic versioned controller
php artisan make:versioned-controller UserController --api-version=2.0

# Create a deprecated controller
php artisan make:versioned-controller V1UserController --api-version=1.0 --deprecated --sunset=2025-12-31 --replaced-by=2.0
```

Or create manually with attributes:

```php
<?php

use ShahGhasiAdil\LaravelApiVersioning\Attributes\ApiVersion;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\Deprecated;
use ShahGhasiAdil\LaravelApiVersioning\Traits\HasApiVersionAttributes;

#[ApiVersion(['2.0', '2.1'])]
class UserController extends Controller
{
    use HasApiVersionAttributes;

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => User::all(),
            'version' => $this->getCurrentApiVersion(),
            'deprecated' => $this->isVersionDeprecated(),
        ]);
    }
}
```

### 4. Create Versioned Resources

```php
<?php

use ShahGhasiAdil\LaravelApiVersioning\Http\Resources\VersionedJsonResource;

class UserResource extends VersionedJsonResource
{
    protected function toArrayV1(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }

    protected function toArrayV2(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->created_at->toISOString(),
        ];
    }

    protected function toArrayDefault(Request $request): array
    {
        return $this->toArrayV2($request);
    }
}
```

## Usage

### API Version Detection

The package supports multiple ways to specify API versions:

#### Header-based (Recommended)
```bash
curl -H "X-API-Version: 2.0" https://api.example.com/users
```

#### Query Parameter
```bash
curl https://api.example.com/users?api-version=2.0
```

#### Path-based
```bash
curl https://api.example.com/api/v2.0/users
```

#### Media Type
```bash
curl -H "Accept: application/vnd.api+json;version=2.0" https://api.example.com/users
```

### Attributes Reference

#### `#[ApiVersion]` - Define Supported Versions

```php
// Single version
#[ApiVersion('2.0')]
class UserController extends Controller {}

// Multiple versions
#[ApiVersion(['1.0', '1.1', '2.0'])]
class UserController extends Controller {}

// Method-specific versions
class UserController extends Controller
{
    #[ApiVersion('2.0')]
    public function store() {}
}
```

#### `#[ApiVersionNeutral]` - Version-Independent Endpoints

```php
#[ApiVersionNeutral]
class HealthController extends Controller
{
    public function check() {} // Works with any version
}
```

#### `#[Deprecated]` - Mark as Deprecated

```php
#[ApiVersion('1.0')]
#[Deprecated(
    message: 'This endpoint is deprecated. Use v2.0 instead.',
    sunsetDate: '2025-12-31',
    replacedBy: '2.0'
)]
class V1UserController extends Controller {}
```

#### `#[MapToApiVersion]` - Method-Specific Mapping

```php
class UserController extends Controller
{
    #[MapToApiVersion(['1.1', '2.0'])]
    public function show() {} // Only available in v1.1 and v2.0
}
```

### Resource Versioning

#### Method-based Versioning

```php
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
            'profile' => ['avatar' => $this->avatar_url],
        ];
    }

    protected function toArrayDefault(Request $request): array
    {
        return $this->toArrayV2($request);
    }
}
```

#### Dynamic Configuration Versioning

```php
class UserResource extends VersionedJsonResource
{
    protected array $versionConfigs = [
        '1.0' => ['id', 'name'],
        '2.0' => ['id', 'name', 'email', 'profile'],
    ];

    protected function toArrayDefault(Request $request): array
    {
        $version = $this->getCurrentApiVersion();
        $config = $this->versionConfigs[$version] ?? $this->versionConfigs['2.0'];
        
        return $this->only($config);
    }
}
```

### Helper Trait Methods

The `HasApiVersionAttributes` trait provides useful methods:

```php
class UserController extends Controller
{
    use HasApiVersionAttributes;

    public function index()
    {
        $version = $this->getCurrentApiVersion();          // '2.0'
        $isDeprecated = $this->isVersionDeprecated();      // false
        $isNeutral = $this->isVersionNeutral();            // false
        $message = $this->getDeprecationMessage();         // null
        $sunset = $this->getSunsetDate();                  // null
        $replacedBy = $this->getReplacedByVersion();       // null
    }
}
```

## Commands

### Generate Versioned Controllers

```bash
# Basic controller
php artisan make:versioned-controller UserController --api-version=2.0

# Deprecated controller
php artisan make:versioned-controller V1UserController \
    --api-version=1.0 \
    --deprecated \
    --sunset=2025-12-31 \
    --replaced-by=2.0
```

### Inspect API Versions

```bash
# Show all routes with version info
php artisan api:versions

# Filter by route pattern
php artisan api:versions --route=users

# Show only deprecated endpoints
php artisan api:versions --deprecated

# Filter by specific version
php artisan api:versions --api-version=2.0
```

### Manage Configuration

```bash
# Show current configuration
php artisan api:version-config --show

# Add new version mapping (guidance only)
php artisan api:version-config --add-version=3.0 --method=toArrayV3
```

## Testing

The package includes comprehensive testing utilities:

```php
use ShahGhasiAdil\LaravelApiVersioning\Testing\ApiVersionTestCase;

class UserControllerTest extends ApiVersionTestCase
{
    public function test_user_endpoint_v1()
    {
        $response = $this->getWithVersion('/api/users', '1.0');
        
        $response->assertOk();
        $this->assertApiVersion($response, '1.0');
        $this->assertApiVersionNotDeprecated($response);
    }

    public function test_deprecated_endpoint()
    {
        $response = $this->getWithVersion('/api/v1/users', '1.0');
        
        $this->assertApiVersionDeprecated($response, '2025-12-31');
        $this->assertDeprecationMessage($response, 'Use v2.0 instead');
        $this->assertReplacedBy($response, '2.0');
    }

    public function test_unsupported_version()
    {
        $response = $this->getWithVersion('/api/users', '3.0');
        
        $response->assertStatus(400);
        $response->assertJson([
            'error' => 'Unsupported API Version',
            'requested_version' => '3.0',
        ]);
    }
}
```

### Available Test Methods

- `getWithVersion($uri, $version, $headers = [])`
- `getWithVersionQuery($uri, $version, $headers = [])`
- `postWithVersion($uri, $data, $version, $headers = [])`
- `putWithVersion($uri, $data, $version, $headers = [])`
- `deleteWithVersion($uri, $version, $headers = [])`
- `assertApiVersion($response, $expectedVersion)`
- `assertApiVersionDeprecated($response, $sunsetDate = null)`
- `assertApiVersionNotDeprecated($response)`
- `assertSupportedVersions($response, $versions)`
- `assertRouteVersions($response, $versions)`
- `assertDeprecationMessage($response, $message)`
- `assertReplacedBy($response, $version)`

## Response Headers

The middleware automatically adds helpful headers to API responses:

```http
X-API-Version: 2.0
X-API-Supported-Versions: 1.0, 1.1, 2.0, 2.1
X-API-Route-Versions: 2.0, 2.1
X-API-Deprecated: true
X-API-Deprecation-Message: This endpoint is deprecated
X-API-Sunset: 2025-12-31
X-API-Replaced-By: 2.0
```

## Error Handling

When an unsupported version is requested, the package returns a structured error response:

```json
{
    "error": "Unsupported API Version",
    "message": "API version '3.0' is not supported for this endpoint.",
    "requested_version": "3.0",
    "supported_versions": ["1.0", "1.1", "2.0", "2.1"],
    "endpoint_versions": ["2.0", "2.1"],
    "documentation": "https://docs.example.com/api"
}
```

## Advanced Examples

### Complex Controller with Multiple Versions

```php
<?php

use ShahGhasiAdil\LaravelApiVersioning\Attributes\{ApiVersion, Deprecated, MapToApiVersion};

#[ApiVersion(['1.0', '1.1', '2.0'])]
class UserController extends Controller
{
    use HasApiVersionAttributes;

    public function index(): JsonResponse
    {
        $users = User::all();
        return UserResource::collection($users);
    }

    #[MapToApiVersion(['2.0'])]
    public function store(Request $request): JsonResponse
    {
        // Only available in v2.0
        $user = User::create($request->validated());
        return new UserResource($user);
    }

    #[Deprecated(message: 'Use POST /users instead', replacedBy: '2.0')]
    #[MapToApiVersion(['1.0', '1.1'])]
    public function create(Request $request): JsonResponse
    {
        // Deprecated method for v1.x
        return $this->store($request);
    }
}
```

### Dynamic Resource Configuration

```php
class UserResource extends VersionedJsonResource
{
    protected array $versionConfigs = [
        '1.0' => ['id', 'name'],
        '1.1' => ['id', 'name', 'email'],
        '2.0' => ['id', 'name', 'email', 'created_at', 'profile'],
        '2.1' => ['id', 'name', 'email', 'created_at', 'updated_at', 'profile', 'preferences', 'stats'],
    ];

    protected function toArrayDefault(Request $request): array
    {
        $version = $this->getCurrentApiVersion();
        $config = $this->versionConfigs[$version] ?? $this->versionConfigs['2.1'];

        $data = [];
        foreach ($config as $field) {
            $data[$field] = $this->getFieldValue($field);
        }

        return $data;
    }

    private function getFieldValue(string $field): mixed
    {
        return match($field) {
            'profile' => ['avatar' => $this->avatar_url, 'bio' => $this->bio],
            'preferences' => ['theme' => $this->theme ?? 'light'],
            'stats' => ['login_count' => $this->login_count ?? 0],
            default => $this->$field,
        };
    }
}
```

### Version-Neutral Endpoints

```php
#[ApiVersionNeutral]
class HealthController extends Controller
{
    use HasApiVersionAttributes;

    public function check(): JsonResponse
    {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'version' => $this->getCurrentApiVersion(),
        ]);
    }
}
```

## Configuration Options

### Detection Methods

Configure how versions are detected from requests:

```php
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
        'prefix' => 'api/v',  // Matches /api/v1.0/users
    ],
    'media_type' => [
        'enabled' => false,
        'format' => 'application/vnd.api+json;version=%s',
    ],
],
```

### Version Inheritance

Set up fallback chains for backward compatibility:

```php
'version_inheritance' => [
    '1.1' => '1.0',  // v1.1 falls back to v1.0 methods
    '1.2' => '1.1',  // v1.2 falls back to v1.1, then v1.0
    '2.1' => '2.0',  // v2.1 falls back to v2.0 methods
],
```

### Method Mapping

Map versions to specific resource methods:

```php
'version_method_mapping' => [
    '1.0' => 'toArrayV1',
    '1.1' => 'toArrayV11',
    '2.0' => 'toArrayV2',
    '2.1' => 'toArrayV21',
],
```

## Artisan Commands

### `php artisan make:versioned-controller`

Generate a new versioned controller with proper attributes:

```bash
# Basic usage
php artisan make:versioned-controller ProductController --api-version=2.0

# With deprecation
php artisan make:versioned-controller V1ProductController \
    --api-version=1.0 \
    --deprecated \
    --sunset=2025-06-30 \
    --replaced-by=2.0
```

**Options:**
- `--api-version=X.X` - Specify the API version (default: 1.0)
- `--deprecated` - Mark the controller as deprecated
- `--sunset=DATE` - Set sunset date for deprecated controller
- `--replaced-by=VERSION` - Specify replacement version

### `php artisan api:versions`

Display comprehensive versioning information for all API routes:

```bash
# Show all API routes
php artisan api:versions

# Filter by route pattern
php artisan api:versions --route=users

# Show only deprecated endpoints
php artisan api:versions --deprecated

# Filter by specific version
php artisan api:versions --api-version=2.0
```

**Options:**
- `--route=PATTERN` - Filter routes by URI pattern
- `--api-version=X.X` - Show only routes supporting specific version
- `--deprecated` - Show only deprecated endpoints

### `php artisan api:version-config`

Manage version configuration:

```bash
# Show current configuration
php artisan api:version-config --show

# Get guidance for adding new versions
php artisan api:version-config --add-version=3.0 --method=toArrayV3
```

**Options:**
- `--show` - Display current version configuration
- `--add-version=X.X` - Get instructions for adding new version
- `--method=NAME` - Specify method name for new version

## Best Practices

### 1. Version Naming Convention
Use semantic versioning (e.g., `1.0`, `1.1`, `2.0`) for clarity and consistency.

### 2. Backward Compatibility
Leverage version inheritance to maintain backward compatibility:

```php
'version_inheritance' => [
    '1.1' => '1.0',  // v1.1 can fall back to v1.0 methods
],
```

### 3. Deprecation Strategy
Always provide clear deprecation information:

```php
#[Deprecated(
    message: 'This endpoint will be removed in v3.0. Use /api/v2/users instead.',
    sunsetDate: '2025-12-31',
    replacedBy: '2.0'
)]
```

### 4. Resource Organization
Keep version-specific logic organized in your resources:

```php
// Good: Clear method names
protected function toArrayV1(Request $request): array {}
protected function toArrayV2(Request $request): array {}

// Good: Inheritance for similar versions
protected function toArrayV11(Request $request): array
{
    return array_merge($this->toArrayV1($request), [
        'email' => $this->email, // Added field
    ]);
}
```

### 5. Testing Strategy
Test all supported versions thoroughly:

```php
public function test_all_supported_versions()
{
    foreach (['1.0', '1.1', '2.0'] as $version) {
        $response = $this->getWithVersion('/api/users', $version);
        $response->assertOk();
        $this->assertApiVersion($response, $version);
    }
}
```

## Error Responses

### Unsupported Version

When a client requests an unsupported version:

**Request:**
```bash
curl -H "X-API-Version: 3.0" https://api.example.com/users
```

**Response:**
```json
{
    "error": "Unsupported API Version",
    "message": "API version '3.0' is not supported for this endpoint.",
    "requested_version": "3.0",
    "supported_versions": ["1.0", "1.1", "2.0", "2.1"],
    "endpoint_versions": ["2.0", "2.1"],
    "documentation": "https://docs.example.com/api"
}
```

## Migration Guide

### From Other Versioning Solutions

1. **Install the package** and publish the configuration
2. **Update your routes** to use the `api.version` middleware
3. **Add attributes** to your existing controllers
4. **Migrate resources** to extend `VersionedJsonResource`
5. **Test thoroughly** using the provided test utilities

### Adding New Versions

1. **Update configuration:**
   ```php
   'supported_versions' => ['1.0', '1.1', '2.0', '2.1', '3.0'],
   'version_method_mapping' => [
       // ... existing mappings
       '3.0' => 'toArrayV3',
   ],
   ```

2. **Add version to controllers:**
   ```php
   #[ApiVersion(['2.0', '2.1', '3.0'])]
   class UserController extends Controller {}
   ```

3. **Implement resource methods:**
   ```php
   protected function toArrayV3(Request $request): array
   {
       // New version implementation
   }
   ```

4. **Test the new version** thoroughly

## Contributing

Contributions are welcome! Please ensure you:

1. Follow PSR-12 coding standards
2. Add tests for new features
3. Update documentation for changes
4. Run the test suite: `composer test`
5. Run static analysis: `composer analyse`
6. Format code: `composer format`

## Security

If you discover any security-related issues, please email [adil.shahghasi@gmail.com](mailto:adil.shahghasi@gmail.com) instead of using the issue tracker.

## Credits

- [Shahghasi Adil](https://github.com/shahghasiadil)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

---

**Made with ❤️ for the Laravel community**
