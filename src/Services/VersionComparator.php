<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Services;

class VersionComparator
{
    /**
     * Compare two version strings
     *
     * @return int Returns < 0 if $version1 is less than $version2;
     *             > 0 if $version1 is greater than $version2;
     *             0 if they are equal.
     */
    public function compare(string $version1, string $version2): int
    {
        return version_compare($version1, $version2);
    }

    /**
     * Check if version1 is greater than version2
     */
    public function isGreaterThan(string $version1, string $version2): bool
    {
        return $this->compare($version1, $version2) > 0;
    }

    /**
     * Check if version1 is greater than or equal to version2
     */
    public function isGreaterThanOrEqual(string $version1, string $version2): bool
    {
        return $this->compare($version1, $version2) >= 0;
    }

    /**
     * Check if version1 is less than version2
     */
    public function isLessThan(string $version1, string $version2): bool
    {
        return $this->compare($version1, $version2) < 0;
    }

    /**
     * Check if version1 is less than or equal to version2
     */
    public function isLessThanOrEqual(string $version1, string $version2): bool
    {
        return $this->compare($version1, $version2) <= 0;
    }

    /**
     * Check if version1 equals version2
     */
    public function equals(string $version1, string $version2): bool
    {
        return $this->compare($version1, $version2) === 0;
    }

    /**
     * Check if a version is within a range (inclusive)
     */
    public function isBetween(string $version, string $min, string $max): bool
    {
        return $this->isGreaterThanOrEqual($version, $min)
            && $this->isLessThanOrEqual($version, $max);
    }

    /**
     * Get the highest version from an array of versions
     */
    public function getHighest(array $versions): ?string
    {
        if ($versions === []) {
            return null;
        }

        usort($versions, fn ($a, $b) => $this->compare($b, $a));

        return $versions[0];
    }

    /**
     * Get the lowest version from an array of versions
     */
    public function getLowest(array $versions): ?string
    {
        if ($versions === []) {
            return null;
        }

        usort($versions, fn ($a, $b) => $this->compare($a, $b));

        return $versions[0];
    }

    /**
     * Sort versions in ascending order
     *
     * @return string[]
     */
    public function sort(array $versions, bool $descending = false): array
    {
        usort($versions, fn ($a, $b) => $descending
            ? $this->compare($b, $a)
            : $this->compare($a, $b));

        return $versions;
    }

    /**
     * Check if a version satisfies a constraint
     * Supports: =, !=, >, <, >=, <=, ^, ~
     *
     * Examples:
     * - ">=2.0" -> version must be >= 2.0
     * - "^2.0" -> version must be >= 2.0 and < 3.0
     * - "~2.1" -> version must be >= 2.1 and < 2.2
     */
    public function satisfies(string $version, string $constraint): bool
    {
        // Handle caret (^) operator - compatible versions
        if (str_starts_with($constraint, '^')) {
            $baseVersion = ltrim($constraint, '^');
            $parts = explode('.', $baseVersion);
            $majorVersion = $parts[0] ?? '0';
            $nextMajor = ((int) $majorVersion + 1).'.0';

            return $this->isGreaterThanOrEqual($version, $baseVersion)
                && $this->isLessThan($version, $nextMajor);
        }

        // Handle tilde (~) operator - patch-level changes
        if (str_starts_with($constraint, '~')) {
            $baseVersion = ltrim($constraint, '~');
            $parts = explode('.', $baseVersion);
            $majorVersion = $parts[0] ?? '0';
            $minorVersion = $parts[1] ?? '0';
            $nextMinor = $majorVersion.'.'.((int) $minorVersion + 1);

            return $this->isGreaterThanOrEqual($version, $baseVersion)
                && $this->isLessThan($version, $nextMinor);
        }

        // Handle comparison operators
        if (preg_match('/^(>=|<=|>|<|!=|=)(.+)$/', $constraint, $matches)) {
            $operator = $matches[1];
            $compareVersion = $matches[2];

            return match ($operator) {
                '>=' => $this->isGreaterThanOrEqual($version, $compareVersion),
                '<=' => $this->isLessThanOrEqual($version, $compareVersion),
                '>' => $this->isGreaterThan($version, $compareVersion),
                '<' => $this->isLessThan($version, $compareVersion),
                '!=' => ! $this->equals($version, $compareVersion),
                '=' => $this->equals($version, $compareVersion),
                default => false,
            };
        }

        // Exact match
        return $this->equals($version, $constraint);
    }
}
