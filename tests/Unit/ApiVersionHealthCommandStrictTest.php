<?php

use Illuminate\Support\Facades\Artisan;

test('passes without --strict when only warnings are present', function () {
    config(['api-versioning.detection_methods.header.enabled' => false]);
    config(['api-versioning.detection_methods.query.enabled' => false]);
    config(['api-versioning.detection_methods.path.enabled' => false]);
    config(['api-versioning.detection_methods.media_type.enabled' => false]);

    $exit = Artisan::call('api:version:health');

    expect($exit)->toBe(0);
    expect(Artisan::output())->toContain('No detection methods enabled');
});

test('fails with --strict when warnings are present', function () {
    config(['api-versioning.detection_methods.header.enabled' => false]);
    config(['api-versioning.detection_methods.query.enabled' => false]);
    config(['api-versioning.detection_methods.path.enabled' => false]);
    config(['api-versioning.detection_methods.media_type.enabled' => false]);

    $exit = Artisan::call('api:version:health', ['--strict' => true]);

    expect($exit)->toBe(1);
    expect(Artisan::output())->toContain('warnings present');
});

test('flags version_method_mapping keys that are not supported versions', function () {
    config(['api-versioning.version_method_mapping' => [
        '1.0' => 'toArrayV1',
        '9.9' => 'toArrayV9',
    ]]);

    $exit = Artisan::call('api:version:health');

    expect($exit)->toBe(0);
    expect(Artisan::output())->toContain('version_method_mapping declares versions not in supported_versions');
});

test('flags a cycle in version_inheritance as an error', function () {
    config(['api-versioning.version_inheritance' => [
        '1.0' => '1.1',
        '1.1' => '1.0',
    ]]);

    $exit = Artisan::call('api:version:health');

    expect($exit)->toBe(1);
    expect(Artisan::output())->toContain('version_inheritance contains a cycle');
});
