<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Http\Responses;

use Illuminate\Http\JsonResponse;

/**
 * RFC 7807 Problem Details for HTTP APIs
 * @see https://tools.ietf.org/html/rfc7807
 */
class ProblemDetailsResponse extends JsonResponse
{
    public function __construct(
        string $title,
        string $detail,
        int $status = 400,
        ?string $type = null,
        ?string $instance = null,
        array $extensions = []
    ) {
        $data = [
            'type' => $type ?? 'about:blank',
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
        ];

        if ($instance !== null) {
            $data['instance'] = $instance;
        }

        // Add any additional extension members
        foreach ($extensions as $key => $value) {
            $data[$key] = $value;
        }

        parent::__construct($data, $status, [
            'Content-Type' => 'application/problem+json',
        ]);
    }

    /**
     * Create a problem details response for unsupported API version
     */
    public static function unsupportedVersion(
        string $requestedVersion,
        array $supportedVersions,
        array $endpointVersions = [],
        ?string $documentationUrl = null
    ): self {
        $extensions = [
            'requested_version' => $requestedVersion,
            'supported_versions' => $supportedVersions,
        ];

        if ($endpointVersions !== []) {
            $extensions['endpoint_versions'] = $endpointVersions;
        }

        if ($documentationUrl !== null) {
            $extensions['documentation'] = $documentationUrl;
        }

        return new self(
            title: 'Unsupported API Version',
            detail: "API version '{$requestedVersion}' is not supported for this endpoint.",
            status: 400,
            type: 'https://tools.ietf.org/html/rfc7231#section-6.5.1',
            extensions: $extensions
        );
    }

    /**
     * Create a problem details response for route not found
     */
    public static function routeNotFound(string $detail = 'Route not found'): self
    {
        return new self(
            title: 'Route Not Found',
            detail: $detail,
            status: 404,
            type: 'https://tools.ietf.org/html/rfc7231#section-6.5.4'
        );
    }
}
