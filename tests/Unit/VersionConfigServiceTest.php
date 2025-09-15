<?php

use ShahGhasiAdil\LaravelApiVersioning\Services\VersionConfigService;

beforeEach(function () {
    $this->service = new VersionConfigService;
});

describe('method resolution for versions', function () {
    test('returns mapped method for configured version', function () {
        config([
            'api-versioning.version_method_mapping' => [
                '1.0' => 'toArrayV1',
                '2.0' => 'toArrayV2',
                '2.1' => 'toArrayV21',
            ],
        ]);

        $service = new VersionConfigService;

        expect($service->getMethodForVersion('1.0'))->toBe('toArrayV1');
        expect($service->getMethodForVersion('2.0'))->toBe('toArrayV2');
        expect($service->getMethodForVersion('2.1'))->toBe('toArrayV21');
    });

    test('returns default method for unmapped version', function () {
        config([
            'api-versioning.version_method_mapping' => [
                '1.0' => 'toArrayV1',
            ],
            'api-versioning.default_method' => 'toArrayDefault',
        ]);

        $service = new VersionConfigService;

        expect($service->getMethodForVersion('3.0'))->toBe('toArrayDefault');
        expect($service->getMethodForVersion('unknown'))->toBe('toArrayDefault');
    });

    test('returns fallback default when no default method configured', function () {
        config([
            'api-versioning.version_method_mapping' => [
                '1.0' => 'toArrayV1',
            ],
        ]);

        $service = new VersionConfigService;

        expect($service->getMethodForVersion('2.0'))->toBe('toArrayDefault');
    });

    test('handles empty version mapping', function () {
        config([
            'api-versioning.version_method_mapping' => [],
            'api-versioning.default_method' => 'customDefault',
        ]);

        $service = new VersionConfigService;

        expect($service->getMethodForVersion('1.0'))->toBe('customDefault');
    });

    test('handles missing version mapping config', function () {
        config([
            'api-versioning' => [
                'default_method' => 'customDefault',
                'version_method_mapping' => [],
            ],
        ]);

        $service = new VersionConfigService;

        expect($service->getMethodForVersion('1.0'))->toBe('customDefault');
    });
});

describe('version inheritance chain resolution', function () {
    test('returns inheritance chain for configured version', function () {
        config([
            'api-versioning.version_inheritance' => [
                '1.2' => '1.1',
                '1.1' => '1.0',
                '2.1' => '2.0',
            ],
        ]);

        $service = new VersionConfigService;

        expect($service->getInheritanceChain('1.2'))->toBe(['1.1', '1.0']);
        expect($service->getInheritanceChain('1.1'))->toBe(['1.0']);
        expect($service->getInheritanceChain('2.1'))->toBe(['2.0']);
    });

    test('returns empty chain for version without inheritance', function () {
        config([
            'api-versioning.version_inheritance' => [
                '1.1' => '1.0',
            ],
        ]);

        $service = new VersionConfigService;

        expect($service->getInheritanceChain('1.0'))->toBe([]);
        expect($service->getInheritanceChain('2.0'))->toBe([]);
    });

    test('handles complex inheritance chains', function () {
        config([
            'api-versioning.version_inheritance' => [
                '1.4' => '1.3',
                '1.3' => '1.2',
                '1.2' => '1.1',
                '1.1' => '1.0',
            ],
        ]);

        $service = new VersionConfigService;

        expect($service->getInheritanceChain('1.4'))->toBe(['1.3', '1.2', '1.1', '1.0']);
        expect($service->getInheritanceChain('1.3'))->toBe(['1.2', '1.1', '1.0']);
        expect($service->getInheritanceChain('1.2'))->toBe(['1.1', '1.0']);
    });

    test('handles empty inheritance config', function () {
        config([
            'api-versioning.version_inheritance' => [],
        ]);

        $service = new VersionConfigService;

        expect($service->getInheritanceChain('1.0'))->toBe([]);
        expect($service->getInheritanceChain('2.0'))->toBe([]);
    });

    test('handles missing inheritance config', function () {
        config([
            'api-versioning' => [],
        ]);

        $service = new VersionConfigService;

        expect($service->getInheritanceChain('1.0'))->toBe([]);
    });

    test('prevents infinite loops in circular inheritance', function () {
        config([
            'api-versioning.version_inheritance' => [
                '1.1' => '1.2',
                '1.2' => '1.1', // Circular reference
            ],
        ]);

        $service = new VersionConfigService;

        // Should not cause infinite loop - will detect cycle and break
        $chain = $service->getInheritanceChain('1.1');

        // The chain should contain '1.2' but stop before the circular reference back to '1.1'
        expect($chain)->toBe(['1.2']);
    });
});

