<?php

namespace ShahGhasiAdil\LaravelApiVersioning\Tests\Feature;

use ShahGhasiAdil\LaravelApiVersioning\Tests\TestCase;
use ShahGhasiAdil\LaravelApiVersioning\Examples\V1UserController;
use ShahGhasiAdil\LaravelApiVersioning\Examples\V2UserController;
use ShahGhasiAdil\LaravelApiVersioning\Examples\SharedController;
use Illuminate\Testing\TestResponse;

class AttributeVersioningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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
    }

    public function testV1ControllerRespondsToV1Request()
    {
        $response = $this->getWithVersion('/api/v1/users', '1.0');

        $response->assertStatus(200)
                ->assertJson(['version' => '1.0'])
                ->assertJsonStructure(['data' => [['id', 'name']]]);

        $this->assertApiVersion($response, '1.0')
             ->assertApiVersionDeprecated($response, '2025-12-31');
    }

    public function testV2ControllerRespondsToV2Request()
    {
        $response = $this->getWithVersion('/api/v2/users', '2.0');

        $response->assertStatus(200)
                ->assertJson(['version' => '2.0'])
                ->assertJsonStructure([
                    'data' => [['id', 'name', 'email', 'created_at', 'profile']],
                    'meta' => ['total', 'per_page']
                ]);

        $this->assertApiVersion($response, '2.0');
    }

    public function testVersionNeutralEndpoint()
    {
        $response = $this->getWithVersion('/api/health', '1.0');
        $response->assertStatus(200)->assertJson(['status' => 'healthy']);

        $response = $this->getWithVersion('/api/health', '2.0');
        $response->assertStatus(200)->assertJson(['status' => 'healthy']);
    }

    public function testUnsupportedVersionReturns400()
    {
        $response = $this->getWithVersion('/api/v1/users', '3.0');

        $response->assertStatus(400)
                ->assertJson([
                    'error' => 'Unsupported API Version'
                ]);
    }

    public function testVersionDetectionFromQuery()
    {
        $response = $this->getWithVersionQuery('/api/v2/users', '2.0');

        $response->assertStatus(200);
        $this->assertApiVersion($response, '2.0');
    }

    /**
     * Call the given URI with API version header
     */
    protected function getWithVersion(string $uri, string $version, array $headers = []): TestResponse
    {
        return $this->withHeaders(array_merge($headers, [
            'X-API-Version' => $version,
        ]))->get($uri);
    }

    /**
     * Call the given URI with API version query parameter
     */
    protected function getWithVersionQuery(string $uri, string $version, array $headers = []): TestResponse
    {
        $separator = str_contains($uri, '?') ? '&' : '?';
        return $this->withHeaders($headers)->get($uri . $separator . 'api-version=' . $version);
    }

    /**
     * Assert response has correct version headers
     */
    protected function assertApiVersion(TestResponse $response, string $expectedVersion): static
    {
        $response->assertHeader('X-API-Version', $expectedVersion);
        return $this;
    }

    /**
     * Assert response indicates deprecation
     */
    protected function assertApiVersionDeprecated(TestResponse $response, ?string $sunsetDate = null): static
    {
        $response->assertHeader('X-API-Deprecated', 'true');

        if ($sunsetDate) {
            $response->assertHeader('X-API-Sunset', $sunsetDate);
        }

        return $this;
    }

    /**
     * Assert response supports specific versions
     */
    protected function assertSupportedVersions(TestResponse $response, array $versions): static
    {
        $response->assertHeader('X-API-Supported-Versions', implode(', ', $versions));
        return $this;
    }
}
