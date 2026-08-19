<?php

use Illuminate\Http\Request;
use ShahGhasiAdil\LaravelApiVersioning\Http\Resources\VersionedJsonResource;
use ShahGhasiAdil\LaravelApiVersioning\Http\Resources\VersionedResourceCollection;

function bindRequestWithVersion(?string $version): Request
{
    $request = Request::create('/');

    if ($version !== null) {
        $request->attributes->set('api_version', $version);
    }

    app()->instance('request', $request);

    return $request;
}

describe('cyclic version_inheritance does not hang resource resolution', function () {
    test('VersionedJsonResource falls back to the default method instead of looping', function () {
        config([
            'api-versioning.version_method_mapping' => [],
            'api-versioning.version_inheritance' => [
                '1.0' => '1.1',
                '1.1' => '1.0',
            ],
            'api-versioning.default_method' => 'toArrayDefault',
        ]);

        $resource = new class(['id' => 1]) extends VersionedJsonResource
        {
            protected function toArrayDefault(Request $request): array
            {
                return ['fallback' => true];
            }
        };

        $request = bindRequestWithVersion('1.0');

        expect($resource->toArray($request))->toBe(['fallback' => true]);
    });

    test('VersionedResourceCollection falls back to the default method instead of looping', function () {
        config([
            'api-versioning.version_method_mapping' => [],
            'api-versioning.version_inheritance' => [
                '1.0' => '1.1',
                '1.1' => '1.0',
            ],
            'api-versioning.default_method' => 'toArrayDefault',
        ]);

        $collection = new class([]) extends VersionedResourceCollection
        {
            protected function toArrayDefault(Request $request): array
            {
                return ['fallback' => true];
            }
        };

        $request = bindRequestWithVersion('1.0');

        expect($collection->toArray($request))->toBe(['fallback' => true]);
    });
});

describe('VersionedResourceCollection honors the configured default_method', function () {
    test('uses a custom default_method instead of the hardcoded toArrayDefault', function () {
        config([
            'api-versioning.version_method_mapping' => [],
            'api-versioning.version_inheritance' => [],
            'api-versioning.default_method' => 'toArrayCustom',
        ]);

        $collection = new class([]) extends VersionedResourceCollection
        {
            protected function toArrayCustom(Request $request): array
            {
                return ['custom' => true];
            }

            protected function toArrayDefault(Request $request): array
            {
                return ['default' => true];
            }
        };

        $request = bindRequestWithVersion('9.9');

        expect($collection->toArray($request))->toBe(['custom' => true]);
    });
});
