<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\AdvertiseApiVersions;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\ApiVersion;
use ShahGhasiAdil\LaravelApiVersioning\Events\ApiVersionResolved;
use ShahGhasiAdil\LaravelApiVersioning\Events\DeprecatedApiVersionUsed;
use ShahGhasiAdil\LaravelApiVersioning\Examples\SharedController;
use ShahGhasiAdil\LaravelApiVersioning\Examples\V1UserController;
use ShahGhasiAdil\LaravelApiVersioning\Examples\V2UserController;

#[ApiVersion('2.0')]
#[AdvertiseApiVersions('2.1')]
class OrdersFeatureTestController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['ok' => true]);
    }
}

beforeEach(function () {
    // Set up routes for testing
    $this->app['router']->middleware(['api', 'api.version'])->prefix('api')->group(function () {
        // V1 routes
        $this->app['router']->get('v1/users', [V1UserController::class, 'index']);
        $this->app['router']->get('v1/users/{id}', [V1UserController::class, 'show']);

        // V2 routes
        $this->app['router']->get('v2/users', [V2UserController::class, 'index']);
        $this->app['router']->get('v2/users/{id}', [V2UserController::class, 'show']);

        // Shared/neutral routes
        $this->app['router']->get('health', [SharedController::class, 'health']);
        $this->app['router']->get('info', [SharedController::class, 'info']);

        // Closure route (no controller to carry attributes)
        $this->app['router']->get('ping', fn () => response()->json(['pong' => true]));

        // Advertises a version it doesn't implement
        $this->app['router']->get('orders', [OrdersFeatureTestController::class, 'index']);

        // Reads version info via the Request macros rather than the trait
        $this->app['router']->get('macro-check', function (Request $request) {
            return response()->json([
                'version' => $request->apiVersion(),
                'deprecated' => $request->isApiVersionDeprecated(),
                'info_version' => $request->apiVersionInfo()?->version,
            ]);
        });
    });
});

test('v1 controller responds to v1 request', function () {
    $response = getWithVersion('/api/v1/users', '1.0');

    $response->assertStatus(200)
        ->assertJson(['version' => '1.0'])
        ->assertJsonStructure(['data' => [['id', 'name']]]);

    assertApiVersion($response, '1.0');
    assertApiVersionDeprecated($response, '2025-12-31');
});

test('v1 controller emits an RFC 8594 Sunset header from its #[Deprecated] sunset date', function () {
    $response = getWithVersion('/api/v1/users', '1.0');

    $response->assertStatus(200)
        ->assertHeader('Sunset', 'Wed, 31 Dec 2025 00:00:00 GMT')
        ->assertHeaderMissing('Link');
});

test('a sunset_policies config entry adds a Link header alongside Sunset', function () {
    config(['api-versioning.sunset_policies' => [
        '1.0' => [
            'date' => '2025-12-31',
            'link' => 'https://example.com/migrate-to-v2',
            'link_type' => 'text/html',
        ],
    ]]);

    $response = getWithVersion('/api/v1/users', '1.0');

    $response->assertStatus(200)
        ->assertHeader('Sunset', 'Wed, 31 Dec 2025 00:00:00 GMT')
        ->assertHeader('Link', '<https://example.com/migrate-to-v2>; rel="sunset"; type="text/html"');
});

test('v2 controller responds to v2 request', function () {
    $response = getWithVersion('/api/v2/users', '2.0');

    $response->assertStatus(200)
        ->assertJson(['version' => '2.0'])
        ->assertJsonStructure([
            'data' => [['id', 'name', 'email', 'created_at', 'profile']],
            'meta' => ['total', 'per_page'],
        ]);

    assertApiVersion($response, '2.0');
});

test('version neutral endpoint', function () {
    $response = getWithVersion('/api/health', '1.0');
    $response->assertStatus(200)->assertJson(['status' => 'healthy']);

    $response = getWithVersion('/api/health', '2.0');
    $response->assertStatus(200)->assertJson(['status' => 'healthy']);
});

test('unsupported version returns 400', function () {
    $response = getWithVersion('/api/v1/users', '3.0');

    $response->assertStatus(400)
        ->assertJson([
            'title' => 'Unsupported API Version',
            'status' => 400,
        ]);
});

test('version detection from query', function () {
    $response = getWithVersionQuery('/api/v2/users', '2.0');

    $response->assertStatus(200);
    assertApiVersion($response, '2.0');
});

test('standard reporting headers are scoped to the endpoint', function () {
    $response = getWithVersion('/api/v1/users', '1.0');

    $response->assertStatus(200)
        ->assertHeader('api-supported-versions', '1.0, 1.1')
        ->assertHeader('api-deprecated-versions', '1.0, 1.1');

    $response = getWithVersion('/api/v2/users', '2.0');

    $response->assertStatus(200)
        ->assertHeader('api-supported-versions', '2.0, 2.1')
        ->assertHeaderMissing('api-deprecated-versions');
});

test('legacy_headers=false omits the X-API-* headers', function () {
    config(['api-versioning.reporting.legacy_headers' => false]);

    $response = getWithVersion('/api/v2/users', '2.0');

    $response->assertStatus(200)
        ->assertHeaderMissing('X-API-Supported-Versions')
        ->assertHeaderMissing('X-API-Route-Versions')
        ->assertHeader('api-supported-versions', '2.0, 2.1');
});

