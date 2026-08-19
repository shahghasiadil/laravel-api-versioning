<?php

use ShahGhasiAdil\LaravelApiVersioning\ValueObjects\ApiVersion;

describe('parsing', function () {
    test('parses a bare major version', function () {
        $version = ApiVersion::parse('1');

        expect($version->major)->toBe(1);
        expect($version->minor)->toBeNull();
        expect($version->groupVersion)->toBeNull();
        expect($version->status)->toBeNull();
    });

    test('parses major.minor', function () {
        $version = ApiVersion::parse('1.0');

        expect($version->major)->toBe(1);
        expect($version->minor)->toBe(0);
    });

    test('parses a status suffix', function () {
        $version = ApiVersion::parse('1.0-beta');

        expect($version->major)->toBe(1);
        expect($version->minor)->toBe(0);
        expect($version->status)->toBe('beta');
        expect($version->isPrerelease())->toBeTrue();
    });

    test('parses a bare group (date) version', function () {
        $version = ApiVersion::parse('2024-11-01');

        expect($version->groupVersion?->format('Y-m-d'))->toBe('2024-11-01');
        expect($version->major)->toBeNull();
    });

    test('parses a group version with major and status', function () {
        $version = ApiVersion::parse('2024-11-01.1-rc');

        expect($version->groupVersion?->format('Y-m-d'))->toBe('2024-11-01');
        expect($version->major)->toBe(1);
        expect($version->status)->toBe('rc');
    });

    test('tryParse returns null for garbage input', function () {
        expect(ApiVersion::tryParse('not-a-version-!!'))->toBeNull();
        expect(ApiVersion::tryParse(''))->toBeNull();
    });

    test('parse throws for garbage input', function () {
        expect(fn () => ApiVersion::parse('###'))->toThrow(InvalidArgumentException::class);
    });
});

describe('equality and normalization', function () {
    test('"2" and "2.0" are equal', function () {
        $a = ApiVersion::parse('2');
        $b = ApiVersion::parse('2.0');

        expect($a->equals($b))->toBeTrue();
        expect($a->compareTo($b))->toBe(0);
    });
});

describe('ordering', function () {
    test('a prerelease sorts before its stable release', function () {
        $beta = ApiVersion::parse('1.0-beta');
        $stable = ApiVersion::parse('1.0');

        expect($beta->compareTo($stable))->toBeLessThan(0);
        expect($stable->compareTo($beta))->toBeGreaterThan(0);
    });

    test('major takes precedence over minor', function () {
        expect(ApiVersion::parse('2.0')->compareTo(ApiVersion::parse('1.9')))->toBeGreaterThan(0);
    });

    test('group (date) versions order by date', function () {
        $older = ApiVersion::parse('2024-01-01');
        $newer = ApiVersion::parse('2024-02-01');

        expect($older->compareTo($newer))->toBeLessThan(0);
    });
});

describe('__toString', function () {
    test('round-trips simple forms', function () {
        expect((string) ApiVersion::parse('1.0'))->toBe('1.0');
        expect((string) ApiVersion::parse('1'))->toBe('1');
        expect((string) ApiVersion::parse('1.0-beta'))->toBe('1.0-beta');
        expect((string) ApiVersion::parse('2024-11-01'))->toBe('2024-11-01');
    });
});
