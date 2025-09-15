<?php

use ShahGhasiAdil\LaravelApiVersioning\ValueObjects\VersionInfo;

describe('VersionInfo construction', function () {
    test('creates with all parameters', function () {
        $versionInfo = new VersionInfo(
            version: '2.0',
            isNeutral: true,
            isDeprecated: true,
            deprecationMessage: 'Use version 3.0 instead',
            sunsetDate: '2025-12-31',
            replacedBy: '3.0'
        );

        expect($versionInfo->version)->toBe('2.0');
        expect($versionInfo->isNeutral)->toBeTrue();
        expect($versionInfo->isDeprecated)->toBeTrue();
        expect($versionInfo->deprecationMessage)->toBe('Use version 3.0 instead');
        expect($versionInfo->sunsetDate)->toBe('2025-12-31');
        expect($versionInfo->replacedBy)->toBe('3.0');
    });

    test('creates with minimal parameters using defaults', function () {
        $versionInfo = new VersionInfo('1.0');

        expect($versionInfo->version)->toBe('1.0');
        expect($versionInfo->isNeutral)->toBeFalse();
        expect($versionInfo->isDeprecated)->toBeFalse();
        expect($versionInfo->deprecationMessage)->toBeNull();
        expect($versionInfo->sunsetDate)->toBeNull();
        expect($versionInfo->replacedBy)->toBeNull();
    });

    test('creates non-deprecated version', function () {
        $versionInfo = new VersionInfo(
            version: '2.1',
            isNeutral: false,
            isDeprecated: false
        );

        expect($versionInfo->version)->toBe('2.1');
        expect($versionInfo->isNeutral)->toBeFalse();
        expect($versionInfo->isDeprecated)->toBeFalse();
        expect($versionInfo->deprecationMessage)->toBeNull();
        expect($versionInfo->sunsetDate)->toBeNull();
        expect($versionInfo->replacedBy)->toBeNull();
    });

    test('creates neutral version', function () {
        $versionInfo = new VersionInfo(
            version: '1.5',
            isNeutral: true
        );

        expect($versionInfo->version)->toBe('1.5');
        expect($versionInfo->isNeutral)->toBeTrue();
        expect($versionInfo->isDeprecated)->toBeFalse();
    });

    test('creates deprecated version with partial information', function () {
        $versionInfo = new VersionInfo(
            version: '1.0',
            isDeprecated: true,
            sunsetDate: '2025-06-30'
        );

        expect($versionInfo->version)->toBe('1.0');
        expect($versionInfo->isDeprecated)->toBeTrue();
        expect($versionInfo->sunsetDate)->toBe('2025-06-30');
        expect($versionInfo->deprecationMessage)->toBeNull();
        expect($versionInfo->replacedBy)->toBeNull();
    });
});

describe('toArray conversion', function () {
    test('converts complete version info to array', function () {
        $versionInfo = new VersionInfo(
            version: '2.0',
            isNeutral: true,
            isDeprecated: true,
            deprecationMessage: 'Please migrate to v3.0',
            sunsetDate: '2025-12-31',
            replacedBy: '3.0'
        );

        $array = $versionInfo->toArray();

        expect($array)->toBe([
            'version' => '2.0',
            'is_neutral' => true,
            'is_deprecated' => true,
            'deprecation_message' => 'Please migrate to v3.0',
            'sunset_date' => '2025-12-31',
            'replaced_by' => '3.0',
        ]);
    });

    test('converts minimal version info to array with nulls', function () {
        $versionInfo = new VersionInfo('1.0');

        $array = $versionInfo->toArray();

        expect($array)->toBe([
            'version' => '1.0',
            'is_neutral' => false,
            'is_deprecated' => false,
            'deprecation_message' => null,
            'sunset_date' => null,
            'replaced_by' => null,
        ]);
    });

    test('converts neutral version to array', function () {
        $versionInfo = new VersionInfo(
            version: '2.1',
            isNeutral: true
        );

        $array = $versionInfo->toArray();

        expect($array['version'])->toBe('2.1');
        expect($array['is_neutral'])->toBeTrue();
        expect($array['is_deprecated'])->toBeFalse();
        expect($array['deprecation_message'])->toBeNull();
        expect($array['sunset_date'])->toBeNull();
        expect($array['replaced_by'])->toBeNull();
    });

    test('converts deprecated version to array', function () {
        $versionInfo = new VersionInfo(
            version: '1.5',
            isDeprecated: true,
            deprecationMessage: 'Legacy version',
            replacedBy: '2.0'
        );

        $array = $versionInfo->toArray();

        expect($array['version'])->toBe('1.5');
        expect($array['is_neutral'])->toBeFalse();
        expect($array['is_deprecated'])->toBeTrue();
        expect($array['deprecation_message'])->toBe('Legacy version');
        expect($array['sunset_date'])->toBeNull();
        expect($array['replaced_by'])->toBe('2.0');
    });
});