describe('supported versions management', function () {
    test('returns configured supported versions', function () {
        config([
            'api-versioning.supported_versions' => ['1.0', '1.1', '2.0', '2.1'],
        ]);

        $service = new VersionConfigService;

        expect($service->getSupportedVersions())->toBe(['1.0', '1.1', '2.0', '2.1']);
    });

    test('returns empty array when no supported versions configured', function () {
        config([
            'api-versioning.supported_versions' => [],
        ]);

        $service = new VersionConfigService;

        expect($service->getSupportedVersions())->toBe([]);
    });

    test('returns empty array when supported versions config missing', function () {
        config([
            'api-versioning' => [],
        ]);

        $service = new VersionConfigService;

        expect($service->getSupportedVersions())->toBe([]);
    });

    test('preserves order of supported versions', function () {
        config([
            'api-versioning.supported_versions' => ['2.1', '1.0', '2.0', '1.1'],
        ]);

        $service = new VersionConfigService;

        expect($service->getSupportedVersions())->toBe(['2.1', '1.0', '2.0', '1.1']);
    });
});

describe('version mapping validation', function () {
    test('correctly identifies when version has mapping', function () {
        config([
            'api-versioning.version_method_mapping' => [
                '1.0' => 'toArrayV1',
                '2.0' => 'toArrayV2',
            ],
        ]);

        $service = new VersionConfigService;

        expect($service->hasVersionMapping('1.0'))->toBeTrue();
        expect($service->hasVersionMapping('2.0'))->toBeTrue();
        expect($service->hasVersionMapping('3.0'))->toBeFalse();
    });

    test('returns false for unmapped versions', function () {
        config([
            'api-versioning.version_method_mapping' => [
                '1.0' => 'toArrayV1',
            ],
        ]);

        $service = new VersionConfigService;

        expect($service->hasVersionMapping('2.0'))->toBeFalse();
        expect($service->hasVersionMapping('unknown'))->toBeFalse();
    });

    test('handles empty version mapping', function () {
        config([
            'api-versioning.version_method_mapping' => [],
        ]);

        $service = new VersionConfigService;

        expect($service->hasVersionMapping('1.0'))->toBeFalse();
    });

    test('handles missing version mapping config', function () {
        config([
            'api-versioning' => [],
        ]);

        $service = new VersionConfigService;

        expect($service->hasVersionMapping('1.0'))->toBeFalse();
    });
});

