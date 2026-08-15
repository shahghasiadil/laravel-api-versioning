<?php

use Illuminate\Http\Request;
use ShahGhasiAdil\LaravelApiVersioning\Exceptions\UnsupportedVersionException;
use ShahGhasiAdil\LaravelApiVersioning\Exceptions\VersionProblemReason;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionManager;

beforeEach(function () {
    $this->config = [
        'default_version' => '2.0',
        'supported_versions' => ['1.0', '1.1', '2', '2.0', '2.1'],
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
                'enabled' => true,
                'format' => 'application/vnd.api+json;version=%s',
            ],
        ],
    ];

    $this->versionManager = new VersionManager($this->config);
});

describe('version detection from request', function () {
    test('detects version from header', function () {
        $request = Request::create('/api/users');
        $request->headers->set('X-API-Version', '1.1');

        $version = $this->versionManager->detectVersionFromRequest($request);

        expect($version)->toBe('1.1');
    });

    test('detects version from query parameter', function () {
        $request = Request::create('/api/users?api-version=2.1');

        $version = $this->versionManager->detectVersionFromRequest($request);

        expect($version)->toBe('2.1');
    });

    test('detects version from path', function () {
        $request = Request::create('/api/v1.0/users');

        $version = $this->versionManager->detectVersionFromRequest($request);

        expect($version)->toBe('1.0');
    });

    test('detects version from media type', function () {
        $request = Request::create('/api/users');
        $request->headers->set('Accept', 'application/vnd.api+json;version=2.0');

        $version = $this->versionManager->detectVersionFromRequest($request);

        expect($version)->toBe('2.0');
    });

    test('uses default version when no version detected', function () {
        $request = Request::create('/api/users');

        $version = $this->versionManager->detectVersionFromRequest($request);

        expect($version)->toBe('2.0');
    });

    test('header takes priority over query parameter', function () {
        $request = Request::create('/api/users?api-version=1.0');
        $request->headers->set('X-API-Version', '2.1');

        $version = $this->versionManager->detectVersionFromRequest($request);

        expect($version)->toBe('2.1');
    });

    test('query takes priority over path', function () {
        $request = Request::create('/api/v1.0/users?api-version=2.0');

        $version = $this->versionManager->detectVersionFromRequest($request);

        expect($version)->toBe('2.0');
    });

    test('path takes priority over media type', function () {
        $request = Request::create('/api/v1.1/users');
        $request->headers->set('Accept', 'application/vnd.api+json;version=2.0');

        $version = $this->versionManager->detectVersionFromRequest($request);

        expect($version)->toBe('1.1');
    });
});

describe('path version extraction', function () {
    test('extracts version from simple path', function () {
        $request = Request::create('/api/v2.0/users');

        $version = $this->versionManager->detectVersionFromRequest($request);

        expect($version)->toBe('2.0');
    });

    test('extracts version from nested path', function () {
        $request = Request::create('/api/v1.1/users/123/posts');

        $version = $this->versionManager->detectVersionFromRequest($request);

        expect($version)->toBe('1.1');
    });

    test('returns null for malformed path version', function () {
        $request = Request::create('/api/vInvalid/users');

        $version = $this->versionManager->detectVersionFromRequest($request);

        expect($version)->toBe('2.0'); // Falls back to default
    });

    test('handles integer versions in path', function () {
        $request = Request::create('/api/v2/users');

        $version = $this->versionManager->detectVersionFromRequest($request);

        expect($version)->toBe('2');
    });
});

