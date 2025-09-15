<?php

use ShahGhasiAdil\LaravelApiVersioning\Attributes\ApiVersion;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\ApiVersionNeutral;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\Deprecated;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\MapToApiVersion;

describe('ApiVersion attribute', function () {
    test('creates attribute with single version string', function () {
        $attribute = new ApiVersion('2.0');

        expect($attribute->versions)->toBe(['2.0']);
    });

    test('creates attribute with version array', function () {
        $attribute = new ApiVersion(['1.0', '1.1', '2.0']);

        expect($attribute->versions)->toBe(['1.0', '1.1', '2.0']);
    });

    test('normalizes single version to array', function () {
        $attribute = new ApiVersion('1.5');

        expect($attribute->versions)->toBeArray();
        expect($attribute->versions)->toHaveCount(1);
        expect($attribute->versions[0])->toBe('1.5');
    });

    test('preserves version order', function () {
        $versions = ['2.1', '1.0', '2.0', '1.1'];
        $attribute = new ApiVersion($versions);

        expect($attribute->versions)->toBe($versions);
    });

    test('handles empty version array', function () {
        $attribute = new ApiVersion([]);

        expect($attribute->versions)->toBe([]);
    });

    test('handles numeric versions', function () {
        $attribute = new ApiVersion([1, 2, 3]);

        expect($attribute->versions)->toBe([1, 2, 3]);
    });
});

describe('ApiVersionNeutral attribute', function () {
    test('creates neutral attribute successfully', function () {
        $attribute = new ApiVersionNeutral;

        expect($attribute)->toBeInstanceOf(ApiVersionNeutral::class);
    });

    test('is a marker attribute with no properties', function () {
        $attribute = new ApiVersionNeutral;

        $reflection = new ReflectionClass($attribute);
        $properties = $reflection->getProperties();

        expect($properties)->toHaveCount(0);
    });
});

describe('Deprecated attribute', function () {
    test('creates with all parameters', function () {
        $attribute = new Deprecated(
            message: 'This endpoint is deprecated',
            sunsetDate: '2025-12-31',
            replacedBy: '3.0'
        );

        expect($attribute->message)->toBe('This endpoint is deprecated');
        expect($attribute->sunsetDate)->toBe('2025-12-31');
        expect($attribute->replacedBy)->toBe('3.0');
    });

    test('creates with minimal parameters', function () {
        $attribute = new Deprecated;

        expect($attribute->message)->toBeNull();
        expect($attribute->sunsetDate)->toBeNull();
        expect($attribute->replacedBy)->toBeNull();
    });

    test('creates with only message', function () {
        $attribute = new Deprecated(message: 'Use v2.0 instead');

        expect($attribute->message)->toBe('Use v2.0 instead');
        expect($attribute->sunsetDate)->toBeNull();
        expect($attribute->replacedBy)->toBeNull();
    });

    test('creates with only sunset date', function () {
        $attribute = new Deprecated(sunsetDate: '2024-06-30');

        expect($attribute->message)->toBeNull();
        expect($attribute->sunsetDate)->toBe('2024-06-30');
        expect($attribute->replacedBy)->toBeNull();
    });

    test('creates with only replacement version', function () {
        $attribute = new Deprecated(replacedBy: '2.1');

        expect($attribute->message)->toBeNull();
        expect($attribute->sunsetDate)->toBeNull();
        expect($attribute->replacedBy)->toBe('2.1');
    });

    test('handles empty strings', function () {
        $attribute = new Deprecated(
            message: '',
            sunsetDate: '',
            replacedBy: ''
        );

        expect($attribute->message)->toBe('');
        expect($attribute->sunsetDate)->toBe('');
        expect($attribute->replacedBy)->toBe('');
    });
});

describe('MapToApiVersion attribute', function () {
    test('creates with single version string', function () {
        $attribute = new MapToApiVersion('2.0');

        expect($attribute->versions)->toBe(['2.0']);
    });

    test('creates with version array', function () {
        $attribute = new MapToApiVersion(['1.0', '2.0', '2.1']);

        expect($attribute->versions)->toBe(['1.0', '2.0', '2.1']);
    });

    test('normalizes single version to array', function () {
        $attribute = new MapToApiVersion('1.5');

        expect($attribute->versions)->toBeArray();
        expect($attribute->versions)->toBe(['1.5']);
    });

    test('handles empty version array', function () {
        $attribute = new MapToApiVersion([]);

        expect($attribute->versions)->toBe([]);
    });

    test('preserves version order', function () {
        $versions = ['2.1', '1.0', '2.0'];
        $attribute = new MapToApiVersion($versions);

        expect($attribute->versions)->toBe($versions);
    });
});

