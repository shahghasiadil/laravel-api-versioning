<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function render(Request $request): JsonResponse
    {
        $data = [
            'error' => 'Unsupported API Version',
            'message' => $this->getMessage(),
            'supported_versions' => $this->supportedVersions,
        ];

        if ($this->requestedVersion !== null) {
            $data['requested_version'] = $this->requestedVersion;
        }

        $documentationUrl = config('api-versioning.documentation.base_url');
        if (is_string($documentationUrl) && $documentationUrl !== '') {
            $data['documentation'] = $documentationUrl;
        }

        return response()->json($data, 400);
    }
}
