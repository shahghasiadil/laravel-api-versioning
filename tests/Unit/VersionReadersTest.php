<?php

use Illuminate\Http\Request;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionManager;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionReaders\CombinedApiVersionReader;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionReaders\HeaderApiVersionReader;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionReaders\MediaTypeApiVersionReader;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionReaders\QueryStringApiVersionReader;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionReaders\UrlSegmentApiVersionReader;

describe('individual readers', function () {
    test('HeaderApiVersionReader reads a configured header', function () {
        $request = Request::create('/api/users');
        $request->headers->set('X-Custom-Version', '3.0');

        expect((new HeaderApiVersionReader('X-Custom-Version'))->read($request))->toBe(['3.0']);
        expect((new HeaderApiVersionReader('X-Missing'))->read($request))->toBe([]);
    });

    test('QueryStringApiVersionReader reads a configured parameter', function () {
        $request = Request::create('/api/users?v=1.5');

        expect((new QueryStringApiVersionReader('v'))->read($request))->toBe(['1.5']);
        expect((new QueryStringApiVersionReader('missing'))->read($request))->toBe([]);
    });

    test('UrlSegmentApiVersionReader reads the version from the path', function () {
        $request = Request::create('/api/v2.1/users');

        expect((new UrlSegmentApiVersionReader('api/v'))->read($request))->toBe(['2.1']);
    });

    test('MediaTypeApiVersionReader reads all matching entries in q-order', function () {
        $request = Request::create('/api/users');
        $request->headers->set(
            'Accept',
            'application/vnd.api+json;version=1.0;q=0.5, application/vnd.api+json;version=2.0'
        );

        expect((new MediaTypeApiVersionReader('version'))->read($request))->toBe(['2.0', '1.0']);
    });

    test('MediaTypeApiVersionReader falls back to Content-Type', function () {
        $request = Request::create('/api/users');
        $request->headers->set('Content-Type', 'application/vnd.api+json;version=4.0');

        expect((new MediaTypeApiVersionReader('version'))->read($request))->toBe(['4.0']);
    });
});

describe('CombinedApiVersionReader', function () {
    test('readByReader maps each reader name to its first found value', function () {
        $request = Request::create('/api/users');
        $request->headers->set('X-API-Version', '1.0');

        $combined = new CombinedApiVersionReader([
            'header' => new HeaderApiVersionReader('X-API-Version'),
            'query' => new QueryStringApiVersionReader('api-version'),
        ]);

        expect($combined->readByReader($request))->toBe(['header' => '1.0']);
        expect($combined->read($request))->toBe(['1.0']);
    });
});

describe('VersionManager readers config form', function () {
    test('the readers config form is used in place of detection_methods when non-empty', function () {
        $manager = new VersionManager([
            'default_version' => '1.0',
            'supported_versions' => ['1.0', '9.0'],
            'detection_methods' => [
                'header' => ['enabled' => true, 'header_name' => 'X-API-Version'],
            ],
            'readers' => [
                QueryStringApiVersionReader::class => ['parameterName' => 'v'],
            ],
            'version_detection' => [],
        ]);

        // The header detection_methods entry is ignored because 'readers' is non-empty.
        $requestWithHeaderOnly = Request::create('/api/users');
        $requestWithHeaderOnly->headers->set('X-API-Version', '9.0');
        expect($manager->detectVersionFromRequest($requestWithHeaderOnly))->toBe('1.0'); // falls back to default

        $requestWithQuery = Request::create('/api/users?v=9.0');
        expect($manager->detectVersionFromRequest($requestWithQuery))->toBe('9.0');
    });
});
