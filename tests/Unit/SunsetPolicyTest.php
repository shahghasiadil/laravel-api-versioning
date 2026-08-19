<?php

use ShahGhasiAdil\LaravelApiVersioning\ValueObjects\SunsetPolicy;

test('has no date or links by default', function () {
    $policy = new SunsetPolicy;

    expect($policy->hasDate())->toBeFalse();
    expect($policy->hasLinks())->toBeFalse();
    expect($policy->formattedDate())->toBeNull();
    expect($policy->formattedLinks())->toBe([]);
});

test('formats the date as an RFC 7231 IMF-fixdate in GMT', function () {
    $policy = new SunsetPolicy(new DateTimeImmutable('2026-06-30T23:59:59+02:00'));

    expect($policy->hasDate())->toBeTrue();
    // 23:59:59+02:00 is 21:59:59 UTC
    expect($policy->formattedDate())->toBe('Tue, 30 Jun 2026 21:59:59 GMT');
});

test('formats a link with rel=sunset', function () {
    $policy = new SunsetPolicy(links: [
        ['url' => 'https://example.com/migrate', 'type' => null, 'title' => null],
    ]);

    expect($policy->hasLinks())->toBeTrue();
    expect($policy->formattedLinks())->toBe(['<https://example.com/migrate>; rel="sunset"']);
});

test('formats a link with type and title', function () {
    $policy = new SunsetPolicy(links: [
        ['url' => 'https://example.com/migrate', 'type' => 'text/html', 'title' => 'Migration guide'],
    ]);

    expect($policy->formattedLinks())->toBe([
        '<https://example.com/migrate>; rel="sunset"; type="text/html"; title="Migration guide"',
    ]);
});

test('formats multiple links independently', function () {
    $policy = new SunsetPolicy(links: [
        ['url' => 'https://example.com/a', 'type' => null, 'title' => null],
        ['url' => 'https://example.com/b', 'type' => 'application/json', 'title' => null],
    ]);

    expect($policy->formattedLinks())->toBe([
        '<https://example.com/a>; rel="sunset"',
        '<https://example.com/b>; rel="sunset"; type="application/json"',
    ]);
});
