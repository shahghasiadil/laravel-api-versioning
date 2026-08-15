<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Http\Responses;

use Illuminate\Http\JsonResponse;
use ShahGhasiAdil\LaravelApiVersioning\Exceptions\VersionProblemReason;

/**
 * RFC 7807 Problem Details for HTTP APIs
 *
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
            'code' => VersionProblemReason::Unsupported->code(),
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
            title: VersionProblemReason::Unsupported->title(),
            detail: "API version '{$requestedVersion}' is not supported for this endpoint.",
            status: 400,
            type: 'https://tools.ietf.org/html/rfc7231#section-6.5.1',
            extensions: $extensions
        );
    }

    /**
     * Create a problem details response for a version-detection failure that
     * isn't a plain "unsupported version": no version specified, an
     * invalidly formatted version, or conflicting versions supplied across
     * multiple detection methods.
     *
     * @param  string[]  $supportedVersions
     * @param  array<string, mixed>  $context  Reason-specific extra data. Currently only
     *                                         'conflicts' (array<string, string>: detection method => detected value) is used,
     *                                         for {@see VersionProblemReason::Ambiguous}.
     */
    public static function versionProblem(
        VersionProblemReason $reason,
        ?string $requestedVersion,
        array $supportedVersions,
        array $context = [],
        ?string $documentationUrl = null
    ): self {
        $extensions = [
            'code' => $reason->code(),
            'supported_versions' => $supportedVersions,
        ];

        if ($requestedVersion !== null) {
            $extensions['requested_version'] = $requestedVersion;
        }

        if (isset($context['conflicts']) && is_array($context['conflicts']) && $context['conflicts'] !== []) {
            $extensions['conflicts'] = $context['conflicts'];
        }

        if ($documentationUrl !== null) {
            $extensions['documentation'] = $documentationUrl;
        }

        $detail = match ($reason) {
            VersionProblemReason::Unspecified => 'No API version was specified and none could be assumed.',
            VersionProblemReason::Invalid => "API version '{$requestedVersion}' is not a validly formatted version.",
            VersionProblemReason::Ambiguous => 'Multiple, conflicting API versions were specified in the same request.',
            VersionProblemReason::Unsupported => "API version '{$requestedVersion}' is not supported.",
        };

        return new self(
            title: $reason->title(),
            detail: $detail,
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
