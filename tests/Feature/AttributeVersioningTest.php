<?php

use Illuminate\Testing\TestResponse;
use ShahGhasiAdil\LaravelApiVersioning\Examples\SharedController;
use ShahGhasiAdil\LaravelApiVersioning\Examples\V1UserController;
use ShahGhasiAdil\LaravelApiVersioning\Examples\V2UserController;

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
