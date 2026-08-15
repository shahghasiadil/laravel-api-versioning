<?php

use Illuminate\Http\Request;
use ShahGhasiAdil\LaravelApiVersioning\Events\ApiVersionResolved;
use ShahGhasiAdil\LaravelApiVersioning\Events\DeprecatedApiVersionUsed;
use ShahGhasiAdil\LaravelApiVersioning\ValueObjects\VersionInfo;

test('ApiVersionResolved carries the request and version info', function () {
    $request = Request::create('/api/users');
    $versionInfo = new VersionInfo(version: '2.0');

    $event = new ApiVersionResolved($request, $versionInfo);

    expect($event->request)->toBe($request);
    expect($event->versionInfo)->toBe($versionInfo);
});

test('DeprecatedApiVersionUsed carries the request and version info', function () {
    $request = Request::create('/api/users');
    $versionInfo = new VersionInfo(version: '1.0', isDeprecated: true);

    $event = new DeprecatedApiVersionUsed($request, $versionInfo);

    expect($event->request)->toBe($request);
    expect($event->versionInfo)->toBe($versionInfo);
});
