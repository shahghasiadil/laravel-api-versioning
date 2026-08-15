<?php

use ShahGhasiAdil\LaravelApiVersioning\Services\VersionComparator;

beforeEach(function () {
    $this->comparator = new VersionComparator;
});

describe('compare / ordering', function () {
    test('compares versions numerically', function () {
        expect($this->comparator->compare('1.0', '2.0'))->toBeLessThan(0);
        expect($this->comparator->compare('2.0', '1.0'))->toBeGreaterThan(0);
        expect($this->comparator->compare('2.0', '2.0'))->toBe(0);
    });

    test('getHighest and getLowest sort numerically, not lexically', function () {
        expect($this->comparator->getHighest(['1.0', '2.0', '10.0']))->toBe('10.0');
        expect($this->comparator->getLowest(['1.0', '2.0', '10.0']))->toBe('1.0');
    });
});

describe('satisfies: caret (^) operator', function () {
    test('allows any minor/patch within the same major', function () {
        expect($this->comparator->satisfies('2.5', '^2.0'))->toBeTrue();
        expect($this->comparator->satisfies('2.0', '^2.0'))->toBeTrue();
    });

    test('rejects the next major', function () {
        expect($this->comparator->satisfies('3.0', '^2.0'))->toBeFalse();
    });
});

describe('satisfies: tilde (~) operator', function () {
    test('major-only constraint allows any minor/patch within that major', function () {
        // Regression: "~2" used to be miscomputed as ">=2 <2.1" instead of ">=2 <3.0".
        expect($this->comparator->satisfies('2.0', '~2'))->toBeTrue();
        expect($this->comparator->satisfies('2.5', '~2'))->toBeTrue();
        expect($this->comparator->satisfies('2.9', '~2'))->toBeTrue();
    });

    test('major-only constraint rejects the next major', function () {
        expect($this->comparator->satisfies('3.0', '~2'))->toBeFalse();
    });

    test('major.minor constraint allows only the same minor', function () {
        expect($this->comparator->satisfies('2.1', '~2.1'))->toBeTrue();
        expect($this->comparator->satisfies('2.1.5', '~2.1'))->toBeTrue();
    });

    test('major.minor constraint rejects the next minor', function () {
        expect($this->comparator->satisfies('2.2', '~2.1'))->toBeFalse();
        expect($this->comparator->satisfies('3.0', '~2.1'))->toBeFalse();
    });
});

describe('satisfies: comparison operators', function () {
    test('supports >=, <=, >, <, !=, =', function () {
        expect($this->comparator->satisfies('2.0', '>=2.0'))->toBeTrue();
        expect($this->comparator->satisfies('1.9', '>=2.0'))->toBeFalse();
        expect($this->comparator->satisfies('2.0', '<=2.0'))->toBeTrue();
        expect($this->comparator->satisfies('2.1', '>2.0'))->toBeTrue();
        expect($this->comparator->satisfies('1.9', '<2.0'))->toBeTrue();
        expect($this->comparator->satisfies('2.0', '!=1.0'))->toBeTrue();
        expect($this->comparator->satisfies('2.0', '=2.0'))->toBeTrue();
    });
});