describe('readonly properties', function () {
    test('properties are readonly and cannot be modified', function () {
        $versionInfo = new VersionInfo('1.0');

        expect(fn() => $versionInfo->version = '2.0')
            ->toThrow(Error::class);
    });

    test('can access all properties as readonly', function () {
        $versionInfo = new VersionInfo(
            version: '2.0',
            isNeutral: true,
            isDeprecated: true,
            deprecationMessage: 'Test message',
            sunsetDate: '2025-01-01',
            replacedBy: '3.0'
        );

        // All properties should be accessible
        expect($versionInfo->version)->toBeString();
        expect($versionInfo->isNeutral)->toBeBool();
        expect($versionInfo->isDeprecated)->toBeBool();
        expect($versionInfo->deprecationMessage)->toBeString();
        expect($versionInfo->sunsetDate)->toBeString();
        expect($versionInfo->replacedBy)->toBeString();
    });
});

describe('edge cases', function () {
    test('handles empty string version', function () {
        $versionInfo = new VersionInfo('');

        expect($versionInfo->version)->toBe('');
        expect($versionInfo->toArray()['version'])->toBe('');
    });

    test('handles empty strings for optional parameters', function () {
        $versionInfo = new VersionInfo(
            version: '1.0',
            isDeprecated: true,
            deprecationMessage: '',
            sunsetDate: '',
            replacedBy: ''
        );

        expect($versionInfo->deprecationMessage)->toBe('');
        expect($versionInfo->sunsetDate)->toBe('');
        expect($versionInfo->replacedBy)->toBe('');

        $array = $versionInfo->toArray();
        expect($array['deprecation_message'])->toBe('');
        expect($array['sunset_date'])->toBe('');
        expect($array['replaced_by'])->toBe('');
    });

    test('handles special characters in version strings', function () {
        $versionInfo = new VersionInfo(
            version: 'v1.0-beta+build.123',
            deprecationMessage: 'Version with special chars: @#$%',
            sunsetDate: '2025-12-31T23:59:59Z',
            replacedBy: 'v2.0-stable'
        );

        expect($versionInfo->version)->toBe('v1.0-beta+build.123');
        expect($versionInfo->deprecationMessage)->toBe('Version with special chars: @#$%');
        expect($versionInfo->sunsetDate)->toBe('2025-12-31T23:59:59Z');
        expect($versionInfo->replacedBy)->toBe('v2.0-stable');
    });

    test('handles both neutral and deprecated flags set', function () {
        $versionInfo = new VersionInfo(
            version: '1.0',
            isNeutral: true,
            isDeprecated: true,
            deprecationMessage: 'Neutral but deprecated'
        );

        expect($versionInfo->isNeutral)->toBeTrue();
        expect($versionInfo->isDeprecated)->toBeTrue();
        expect($versionInfo->deprecationMessage)->toBe('Neutral but deprecated');

        $array = $versionInfo->toArray();
        expect($array['is_neutral'])->toBeTrue();
        expect($array['is_deprecated'])->toBeTrue();
    });

    test('handles long deprecation messages', function () {
        $longMessage = str_repeat('This is a very long deprecation message. ', 50);

        $versionInfo = new VersionInfo(
            version: '1.0',
            isDeprecated: true,
            deprecationMessage: $longMessage
        );

        expect($versionInfo->deprecationMessage)->toBe($longMessage);
        expect($versionInfo->toArray()['deprecation_message'])->toBe($longMessage);
    });
});

describe('object comparison and equality', function () {
    test('objects with same values are considered equal', function () {
        $versionInfo1 = new VersionInfo(
            version: '1.0',
            isDeprecated: true,
            deprecationMessage: 'Same message'
        );

        $versionInfo2 = new VersionInfo(
            version: '1.0',
            isDeprecated: true,
            deprecationMessage: 'Same message'
        );

        expect($versionInfo1->toArray())->toBe($versionInfo2->toArray());
    });

    test('objects with different values are not equal', function () {
        $versionInfo1 = new VersionInfo('1.0');
        $versionInfo2 = new VersionInfo('2.0');

        expect($versionInfo1->toArray())->not()->toBe($versionInfo2->toArray());
    });

    test('can be used in arrays and collections', function () {
        $versions = [
            new VersionInfo('1.0'),
            new VersionInfo('2.0', isDeprecated: true),
            new VersionInfo('3.0', isNeutral: true),
        ];

        expect($versions)->toHaveCount(3);
        expect($versions[0]->version)->toBe('1.0');
        expect($versions[1]->isDeprecated)->toBeTrue();
        expect($versions[2]->isNeutral)->toBeTrue();
    });
});