<?php

namespace ShahGhasiAdil\LaravelApiVersioning\Testing;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class ApiVersionTestCase extends BaseTestCase
{
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
