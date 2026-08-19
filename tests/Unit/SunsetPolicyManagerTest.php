<?php

use ShahGhasiAdil\LaravelApiVersioning\Services\SunsetPolicyManager;

beforeEach(function () {
    $this->manager = new SunsetPolicyManager;
    config(['api-versioning.sunset_policies' => []]);
});

test('returns null when nothing is configured and no attribute sunset date is given', function () {
    expect($this->manager->getPolicy('1.0'))->toBeNull();
});

test('falls back to the attribute-resolved sunset date when no config policy exists', function () {
    $policy = $this->manager->getPolicy('1.0', '2026-06-30');

    expect($policy)->not()->toBeNull();
    expect($policy->hasDate())->toBeTrue();
    expect($policy->hasLinks())->toBeFalse();
});

test('returns null from an attribute sunset date it cannot parse', function () {
    expect($this->manager->getPolicy('1.0', 'not-a-date'))->toBeNull();
});

test('a config policy takes precedence over the attribute sunset date', function () {
    config(['api-versioning.sunset_policies' => [
        '1.0' => ['date' => '2027-01-01'],
    ]]);

    $policy = $this->manager->getPolicy('1.0', '2026-06-30');

    expect($policy->formattedDate())->toContain('2027');
});

test('builds a link from a config policy', function () {
    config(['api-versioning.sunset_policies' => [
        '1.0' => [
            'date' => '2026-06-30',
            'link' => 'https://example.com/migrate',
            'link_type' => 'text/html',
            'link_title' => 'Migration guide',
        ],
    ]]);

    $policy = $this->manager->getPolicy('1.0');

    expect($policy->hasDate())->toBeTrue();
    expect($policy->hasLinks())->toBeTrue();
    expect($policy->formattedLinks())->toBe([
        '<https://example.com/migrate>; rel="sunset"; type="text/html"; title="Migration guide"',
    ]);
});

test('a config policy with only a link and no date has no Sunset header data but still has links', function () {
    config(['api-versioning.sunset_policies' => [
        '1.0' => ['link' => 'https://example.com/migrate'],
    ]]);

    $policy = $this->manager->getPolicy('1.0');

    expect($policy->hasDate())->toBeFalse();
    expect($policy->hasLinks())->toBeTrue();
});

test('a config policy independently sunsets a version with no attribute sunset date at all', function () {
    config(['api-versioning.sunset_policies' => [
        '2.0' => ['date' => '2027-12-31'],
    ]]);

    $policy = $this->manager->getPolicy('2.0', null);

    expect($policy)->not()->toBeNull();
    expect($policy->hasDate())->toBeTrue();
});

test('an unconfigured version with no attribute sunset date resolves no policy', function () {
    config(['api-versioning.sunset_policies' => [
        '2.0' => ['date' => '2027-12-31'],
    ]]);

    expect($this->manager->getPolicy('1.0', null))->toBeNull();
});