describe('configuration debugging and inspection', function () {
    test('returns all version mappings', function () {
        $mappings = [
            '1.0' => 'toArrayV1',
            '2.0' => 'toArrayV2',
            '2.1' => 'toArrayV21',
        ];

        config([
            'api-versioning.version_method_mapping' => $mappings,
        ]);

        $service = new VersionConfigService;

        expect($service->getVersionMappings())->toBe($mappings);
    });

    test('returns empty array when no version mappings', function () {
        config([
            'api-versioning.version_method_mapping' => [],
        ]);

        $service = new VersionConfigService;

        expect($service->getVersionMappings())->toBe([]);
    });

    test('returns all version inheritance mappings', function () {
        $inheritance = [
            '1.1' => '1.0',
            '1.2' => '1.1',
            '2.1' => '2.0',
        ];

        config([
            'api-versioning.version_inheritance' => $inheritance,
        ]);

        $service = new VersionConfigService;

        expect($service->getVersionInheritance())->toBe($inheritance);
    });

    test('returns empty array when no inheritance mappings', function () {
        config([
            'api-versioning.version_inheritance' => [],
        ]);

        $service = new VersionConfigService;

        expect($service->getVersionInheritance())->toBe([]);
    });

    test('returns configured default method', function () {
        config([
            'api-versioning.default_method' => 'customDefaultMethod',
        ]);

        $service = new VersionConfigService;

        expect($service->getDefaultMethod())->toBe('customDefaultMethod');
    });

    test('returns fallback default method when not configured', function () {
        config([
            'api-versioning' => [],
        ]);

        $service = new VersionConfigService;

        expect($service->getDefaultMethod())->toBe('toArrayDefault');
    });
});

describe('edge cases and error handling', function () {
    test('handles null configuration gracefully', function () {
        config(['api-versioning' => null]);

        $service = new VersionConfigService;

        expect($service->getMethodForVersion('1.0'))->toBe('toArrayDefault');
        expect($service->getInheritanceChain('1.0'))->toBe([]);
        expect($service->getSupportedVersions())->toBe([]);
        expect($service->hasVersionMapping('1.0'))->toBeFalse();
        expect($service->getVersionMappings())->toBe([]);
        expect($service->getVersionInheritance())->toBe([]);
        expect($service->getDefaultMethod())->toBe('toArrayDefault');
    });

    test('handles special characters in version strings', function () {
        config([
            'api-versioning.version_method_mapping' => [
                'v1.0-beta' => 'toArrayBeta',
                'v2.0@special' => 'toArraySpecial',
            ],
            'api-versioning.version_inheritance' => [
                'v1.0-beta.2' => 'v1.0-beta',
            ],
        ]);

        $service = new VersionConfigService;

        expect($service->getMethodForVersion('v1.0-beta'))->toBe('toArrayBeta');
        expect($service->getMethodForVersion('v2.0@special'))->toBe('toArraySpecial');
        expect($service->hasVersionMapping('v1.0-beta'))->toBeTrue();
        expect($service->getInheritanceChain('v1.0-beta.2'))->toBe(['v1.0-beta']);
    });

    test('handles empty string versions', function () {
        config([
            'api-versioning.version_method_mapping' => [
                '' => 'toArrayEmpty',
            ],
            'api-versioning.version_inheritance' => [
                ' ' => '',
            ],
        ]);

        $service = new VersionConfigService;

        expect($service->getMethodForVersion(''))->toBe('toArrayEmpty');
        expect($service->hasVersionMapping(''))->toBeTrue();
        expect($service->getInheritanceChain(' '))->toBe(['']);
    });

    test('handles numeric version strings', function () {
        config([
            'api-versioning.version_method_mapping' => [
                '1' => 'toArrayV1',
                '2' => 'toArrayV2',
            ],
            'api-versioning.supported_versions' => ['1', '2', '3'],
        ]);

        $service = new VersionConfigService;

        expect($service->getMethodForVersion('1'))->toBe('toArrayV1');
        expect($service->getSupportedVersions())->toBe(['1', '2', '3']);
        expect($service->hasVersionMapping('1'))->toBeTrue();
    });

    test('handles completely missing api-versioning config', function () {
        config(['api-versioning' => null]);

        $service = new VersionConfigService;

        expect($service->getMethodForVersion('1.0'))->toBe('toArrayDefault');
        expect($service->getInheritanceChain('1.0'))->toBe([]);
        expect($service->getSupportedVersions())->toBe([]);
        expect($service->hasVersionMapping('1.0'))->toBeFalse();
        expect($service->getVersionMappings())->toBe([]);
        expect($service->getVersionInheritance())->toBe([]);
        expect($service->getDefaultMethod())->toBe('toArrayDefault');
    });
});
