<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Testing;

use Illuminate\Testing\TestResponse;
use Orchestra\Testbench\TestCase as BaseTestCase;
use ShahGhasiAdil\LaravelApiVersioning\ApiVersioningServiceProvider;

abstract class ApiVersionTestCase extends BaseTestCase
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ApiVersioningServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Define environment setup for testing
        $app['config']->set('api-versioning', [
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
                '1.1' => '1.0',
                '2.1' => '2.0',
            ],
            'default_method' => 'toArrayDefault',
        ]);
    }

    /**
     * Call the given URI with API version header
     *
     * @param  array<string, string>  $headers
     */
    protected function getWithVersion(string $uri, string $version, array $headers = []): TestResponse
    {
        return $this->withHeaders(array_merge($headers, [
            'X-API-Version' => $version,
        ]))->get($uri);
    }

    /**
     * Call the given URI with API version query parameter
     *
     * @param  array<string, string>  $headers
     */
    protected function getWithVersionQuery(string $uri, string $version, array $headers = []): TestResponse
    {
        $separator = str_contains($uri, '?') ? '&' : '?';

        return $this->withHeaders($headers)->get($uri.$separator.'api-version='.$version);
    }

    /**
     * Make a POST request with API version
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    protected function postWithVersion(string $uri, array $data, string $version, array $headers = []): TestResponse
    {
        return $this->withHeaders(array_merge($headers, [
            'X-API-Version' => $version,
        ]))->post($uri, $data);
    }

    /**
     * Make a PUT request with API version
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    protected function putWithVersion(string $uri, array $data, string $version, array $headers = []): TestResponse
    {
        return $this->withHeaders(array_merge($headers, [
            'X-API-Version' => $version,
        ]))->put($uri, $data);
    }

    /**
     * Make a DELETE request with API version
     *
     * @param  array<string, string>  $headers
     */
    protected function deleteWithVersion(string $uri, string $version, array $headers = []): TestResponse
    {
        return $this->withHeaders(array_merge($headers, [
            'X-API-Version' => $version,
        ]))->delete($uri);
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

        if ($sunsetDate !== null) {
            $response->assertHeader('X-API-Sunset', $sunsetDate);
        }

        return $this;
    }

    /**
     * Assert response supports specific versions
     *
     * @param  string[]  $versions
     */
    protected function assertSupportedVersions(TestResponse $response, array $versions): static
    {
        $response->assertHeader('X-API-Supported-Versions', implode(', ', $versions));

        return $this;
    }

    /**
     * Assert response indicates version is not deprecated
     */
    protected function assertApiVersionNotDeprecated(TestResponse $response): static
    {
        $response->assertHeaderMissing('X-API-Deprecated');

        return $this;
    }

    /**
     * Assert response has route-specific version headers
     *
     * @param  string[]  $versions
     */
    protected function assertRouteVersions(TestResponse $response, array $versions): static
    {
        $response->assertHeader('X-API-Route-Versions', implode(', ', $versions));

        return $this;
    }

    /**
     * Assert response has deprecation message
     */
    protected function assertDeprecationMessage(TestResponse $response, string $message): static
    {
        $response->assertHeader('X-API-Deprecation-Message', $message);

        return $this;
    }

    /**
     * Assert response has replacement version
     */
    protected function assertReplacedBy(TestResponse $response, string $version): static
    {
        $response->assertHeader('X-API-Replaced-By', $version);

        return $this;
    }
}
