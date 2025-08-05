<?php

namespace ShahGhasiAdil\LaravelApiVersioning\Exceptions;

use Exception;

class UnsupportedVersionException extends Exception
{
    public function __construct(
        string $message = '',
        public readonly array $supportedVersions = [],
        public readonly ?string $requestedVersion = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function render($request)
    {
        return response()->json([
            'error' => 'Unsupported API Version',
            'message' => $this->getMessage(),
            'requested_version' => $this->requestedVersion,
            'supported_versions' => $this->supportedVersions,
            'documentation' => config('api-versioning.documentation.base_url'),
        ], 400);
    }
}
