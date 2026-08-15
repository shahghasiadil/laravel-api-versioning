<?php

use Illuminate\Http\Request;
use ShahGhasiAdil\LaravelApiVersioning\ValueObjects\VersionInfo;

test('apiVersion returns null when nothing was resolved', function () {
    $request = Request::create('/api/users');

    expect($request->apiVersion())->toBeNull();
});

test('apiVersion returns the resolved version string', function () {
    $request = Request::create('/api/users');
    $request->attributes->set('api_version', '2.0');

    expect($request->apiVersion())->toBe('2.0');
});

test('apiVersionInfo returns null when nothing was resolved', function () {
    $request = Request::create('/api/users');

    expect($request->apiVersionInfo())->toBeNull();
});

test('apiVersionInfo returns the resolved VersionInfo instance', function () {
    $request = Request::create('/api/users');
    $versionInfo = new VersionInfo(version: '2.0', isDeprecated: true);
    $request->attributes->set('api_version_info', $versionInfo);

    expect($request->apiVersionInfo())->toBe($versionInfo);
});

test('isApiVersionDeprecated is false when nothing was resolved', function () {
    $request = Request::create('/api/users');

    expect($request->isApiVersionDeprecated())->toBeFalse();
});

test('isApiVersionDeprecated reflects the resolved VersionInfo', function () {
    $request = Request::create('/api/users');
    $request->attributes->set('api_version_info', new VersionInfo(version: '1.0', isDeprecated: true));

    expect($request->isApiVersionDeprecated())->toBeTrue();
});

test('isApiVersionDeprecated is false for a non-deprecated resolved version', function () {
    $request = Request::create('/api/users');
    $request->attributes->set('api_version_info', new VersionInfo(version: '2.0', isDeprecated: false));

    expect($request->isApiVersionDeprecated())->toBeFalse();
});
