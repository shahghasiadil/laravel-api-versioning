# Laravel API Versioning

[![Latest Version on Packagist](https://img.shields.io/packagist/v/shahghasiadil/laravel-api-versioning.svg?style=flat-square)](https://packagist.org/packages/shahghasiadil/laravel-api-versioning)
[![Total Downloads](https://img.shields.io/packagist/dt/shahghasiadil/laravel-api-versioning.svg?style=flat-square)](https://packagist.org/packages/shahghasiadil/laravel-api-versioning)

Attribute-based API versioning for Laravel. This package provides a clean, declarative way to manage API versions using PHP 8+ attributes.

## Features

✨ **Attribute-based versioning** - Use PHP 8 attributes to declare API versions  
🎯 **Multiple detection methods** - Headers, query parameters, path segments, or media types  
🔄 **Version inheritance** - Inherit transformations from previous versions  
⚠️ **Deprecation support** - Mark versions as deprecated with sunset dates  
🎭 **Version-neutral endpoints** - Endpoints that work across all versions  
🔧 **Flexible resource transformation** - Dynamic response transformation per version  
📊 **Command-line tools** - Artisan commands for version management  
🧪 **Testing utilities** - Built-in test helpers for API versioning  

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

### 1. Apply Middleware

Add the middleware to your API routes:

```php
// routes/api.php
Route::middleware(['api', 'api.version'])->group(function () {
    Route::get('users', [UserController::class, 'index']);
    Route::get('users/{id}', [UserController::class, 'show']);
});
```

### 2. Create Versioned Controllers

```php
<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\ApiVersion;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\Deprecated;
use ShahGhasiAdil\LaravelApiVersioning\Traits\HasApiVersionAttributes;

#[ApiVersion(['1.0', '1.1'])]
#[Deprecated(
    message: 'This version is deprecated. Please migrate to v2.0',
    sunsetDate: '2025-12-31',
    replacedBy: '2.0'
)]
class V1UserController extends Controller
{
    use HasApiVersionAttributes;

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => User::select('id', 'name')->get(),
            'version' => $this->getCurrentApiVersion(),
            'deprecated' => $this->isVersionDeprecated(),
        ]);
    }
}
```

```php
#[ApiVersion(['2.0', '2.1'])]
class V2UserController extends Controller
{
    use HasApiVersionAttributes;

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => User::with('profile')->get(),
            'version' => $this->getCurrentApiVersion(),
            'meta' => ['total' => User::count()],
        ]);
    }
}
```

### 3. Create Versioned Resources

```php
<?php

namespace App\Http\Resources;

use ShahGhasiAdil\LaravelApiVersioning\Http\Resources\VersionedJsonResource;

class UserResource extends VersionedJsonResource
{
    protected function toArrayV1($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }

    protected function toArrayV11($request): array
    {
        return array_merge($this->toArrayV1($request), [
            'email' => $this->email,
        ]);
    }

    protected function toArrayV2($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'profile' => [
                'avatar' => $this->avatar_url,
                'bio' => $this->bio,
            ],
            'created_at' => $this->created_at->toISOString(),
        ];
    }

    protected function toArrayDefault($request): array
    {
        return $this->toArrayV2($request);
    }
}
```

## Configuration

The configuration file `config/api-versioning.php` contains all available options:

```php
return [
    'default_version' => '2.0',

    'supported_versions' => [
        '1.0', '1.1', '2.0', '2.1'
    ],

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

    'version_method_mapping' => [
        '1.0' => 'toArrayV1',
        '1.1' => 'toArrayV11',
        '2.0' => 'toArrayV2',
        '2.1' => 'toArrayV21',
    ],

    'version_inheritance' => [
        '1.1' => '1.0',  // v1.1 inherits from v1.0
        '2.1' => '2.0',  // v2.1 inherits from v2.0
    ],
];
```

## Usage

### Version Detection

The package supports multiple ways to specify the API version:

#### 1. Header-based (Recommended)
```bash
curl -H "X-API-Version: 2.0" https://api.example.com/users
```

#### 2. Query Parameter
```bash
curl "https://api.example.com/users?api-version=2.0"
```

#### 3. Path-based
```bash
curl "https://api.example.com/v2/users"
```

#### 4. Media Type
```bash
curl -H "Accept: application/vnd.api+json;version=2.0" https://api.example.com/users
```

### Available Attributes

#### `#[ApiVersion]`
Declares which versions a controller or method supports:

```php
#[ApiVersion('2.0')]                    // Single version
#[ApiVersion(['2.0', '2.1'])]          // Multiple versions
class UserController extends Controller
{
    #[ApiVersion('2.1')]               // Method-specific version
    public function newFeature() { }
}
```

#### `#[ApiVersionNeutral]`
Marks endpoints that work across all API versions:

```php
class HealthController extends Controller
{
    #[ApiVersionNeutral]
    public function check()
    {
        return response()->json(['status' => 'healthy']);
    }
}
```

#### `#[Deprecated]`
Marks versions as deprecated:

```php
#[ApiVersion('1.0')]
#[Deprecated(
    message: 'Version 1.0 is deprecated',
    sunsetDate: '2025-06-30',
    replacedBy: '2.0'
)]
class V1UserController extends Controller { }
```

#### `#[MapToApiVersion]`
Maps specific methods to API versions:

```php
class UserController extends Controller
{
    #[MapToApiVersion(['2.0', '2.1'])]
    public function advancedSearch() { }
}
```

### Resource Versioning

#### Method-based Versioning
Define version-specific methods in your resource classes:

```php
class UserResource extends VersionedJsonResource
{
    protected function toArrayV1($request): array
    {
        return ['id' => $this->id, 'name' => $this->name];
    }

    protected function toArrayV2($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'profile' => $this->profile,
        ];
    }
}
```

#### Dynamic Configuration
Use array-based configuration for simpler cases:

```php
class UserResource extends VersionedJsonResource
{
    protected array $versionConfigs = [
        '1.0' => ['id', 'name'],
        '1.1' => ['id', 'name', 'email'],
        '2.0' => ['id', 'name', 'email', 'profile', 'created_at'],
    ];

    protected function toArrayDefault($request): array
    {
        $version = $this->getCurrentApiVersion();
        $fields = $this->versionConfigs[$version] ?? $this->versionConfigs['2.0'];
        
        return $this->only($fields);
    }
}
```

### Traits and Helper Methods

Use the `HasApiVersionAttributes` trait in your controllers:

```php
use ShahGhasiAdil\LaravelApiVersioning\Traits\HasApiVersionAttributes;

class UserController extends Controller
{
    use HasApiVersionAttributes;

    public function index()
    {
        $version = $this->getCurrentApiVersion();        // "2.0"
        $isDeprecated = $this->isVersionDeprecated();    // true/false
        $isNeutral = $this->isVersionNeutral();          // true/false
        $message = $this->getDeprecationMessage();       // "Version deprecated..."
        
        return response()->json([
            'data' => UserResource::collection(User::all()),
            'version' => $version,
            'deprecated' => $isDeprecated,
        ]);
    }
}
```

## Artisan Commands

### View API Versions
Display all routes and their supported versions:

```bash
php artisan api:versions
```

Options:
- `--route=pattern` - Filter by route pattern
- `--version=2.0` - Filter by specific version  
- `--deprecated` - Show only deprecated endpoints

### Generate Versioned Controller
Create a new versioned controller:

```bash
php artisan make:versioned-controller UserController --version=2.0
php artisan make:versioned-controller UserController --version=1.0 --deprecated --sunset=2025-12-31
```

### Version Configuration
Manage version configurations:

```bash
php artisan api:version-config --show
php artisan api:version-config --add-version=2.2 --method=toArrayV22
```

## Response Headers

The middleware automatically adds version information to responses:

```http
HTTP/1.1 200 OK
X-API-Version: 2.0
X-API-Supported-Versions: 1.0, 1.1, 2.0, 2.1
X-API-Route-Versions: 2.0, 2.1
X-API-Deprecated: true
X-API-Sunset: 2025-12-31
X-API-Replaced-By: 2.1
```

## Error Handling

When an unsupported version is requested:

```json
{
    "error": "Unsupported API Version",
    "message": "API version '3.0' is not supported for this endpoint.",
    "requested_version": "3.0",
    "supported_versions": ["1.0", "1.1", "2.0", "2.1"],
    "documentation": "https://api.example.com/docs"
}
```

## Testing

The package includes testing utilities:

```php
use ShahGhasiAdil\LaravelApiVersioning\Testing\ApiVersionTestCase;

class UserApiTest extends ApiVersionTestCase
{
    public function test_v1_users_endpoint()
    {
        $response = $this->getWithVersion('/api/users', '1.0');
        
        $response->assertStatus(200);
        $this->assertApiVersion($response, '1.0')
             ->assertApiVersionDeprecated($response, '2025-12-31');
    }

    public function test_version_neutral_endpoint()
    {
        $response = $this->getWithVersion('/api/health', '1.0');
        $response->assertStatus(200);
        
        $response = $this->getWithVersion('/api/health', '2.0');
        $response->assertStatus(200);
    }
}
```

Available test methods:
- `getWithVersion($uri, $version)`
- `postWithVersion($uri, $data, $version)`
- `putWithVersion($uri, $data, $version)`
- `deleteWithVersion($uri, $version)`
- `getWithVersionQuery($uri, $version)`
- `assertApiVersion($response, $version)`
- `assertApiVersionDeprecated($response, $sunsetDate)`
- `assertSupportedVersions($response, $versions)`

## Examples

### Complete API Versioning Example

```php
// V1 Controller (Deprecated)
#[ApiVersion(['1.0', '1.1'])]
#[Deprecated(sunsetDate: '2025-12-31', replacedBy: '2.0')]
class V1UserController extends Controller
{
    use HasApiVersionAttributes;
    
    public function index()
    {
        return response()->json([
            'data' => User::select('id', 'name')->get(),
            'version' => $this->getCurrentApiVersion(),
        ]);
    }
    
    #[MapToApiVersion('1.1')]
    public function show($id)
    {
        return response()->json([
            'data' => User::select('id', 'name', 'email')->find($id),
            'version' => $this->getCurrentApiVersion(),
        ]);
    }
}

// V2 Controller (Current)
#[ApiVersion(['2.0', '2.1'])]
class V2UserController extends Controller
{
    use HasApiVersionAttributes;
    
    public function index()
    {
        return UserResource::collection(User::all());
    }
    
    public function show($id)
    {
        return new UserResource(User::findOrFail($id));
    }
    
    #[MapToApiVersion('2.1')]
    public function analytics($id)
    {
        $user = User::findOrFail($id);
        return response()->json([
            'user' => new UserResource($user),
            'analytics' => $user->getAnalytics(),
            'version' => $this->getCurrentApiVersion(),
        ]);
    }
}

// Shared/Neutral Controller
class StatusController extends Controller
{
    #[ApiVersionNeutral]
    public function health()
    {
        return response()->json(['status' => 'healthy']);
    }
}
```

### Routes Configuration

```php
// routes/api.php
Route::middleware(['api', 'api.version'])->group(function () {
    // Version-specific routes
    Route::prefix('v1')->group(function () {
        Route::get('users', [V1UserController::class, 'index']);
        Route::get('users/{id}', [V1UserController::class, 'show']);
    });
    
    Route::prefix('v2')->group(function () {
        Route::apiResource('users', V2UserController::class);
        Route::get('users/{id}/analytics', [V2UserController::class, 'analytics']);
    });
    
    // Version-neutral routes
    Route::get('health', [StatusController::class, 'health']);
    Route::get('status', [StatusController::class, 'status']);
});
```

## Best Practices

### 1. Version Strategy
- Use semantic versioning (1.0, 1.1, 2.0)
- Plan deprecation timeline before releasing new versions
- Provide clear migration guides

### 2. Resource Design
- Keep backward compatibility when possible
- Use inheritance for minor version changes
- Create new methods for major version changes

### 3. Testing
- Test all supported versions
- Test deprecation warnings
- Test version detection methods

### 4. Documentation
- Document version differences
- Provide upgrade guides
- Use clear deprecation notices

## Advanced Usage

### Custom Version Detection

You can extend version detection by creating custom detection methods:

```php
// In a service provider
$this->app->extend(VersionManager::class, function ($manager, $app) {
    $manager->addDetectionMethod('custom', function ($request) {
        return $request->header('Custom-Version-Header');
    });
    return $manager;
});
```

### Version-Specific Middleware

Create middleware that only applies to specific versions:

```php
class V2OnlyMiddleware
{
    public function handle($request, Closure $next)
    {
        $version = $request->attributes->get('api_version');
        
        if (!str_starts_with($version, '2.')) {
            return response()->json(['error' => 'Feature not available'], 404);
        }
        
        return $next($request);
    }
}
```

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review our security policy on how to report security vulnerabilities.

## Credits

- [Shahghasi Adil](https://github.com/shahghasiadil)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