describe('media type version extraction', function () {
    test('extracts version from accept header with version parameter', function () {
        $request = Request::create('/api/users');
        $request->headers->set('Accept', 'application/vnd.api+json;version=1.0');

        $version = $this->versionManager->detectVersionFromRequest($request);

        expect($version)->toBe('1.0');
    });

    test('extracts version from complex accept header', function () {
        $request = Request::create('/api/users');
        $request->headers->set('Accept', 'application/json, application/vnd.api+json;version=2.1, text/html');

        $version = $this->versionManager->detectVersionFromRequest($request);

        expect($version)->toBe('2.1');
    });

    test('returns null for accept header without version', function () {
        $request = Request::create('/api/users');
        $request->headers->set('Accept', 'application/json');

        $version = $this->versionManager->detectVersionFromRequest($request);

        expect($version)->toBe('2.0'); // Falls back to default
    });

    test('handles missing accept header', function () {
        $request = Request::create('/api/users');

        $version = $this->versionManager->detectVersionFromRequest($request);

        expect($version)->toBe('2.0'); // Falls back to default
    });
});

describe('version validation', function () {
    test('throws exception for unsupported version', function () {
        $request = Request::create('/api/users');
        $request->headers->set('X-API-Version', '3.0');

        expect(fn () => $this->versionManager->detectVersionFromRequest($request))
            ->toThrow(UnsupportedVersionException::class, "API version '3.0' is not supported.");
    });

    test('accepts all supported versions', function () {
        $supportedVersions = ['1.0', '1.1', '2.0', '2.1'];

        foreach ($supportedVersions as $version) {
            $request = Request::create('/api/users');
            $request->headers->set('X-API-Version', $version);

            $detectedVersion = $this->versionManager->detectVersionFromRequest($request);

            expect($detectedVersion)->toBe($version);
        }
    });
});

describe('disabled detection methods', function () {
    test('skips disabled header detection', function () {
        $config = $this->config;
        $config['detection_methods']['header']['enabled'] = false;

        $versionManager = new VersionManager($config);
        $request = Request::create('/api/users?api-version=1.0');
        $request->headers->set('X-API-Version', '2.1');

        $version = $versionManager->detectVersionFromRequest($request);

        expect($version)->toBe('1.0'); // Uses query instead of header
    });

    test('skips disabled query detection', function () {
        $config = $this->config;
        $config['detection_methods']['query']['enabled'] = false;

        $versionManager = new VersionManager($config);
        $request = Request::create('/api/v1.1/users?api-version=2.0');

        $version = $versionManager->detectVersionFromRequest($request);

        expect($version)->toBe('1.1'); // Uses path instead of query
    });
});

describe('version_detection.require_explicit_version', function () {
    test('still assumes default_version when disabled (default)', function () {
        $request = Request::create('/api/users');

        expect($this->versionManager->detectVersionFromRequest($request))->toBe('2.0');
    });

    test('throws an Unspecified problem when enabled and no version is given', function () {
        $config = $this->config;
        $config['version_detection']['require_explicit_version'] = true;
        $versionManager = new VersionManager($config);

        $request = Request::create('/api/users');

        try {
            $versionManager->detectVersionFromRequest($request);
            $this->fail('Expected UnsupportedVersionException to be thrown.');
        } catch (UnsupportedVersionException $e) {
            expect($e->reason)->toBe(VersionProblemReason::Unspecified);
            expect($e->requestedVersion)->toBeNull();
        }
    });

    test('does not interfere when a version is explicitly given', function () {
        $config = $this->config;
        $config['version_detection']['require_explicit_version'] = true;
        $versionManager = new VersionManager($config);

        $request = Request::create('/api/users');
        $request->headers->set('X-API-Version', '1.0');

        expect($versionManager->detectVersionFromRequest($request))->toBe('1.0');
    });
});