test('standard_headers=false omits the standard headers', function () {
    config(['api-versioning.reporting.standard_headers' => false]);

    $response = getWithVersion('/api/v2/users', '2.0');

    $response->assertStatus(200)
        ->assertHeaderMissing('api-supported-versions')
        ->assertHeaderMissing('api-deprecated-versions')
        ->assertHeader('X-API-Route-Versions', '2.0, 2.1');
});

test('require_explicit_version returns an Unspecified problem when no version is given', function () {
    config(['api-versioning.version_detection.require_explicit_version' => true]);

    // Note: /api/v2/users would itself be picked up by path-based detection,
    // so this uses a route without a version segment in its URL.
    $response = test()->get('/api/health');

    $response->assertStatus(400)
        ->assertJson([
            'title' => 'Unspecified API Version',
            'code' => 'ApiVersionUnspecified',
        ]);
});

test('reject_conflicting_versions returns an Ambiguous problem when detection methods disagree', function () {
    config(['api-versioning.version_detection.reject_conflicting_versions' => true]);

    $response = test()->withHeaders(['X-API-Version' => '1.0'])
        ->get('/api/v2/users?api-version=2.0');

    $response->assertStatus(400)
        ->assertJson([
            'title' => 'Ambiguous API Version',
            'code' => 'AmbiguousApiVersion',
            'conflicts' => ['header' => '1.0', 'query' => '2.0'],
        ]);
});

test('closure routes are version-neutral by default', function () {
    $response = getWithVersion('/api/ping', '1.0');
    $response->assertStatus(200)->assertJson(['pong' => true]);

    $response = getWithVersion('/api/ping', '2.0');
    $response->assertStatus(200)->assertJson(['pong' => true]);
});

test('the Request macros expose version info resolved by the middleware', function () {
    $response = getWithVersion('/api/macro-check', '2.0');

    $response->assertStatus(200)->assertJson([
        'version' => '2.0',
        'deprecated' => false,
        'info_version' => '2.0',
    ]);
});

test('ApiVersionResolved and DeprecatedApiVersionUsed fire on a real request', function () {
    Event::fake();

    getWithVersion('/api/v1/users', '1.0')->assertStatus(200);

    Event::assertDispatched(ApiVersionResolved::class, fn (ApiVersionResolved $e) => $e->versionInfo->version === '1.0');
    Event::assertDispatched(DeprecatedApiVersionUsed::class, fn (DeprecatedApiVersionUsed $e) => $e->versionInfo->version === '1.0');
});

test('DeprecatedApiVersionUsed does not fire for a non-deprecated version', function () {
    Event::fake();

    getWithVersion('/api/v2/users', '2.0')->assertStatus(200);

    Event::assertDispatched(ApiVersionResolved::class);
    Event::assertNotDispatched(DeprecatedApiVersionUsed::class);
});

test('closure_routes=reject restores the original 400 behavior', function () {
    config(['api-versioning.closure_routes' => 'reject']);

    $response = getWithVersion('/api/ping', '1.0');

    $response->assertStatus(400)
        ->assertJson(['title' => 'Unsupported API Version']);
});

test('an advertised version is discoverable but not resolvable on the advertising route', function () {
    $response = getWithVersion('/api/orders', '2.0');
    $response->assertStatus(200)
        ->assertHeader('api-supported-versions', '2.0, 2.1');

    $response = getWithVersion('/api/orders', '2.1');
    $response->assertStatus(400)
        ->assertJson([
            'title' => 'Unsupported API Version',
            'endpoint_versions' => ['2.0', '2.1'],
        ]);
});

test('format_validation.enabled returns an Invalid problem for a malformed version', function () {
    config(['api-versioning.version_detection.format_validation.enabled' => true]);

    $response = getWithVersion('/api/v2/users', 'not-a-version!!');

    $response->assertStatus(400)
        ->assertJson([
            'title' => 'Invalid API Version',
            'code' => 'InvalidApiVersion',
        ]);
});

/**
 * Call the given URI with API version header
 */
function getWithVersion(string $uri, string $version, array $headers = []): TestResponse
{
    return test()->withHeaders(array_merge($headers, [
        'X-API-Version' => $version,
    ]))->get($uri);
}

/**
 * Call the given URI with API version query parameter
 */
function getWithVersionQuery(string $uri, string $version, array $headers = []): TestResponse
{
    $separator = str_contains($uri, '?') ? '&' : '?';

    return test()->withHeaders($headers)->get($uri.$separator.'api-version='.$version);
}

/**
 * Assert response has correct version headers
 */
function assertApiVersion(TestResponse $response, string $expectedVersion): void
{
    $response->assertHeader('X-API-Version', $expectedVersion);
}

/**
 * Assert response indicates deprecation
 */
function assertApiVersionDeprecated(TestResponse $response, ?string $sunsetDate = null): void
{
    $response->assertHeader('X-API-Deprecated', 'true');

    if ($sunsetDate !== null) {
        $response->assertHeader('X-API-Sunset', $sunsetDate);
    }
}

/**
 * Assert response supports specific versions
 */
function assertSupportedVersions(TestResponse $response, array $versions): void
{
    $response->assertHeader('X-API-Supported-Versions', implode(', ', $versions));
}