describe('attribute usage on classes', function () {
    test('ApiVersion can be applied to classes', function () {
        $class = new #[ApiVersion(['1.0', '2.0'])] class {};

        $reflection = new ReflectionClass($class);
        $attributes = $reflection->getAttributes(ApiVersion::class);

        expect($attributes)->toHaveCount(1);

        $attribute = $attributes[0]->newInstance();
        expect($attribute->versions)->toBe(['1.0', '2.0']);
    });

    test('ApiVersionNeutral can be applied to classes', function () {
        $class = new #[ApiVersionNeutral] class {};

        $reflection = new ReflectionClass($class);
        $attributes = $reflection->getAttributes(ApiVersionNeutral::class);

        expect($attributes)->toHaveCount(1);
    });

    test('Deprecated can be applied to classes', function () {
        $class = new #[Deprecated(message: 'Old controller', sunsetDate: '2025-01-01')] class {};

        $reflection = new ReflectionClass($class);
        $attributes = $reflection->getAttributes(Deprecated::class);

        expect($attributes)->toHaveCount(1);

        $attribute = $attributes[0]->newInstance();
        expect($attribute->message)->toBe('Old controller');
        expect($attribute->sunsetDate)->toBe('2025-01-01');
    });

    test('multiple attributes can be applied to same class', function () {
        $class = new #[ApiVersion('1.0')] #[Deprecated(message: 'Deprecated')] class {};

        $reflection = new ReflectionClass($class);

        $versionAttributes = $reflection->getAttributes(ApiVersion::class);
        $deprecatedAttributes = $reflection->getAttributes(Deprecated::class);

        expect($versionAttributes)->toHaveCount(1);
        expect($deprecatedAttributes)->toHaveCount(1);
    });
});

describe('attribute usage on methods', function () {
    test('MapToApiVersion can be applied to methods', function () {
        $class = new class
        {
            #[MapToApiVersion(['2.0', '2.1'])]
            public function testMethod() {}
        };

        $reflection = new ReflectionClass($class);
        $method = $reflection->getMethod('testMethod');
        $attributes = $method->getAttributes(MapToApiVersion::class);

        expect($attributes)->toHaveCount(1);

        $attribute = $attributes[0]->newInstance();
        expect($attribute->versions)->toBe(['2.0', '2.1']);
    });

    test('multiple method attributes supported', function () {
        $class = new class
        {
            #[MapToApiVersion('2.0')]
            #[Deprecated(message: 'Use newMethod instead')]
            public function oldMethod() {}
        };

        $reflection = new ReflectionClass($class);
        $method = $reflection->getMethod('oldMethod');

        $mapAttributes = $method->getAttributes(MapToApiVersion::class);
        $deprecatedAttributes = $method->getAttributes(Deprecated::class);

        expect($mapAttributes)->toHaveCount(1);
        expect($deprecatedAttributes)->toHaveCount(1);
    });
});

describe('edge cases and validation', function () {
    test('handles mixed type version arrays', function () {
        $attribute = new ApiVersion(['1.0', 2, '3.0', 4.0]);

        expect($attribute->versions)->toBe(['1.0', 2, '3.0', 4.0]);
    });

    test('handles null values in constructor', function () {
        $deprecatedAttribute = new Deprecated(
            message: null,
            sunsetDate: null,
            replacedBy: null
        );

        expect($deprecatedAttribute->message)->toBeNull();
        expect($deprecatedAttribute->sunsetDate)->toBeNull();
        expect($deprecatedAttribute->replacedBy)->toBeNull();
    });

    test('preserves whitespace in strings', function () {
        $attribute = new Deprecated(
            message: '  This has spaces  ',
            sunsetDate: ' 2025-01-01 ',
            replacedBy: ' 2.0 '
        );

        expect($attribute->message)->toBe('  This has spaces  ');
        expect($attribute->sunsetDate)->toBe(' 2025-01-01 ');
        expect($attribute->replacedBy)->toBe(' 2.0 ');
    });
});