describe('version_detection.reject_conflicting_versions', function () {
    test('still uses first-match-wins when disabled (default)', function () {
        $request = Request::create('/api/users?api-version=1.0');
        $request->headers->set('X-API-Version', '2.1');

        expect($this->versionManager->detectVersionFromRequest($request))->toBe('2.1');
    });

    test('throws an Ambiguous problem when enabled and methods disagree', function () {
        $config = $this->config;
        $config['version_detection']['reject_conflicting_versions'] = true;
        $versionManager = new VersionManager($config);

        $request = Request::create('/api/users?api-version=1.0');
        $request->headers->set('X-API-Version', '2.1');

        try {
            $versionManager->detectVersionFromRequest($request);
            $this->fail('Expected UnsupportedVersionException to be thrown.');
        } catch (UnsupportedVersionException $e) {
            expect($e->reason)->toBe(VersionProblemReason::Ambiguous);
            expect($e->context['conflicts'])->toBe(['header' => '2.1', 'query' => '1.0']);
        }
    });

    test('does not throw when enabled but every method agrees', function () {
        $config = $this->config;
        $config['version_detection']['reject_conflicting_versions'] = true;
        $versionManager = new VersionManager($config);

        $request = Request::create('/api/users?api-version=2.1');
        $request->headers->set('X-API-Version', '2.1');

        expect($versionManager->detectVersionFromRequest($request))->toBe('2.1');
    });
});

describe('version_detection.format_validation', function () {
    test('does not validate format when disabled (default)', function () {
        $request = Request::create('/api/users');
        $request->headers->set('X-API-Version', 'not-a-version-at-all!!');

        // Falls through to the ordinary "unsupported" path, not "invalid".
        expect(fn () => $this->versionManager->detectVersionFromRequest($request))
            ->toThrow(UnsupportedVersionException::class);
    });

    test('throws an Invalid problem for a malformed version when enabled', function () {
        $config = $this->config;
        $config['version_detection']['format_validation']['enabled'] = true;
        $versionManager = new VersionManager($config);

        $request = Request::create('/api/users');
        $request->headers->set('X-API-Version', 'not-a-version-at-all!!');

        try {
            $versionManager->detectVersionFromRequest($request);
            $this->fail('Expected UnsupportedVersionException to be thrown.');
        } catch (UnsupportedVersionException $e) {
            expect($e->reason)->toBe(VersionProblemReason::Invalid);
            expect($e->requestedVersion)->toBe('not-a-version-at-all!!');
        }
    });

    test('accepts validly formatted versions when enabled', function () {
        $config = $this->config;
        $config['version_detection']['format_validation']['enabled'] = true;
        $versionManager = new VersionManager($config);

        $request = Request::create('/api/users');
        $request->headers->set('X-API-Version', '2.1');

        expect($versionManager->detectVersionFromRequest($request))->toBe('2.1');
    });
});

describe('utility methods', function () {
    test('isSupportedVersion returns true for supported versions', function () {
        expect($this->versionManager->isSupportedVersion('1.0'))->toBeTrue();
        expect($this->versionManager->isSupportedVersion('1.1'))->toBeTrue();
        expect($this->versionManager->isSupportedVersion('2.0'))->toBeTrue();
        expect($this->versionManager->isSupportedVersion('2.1'))->toBeTrue();
    });

    test('isSupportedVersion returns false for unsupported versions', function () {
        expect($this->versionManager->isSupportedVersion('3.0'))->toBeFalse();
        expect($this->versionManager->isSupportedVersion('0.9'))->toBeFalse();
        expect($this->versionManager->isSupportedVersion('invalid'))->toBeFalse();
    });

    test('getSupportedVersions returns all supported versions', function () {
        $versions = $this->versionManager->getSupportedVersions();

        expect($versions)->toBe(['1.0', '1.1', '2', '2.0', '2.1']);
    });

    test('getDefaultVersion returns default version', function () {
        expect($this->versionManager->getDefaultVersion())->toBe('2.0');
    });

    test('getDetectionMethods returns detection methods configuration', function () {
        $methods = $this->versionManager->getDetectionMethods();

        expect($methods)->toHaveKey('header');
        expect($methods)->toHaveKey('query');
        expect($methods)->toHaveKey('path');
        expect($methods)->toHaveKey('media_type');
    });
});
