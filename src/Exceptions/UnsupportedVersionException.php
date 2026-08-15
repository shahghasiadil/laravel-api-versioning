<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use ShahGhasiAdil\LaravelApiVersioning\Http\Responses\ProblemDetailsResponse;
use Throwable;

class UnsupportedVersionException extends Exception
{
    /**
     * @param  string[]  $supportedVersions
     */
    public function __construct(
        string $message = '',
        public readonly array $supportedVersions = [],
        public readonly ?string $requestedVersion = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Render the exception as the same RFC 7807 problem-details response the
     * versioning middleware produces, so the response shape is identical
     * whether the client is caught by the middleware or bubbles up here.
     */
    public function render(Request $request): JsonResponse
    {
        $documentationUrl = config('api-versioning.documentation.base_url');

        return ProblemDetailsResponse::unsupportedVersion(
            requestedVersion: $this->requestedVersion ?? 'unknown',
            supportedVersions: $this->supportedVersions,
            documentationUrl: is_string($documentationUrl) && $documentationUrl !== '' ? $documentationUrl : null
        );
    }
}
