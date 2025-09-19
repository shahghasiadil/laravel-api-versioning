# Laravel API Versioning

[![Latest Version on Packagist](https://img.shields.io/packagist/v/shahghasiadil/laravel-api-versioning.svg?style=flat-square)](https://packagist.org/packages/shahghasiadil/laravel-api-versioning)
[![Total Downloads](https://img.shields.io/packagist/dt/shahghasiadil/laravel-api-versioning.svg?style=flat-square)](https://packagist.org/packages/shahghasiadil/laravel-api-versioning)
[![License](https://img.shields.io/packagist/l/shahghasiadil/laravel-api-versioning.svg?style=flat-square)](https://packagist.org/packages/shahghasiadil/laravel-api-versioning)

A powerful and elegant attribute-based API versioning solution for Laravel applications with strict type safety and comprehensive deprecation management.

## ✨ Features

- 🎯 **Attribute-based versioning** - Use PHP 8+ attributes to define API versions
- 🛡️ **Type-safe** - Full type annotations and strict type checking
- 🔄 **Multiple detection methods** - Header, query parameter, path, and media type detection
- 📦 **Resource versioning** - Smart version-aware JSON resources
- 🚫 **Deprecation support** - Built-in deprecation warnings and sunset dates
- 🔗 **Version inheritance** - Fallback chains for backward compatibility
- 🧪 **Testing utilities** - Comprehensive test helpers with Pest PHP
- 📊 **Artisan commands** - Route inspection and controller generation
- ⚡ **Performance optimized** - Minimal overhead with efficient resolution

## 📋 Requirements

- PHP 8.2+
- Laravel 10.0|11.0|12.0+

## 🚀 Installation

```bash
composer require shahghasiadil/laravel-api-versioning
```

```bash
php artisan vendor:publish --provider="ShahGhasiAdil\LaravelApiVersioning\ApiVersioningServiceProvider" --tag="config"
```

## ⚡ Quick Start

### 1. Configure API Versions

Edit `config/api-versioning.php`:

```php
return [
    'default_version' => '2.0',
    'supported_versions' => ['1.0', '1.1', '2.0', '2.1'],
    'detection_methods' => [
        'header' => ['enabled' => true, 'header_name' => 'X-API-Version'],
        'query' => ['enabled' => true, 'parameter_name' => 'api-version'],
        'path' => ['enabled' => true, 'prefix' => 'api/v'],
    ],
    'version_method_mapping' => [
        '1.0' => 'toArrayV1', '2.0' => 'toArrayV2', '2.1' => 'toArrayV21',
    ],
    'version_inheritance' => ['1.1' => '1.0', '2.1' => '2.0'],
];
```

### 2. Apply Middleware

```php
// routes/api.php - Route Groups (Recommended)
Route::middleware('api.version')->group(function () {
    Route::apiResource('users', UserController::class);
});

// Direct Middleware Class
use ShahGhasiAdil\LaravelApiVersioning\Middleware\AttributeApiVersionMiddleware;
Route::middleware(AttributeApiVersionMiddleware::class)->group(function () {
    Route::get('users', [UserController::class, 'index']);
});

// Individual Routes
Route::get('users/{user}', [UserController::class, 'show'])->middleware('api.version');

// Global Middleware (Laravel 11+)
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->api(append: [AttributeApiVersionMiddleware::class]);
})
```

### 3. Create Controllers with Attributes

```bash
php artisan make:versioned-controller UserController --api-version=2.0
```

```php
use ShahGhasiAdil\LaravelApiVersioning\Attributes\{ApiVersion, Deprecated, MapToApiVersion};
use ShahGhasiAdil\LaravelApiVersioning\Traits\HasApiVersionAttributes;

// Controller-level versioning
#[ApiVersion(['2.0', '2.1'])]
class UserController extends Controller
{
    use HasApiVersionAttributes;

    // Available in all controller versions (2.0, 2.1)
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => User::all(),
            'version' => $this->getCurrentApiVersion(),
        ]);
    }

    // Method-specific versioning
    #[MapToApiVersion(['2.1'])]
    public function store(Request $request): JsonResponse
    {
        // Only available in v2.1
        return response()->json(['message' => 'User created']);
    }

    // Deprecated method
    #[Deprecated(message: 'Use store() instead', replacedBy: '2.1')]
    #[MapToApiVersion(['2.0'])]
    public function create(Request $request): JsonResponse
    {
        // Only available in v2.0, deprecated
        return $this->store($request);
    }
}

// Version-neutral endpoints (work with any version)
#[ApiVersionNeutral]
class HealthController extends Controller
{
    use HasApiVersionAttributes;

    public function check(): JsonResponse
    {
        return response()->json(['status' => 'healthy']);
    }
}
```

### 4. Create Resources

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
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
```

## 🔧 Usage

### Version Detection

```bash
# Header (Recommended)
curl -H "X-API-Version: 2.0" https://api.example.com/users

# Query Parameter
curl https://api.example.com/users?api-version=2.0

# Path
curl https://api.example.com/api/v2.0/users

# Media Type
curl -H "Accept: application/vnd.api+json;version=2.0" https://api.example.com/users
```

### Attributes Usage

#### Controller-Level Attributes
```php
// Single version support
#[ApiVersion('2.0')]
class V2UserController extends Controller {}

// Multiple versions support
#[ApiVersion(['1.0', '1.1', '2.0'])]
class UserController extends Controller {}

// Version-neutral (works with any API version)
#[ApiVersionNeutral]
class HealthController extends Controller {}

// Deprecated controller
#[ApiVersion('1.0')]
#[Deprecated(
    message: 'This controller is deprecated. Use v2.0 UserController instead.',
    sunsetDate: '2025-12-31',
    replacedBy: '2.0'
)]
class V1UserController extends Controller {}
```

#### Method-Level Attributes
```php
#[ApiVersion(['1.0', '2.0', '2.1'])]
class UserController extends Controller
{
    // Available in all controller versions
    public function index() {}

    // Only available in specific versions
    #[MapToApiVersion(['2.0', '2.1'])]
    public function store() {}

    // Version-specific method with deprecation
    #[MapToApiVersion(['1.0'])]
    #[Deprecated(message: 'Use store() method instead', replacedBy: '2.0')]
    public function create() {}

    // Advanced features only in latest version
    #[MapToApiVersion(['2.1'])]
    public function bulkUpdate() {}
}
```

#### Combining Attributes
```php
#[ApiVersion(['1.0', '2.0'])]
class PostController extends Controller
{
    // Method overrides controller version restriction
    #[MapToApiVersion(['2.0'])]
    #[Deprecated(sunsetDate: '2025-06-30', replacedBy: '2.1')]
    public function legacyUpdate() {}
}
```

### Helper Methods

```php
class UserController extends Controller
{
    use HasApiVersionAttributes;

    public function index()
    {
        $version = $this->getCurrentApiVersion();      // '2.0'
        $isDeprecated = $this->isVersionDeprecated();  // false
        $message = $this->getDeprecationMessage();     // null
        $sunset = $this->getSunsetDate();              // null
    }
}
```

## 🛠️ Artisan Commands

```bash
# Generate controllers
php artisan make:versioned-controller UserController --api-version=2.0
php artisan make:versioned-controller V1UserController --api-version=1.0 --deprecated

# Inspect API versions
php artisan api:versions                    # All routes
php artisan api:versions --route=users     # Filter by route
php artisan api:versions --deprecated      # Deprecated only

# Configuration management
php artisan api:version-config --show      # Show config
```

## 🧪 Testing (Pest PHP)

```php
test('user endpoint versions', function () {
    $response = getWithVersion('/api/users', '2.0');
    $response->assertOk();
    assertApiVersion($response, '2.0');
});

test('deprecated endpoints', function () {
    $response = getWithVersion('/api/users', '1.0');
    assertApiVersionDeprecated($response);
    assertReplacedBy($response, '2.0');
});
```

### Test Helpers
- `getWithVersion()`, `postWithVersion()`, `putWithVersion()`, `deleteWithVersion()`
- `assertApiVersion()`, `assertApiVersionDeprecated()`, `assertReplacedBy()`

```bash
composer test                    # Run all tests
composer test-coverage           # With coverage
./vendor/bin/pest --filter="v1"  # Specific tests
```

## 📋 Response Headers

```http
X-API-Version: 2.0
X-API-Supported-Versions: 1.0, 1.1, 2.0, 2.1
X-API-Route-Versions: 2.0, 2.1
X-API-Deprecated: true
X-API-Sunset: 2025-12-31
X-API-Replaced-By: 2.0
```

## ⚠️ Error Handling

```json
{
    "error": "Unsupported API Version",
    "message": "API version '3.0' is not supported for this endpoint.",
    "requested_version": "3.0",
    "supported_versions": ["1.0", "1.1", "2.0", "2.1"],
    "endpoint_versions": ["2.0", "2.1"]
}
```

## ⚙️ Configuration

### Detection Methods
```php
'detection_methods' => [
    'header' => ['enabled' => true, 'header_name' => 'X-API-Version'],
    'query' => ['enabled' => true, 'parameter_name' => 'api-version'],
    'path' => ['enabled' => true, 'prefix' => 'api/v'],
    'media_type' => ['enabled' => false],
],
```

### Version Inheritance & Mapping
```php
'version_inheritance' => [
    '1.1' => '1.0',  // v1.1 falls back to v1.0
    '2.1' => '2.0',  // v2.1 falls back to v2.0
],
'version_method_mapping' => [
    '1.0' => 'toArrayV1', '2.0' => 'toArrayV2', '2.1' => 'toArrayV21',
],
```

## 📚 Best Practices

- Use semantic versioning: `1.0`, `1.1`, `2.0`
- Leverage version inheritance for backward compatibility
- Always provide clear deprecation information with sunset dates
- Test all supported versions thoroughly
- Keep version-specific logic organized in resources

## 🔄 Migration Guide

1. Install package and publish configuration
2. Apply `api.version` middleware to routes
3. Add attributes to controllers
4. Extend resources from `VersionedJsonResource`
5. Add comprehensive tests

## 🤝 Contributing

1. Follow PSR-12 coding standards
2. Add Pest tests for new features
3. Run: `composer test`, `composer analyse`, `composer format`

## 📄 License

MIT License. See [LICENSE.md](LICENSE.md) for details.

## 👨‍💻 Credits

- [Shahghasi Adil](https://github.com/shahghasiadil)
- [All Contributors](../../contributors)

**Made with ❤️ for the Laravel community**
