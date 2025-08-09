<?php

namespace ShahGhasiAdil\LaravelApiVersioning\Tests\Feature;

use ShahGhasiAdil\LaravelApiVersioning\Testing\ApiVersionTestCase;
use ShahGhasiAdil\LaravelApiVersioning\Examples\V1UserController;
use ShahGhasiAdil\LaravelApiVersioning\Examples\V2UserController;
use ShahGhasiAdil\LaravelApiVersioning\Examples\SharedController;

class AttributeVersioningTest extends ApiVersionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set up routes for testing
        $this->app['router']->middleware(['api', 'api.version'])->prefix('api')->group(function () {
            $this->app['router']->get('users', [V1UserController::class, 'index']);
            $this->app['router']->get('users', [V2UserController::class, 'index']);
            $this->app['router']->get('health', [SharedController::class, 'health']);
        });
    }

    public function testV1ControllerRespondsToV1Request()
    {
        $response = $this->getWithVersion('/api/users', '1.0');

        $response->assertStatus(200)
                ->assertJson(['version' => '1.0'])
                ->assertJsonStructure(['data' => [['id', 'name']]]);

        $this->assertApiVersion($response, '1.0')
             ->assertApiVersionDeprecated($response, '2025-12-31');
    }

    public function testV2ControllerRespondsToV2Request()
    {
        $response = $this->getWithVersion('/api/users', '2.0');

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
        $response = $this->getWithVersion('/api/users', '3.0');

        $response->assertStatus(400)
                ->assertJson([
                    'error' => 'Unsupported API Version',
                    'requested_version' => '3.0'
                ]);
    }

    public function testVersionDetectionFromQuery()
    {
        $response = $this->getWithVersionQuery('/api/users', '2.0');

        $response->assertStatus(200);
        $this->assertApiVersion($response, '2.0');
    }
}
