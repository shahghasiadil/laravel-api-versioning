<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use ShahGhasiAdil\LaravelApiVersioning\Exceptions\UnsupportedVersionException;

beforeEach(function () {
    // Clear any existing configuration to ensure clean state
    config(['api-versioning' => []]);
});

describe('exception creation', function () {
    test('creates exception with all parameters', function () {
        $exception = new UnsupportedVersionException(
            message: 'Version not supported',
            supportedVersions: ['1.0', '2.0'],
            requestedVersion: '3.0',
            code: 400
        );

        expect($exception->getMessage())->toBe('Version not supported');
        expect($exception->supportedVersions)->toBe(['1.0', '2.0']);
        expect($exception->requestedVersion)->toBe('3.0');
        expect($exception->getCode())->toBe(400);
    });

    test('creates exception with minimal parameters', function () {
        $exception = new UnsupportedVersionException('Simple message');

        expect($exception->getMessage())->toBe('Simple message');
        expect($exception->supportedVersions)->toBe([]);
        expect($exception->requestedVersion)->toBeNull();
        expect($exception->getCode())->toBe(0);
    });

    test('creates exception with empty arrays', function () {
        $exception = new UnsupportedVersionException(
            message: 'Error',
            supportedVersions: [],
            requestedVersion: null
        );

        expect($exception->supportedVersions)->toBe([]);
        expect($exception->requestedVersion)->toBeNull();
    });
});

describe('json response rendering', function () {
    test('renders complete error response', function () {
        $exception = new UnsupportedVersionException(
            message: 'API version "3.0" is not supported',
            supportedVersions: ['1.0', '2.0', '2.1'],
            requestedVersion: '3.0'
        );

        $request = Request::create('/api/users');
        $response = $exception->render($request);

        expect($response)->toBeInstanceOf(JsonResponse::class);
        expect($response->getStatusCode())->toBe(400);

        $data = $response->getData(true);
        expect($data['error'])->toBe('Unsupported API Version');
        expect($data['message'])->toBe('API version "3.0" is not supported');
        expect($data['supported_versions'])->toBe(['1.0', '2.0', '2.1']);
        expect($data['requested_version'])->toBe('3.0');
    });

    test('renders response without requested version', function () {
        $exception = new UnsupportedVersionException(
            message: 'Invalid version format',
            supportedVersions: ['1.0', '2.0'],
            requestedVersion: null
        );

        $request = Request::create('/api/users');
        $response = $exception->render($request);

        $data = $response->getData(true);
        expect($data['error'])->toBe('Unsupported API Version');
        expect($data['message'])->toBe('Invalid version format');
        expect($data['supported_versions'])->toBe(['1.0', '2.0']);
        expect($data)->not()->toHaveKey('requested_version');
    });

    test('renders response with empty supported versions', function () {
        $exception = new UnsupportedVersionException(
            message: 'No versions available',
            supportedVersions: [],
            requestedVersion: '1.0'
        );

        $request = Request::create('/api/users');
        $response = $exception->render($request);

        $data = $response->getData(true);
        expect($data['supported_versions'])->toBe([]);
        expect($data['requested_version'])->toBe('1.0');
    });

    test('includes documentation url when configured', function () {
        config(['api-versioning.documentation.base_url' => 'https://api-docs.example.com']);

        $exception = new UnsupportedVersionException(
            message: 'Version not found',
            supportedVersions: ['1.0'],
            requestedVersion: '2.0'
        );

        $request = Request::create('/api/users');
        $response = $exception->render($request);

        $data = $response->getData(true);
        expect($data['documentation'])->toBe('https://api-docs.example.com');
    });

    test('omits documentation url when not configured', function () {
        config(['api-versioning.documentation.base_url' => null]);

        $exception = new UnsupportedVersionException('Error');

        $request = Request::create('/api/users');
        $response = $exception->render($request);

        $data = $response->getData(true);
        expect($data)->not()->toHaveKey('documentation');
    });

    test('omits documentation url when empty string', function () {
        config(['api-versioning.documentation.base_url' => '']);

        $exception = new UnsupportedVersionException('Error');

        $request = Request::create('/api/users');
        $response = $exception->render($request);

        $data = $response->getData(true);
        expect($data)->not()->toHaveKey('documentation');
    });
});

describe('inheritance behavior', function () {
    test('extends standard Exception class', function () {
        $exception = new UnsupportedVersionException('Test');

        expect($exception)->toBeInstanceOf(Exception::class);
    });

    test('supports exception chaining', function () {
        $previous = new Exception('Previous error');
        $exception = new UnsupportedVersionException(
            message: 'Current error',
            previous: $previous
        );

        expect($exception->getPrevious())->toBe($previous);
    });

    test('preserves standard exception properties', function () {
        $exception = new UnsupportedVersionException(
            message: 'Test message',
            code: 123
        );

        expect($exception->getMessage())->toBe('Test message');
        expect($exception->getCode())->toBe(123);
        expect($exception->getFile())->toBeString();
        expect($exception->getLine())->toBeInt();
    });
});

describe('edge cases', function () {
    test('handles very long error messages', function () {
        $longMessage = str_repeat('Very long error message. ', 100);
        $exception = new UnsupportedVersionException($longMessage);

        $request = Request::create('/api/test');
        $response = $exception->render($request);

        $data = $response->getData(true);
        expect($data['message'])->toBe($longMessage);
    });

    test('handles special characters in version strings', function () {
        $exception = new UnsupportedVersionException(
            message: 'Invalid version',
            supportedVersions: ['v1.0-beta', 'v2.0-alpha'],
            requestedVersion: 'v3.0@special'
        );

        $request = Request::create('/api/test');
        $response = $exception->render($request);

        $data = $response->getData(true);
        expect($data['supported_versions'])->toBe(['v1.0-beta', 'v2.0-alpha']);
        expect($data['requested_version'])->toBe('v3.0@special');
    });

    test('handles numeric version arrays', function () {
        $exception = new UnsupportedVersionException(
            message: 'Numeric versions',
            supportedVersions: ['1', '2', '3'],
            requestedVersion: '4'
        );

        $request = Request::create('/api/test');
        $response = $exception->render($request);

        $data = $response->getData(true);
        expect($data['supported_versions'])->toBe(['1', '2', '3']);
        expect($data['requested_version'])->toBe('4');
    });
});
