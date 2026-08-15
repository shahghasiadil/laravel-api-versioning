<?php

use ShahGhasiAdil\LaravelApiVersioning\Exceptions\VersionProblemReason;

test('each reason has a distinct title and code', function () {
    $titles = [];
    $codes = [];

    foreach (VersionProblemReason::cases() as $reason) {
        $titles[] = $reason->title();
        $codes[] = $reason->code();
    }

    expect($titles)->toBe(array_unique($titles));
    expect($codes)->toBe(array_unique($codes));
});

test('codes match the expected machine-readable values', function () {
    expect(VersionProblemReason::Unsupported->code())->toBe('UnsupportedApiVersion');
    expect(VersionProblemReason::Unspecified->code())->toBe('ApiVersionUnspecified');
    expect(VersionProblemReason::Invalid->code())->toBe('InvalidApiVersion');
    expect(VersionProblemReason::Ambiguous->code())->toBe('AmbiguousApiVersion');
});
