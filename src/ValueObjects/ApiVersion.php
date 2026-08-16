<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;
use Stringable;

/**
 * A parsed API version: an optional date-based group version, an optional
 * major/minor pair, and an optional status (e.g. "beta", "rc").
 *
 * Modeled after aspnet-api-versioning's `ApiVersion`
 * (`Asp.Versioning.Abstractions.ApiVersion`): versions compare by group
 * date, then major, then minor, then status -- where a *null* status
 * sorts after any real status (a release is "newer" than its own
 * prerelease), and a missing minor is treated as `0` so `'2'` and `'2.0'`
 * compare equal.
 *
 * Accepted textual forms: `1`, `1.0`, `1.0-beta`, `2024-11-01`,
 * `2024-11-01.1-rc`.
 */
final readonly class ApiVersion implements Stringable
{
    private const PATTERN = '/^(?<group>\d{4}-\d{2}-\d{2})?(?:\.?(?<major>\d+)(?:\.(?<minor>\d+))?)?(?:-(?<status>[A-Za-z][A-Za-z0-9]*))?$/';

    public function __construct(
        public ?DateTimeImmutable $groupVersion = null,
        public ?int $major = null,
        public ?int $minor = null,
        public ?string $status = null,
    ) {}

    /**
     * @throws InvalidArgumentException When $text isn't a validly formatted version.
     */
    public static function parse(string $text): self
    {
        $version = self::tryParse($text);

        if ($version === null) {
            throw new InvalidArgumentException("'{$text}' is not a validly formatted API version.");
        }

        return $version;
    }

    public static function tryParse(string $text): ?self
    {
        $text = trim($text);

        if ($text === '' || preg_match(self::PATTERN, $text, $matches) !== 1) {
            return null;
        }

        // Reject a match that captured nothing at all (e.g. a lone '-').
        if (($matches['group'] ?? '') === '' && ($matches['major'] ?? '') === '' && ($matches['status'] ?? '') === '') {
            return null;
        }

        $group = null;

        if (($matches['group'] ?? '') !== '') {
            $parsedGroup = DateTimeImmutable::createFromFormat('!Y-m-d', $matches['group']);
            $group = $parsedGroup !== false ? $parsedGroup : null;
        }

        return new self(
            groupVersion: $group,
            major: ($matches['major'] ?? '') !== '' ? (int) $matches['major'] : null,
            minor: ($matches['minor'] ?? '') !== '' ? (int) $matches['minor'] : null,
            status: ($matches['status'] ?? '') !== '' ? $matches['status'] : null,
        );
    }

    /**
     * @return int Negative if this version precedes $other, positive if it
     *             follows, 0 if equal.
     */
    public function compareTo(self $other): int
    {
        $groupComparison = $this->compareGroupVersions($other);
        if ($groupComparison !== 0) {
            return $groupComparison;
        }

        $majorComparison = ($this->major ?? 0) <=> ($other->major ?? 0);
        if ($majorComparison !== 0) {
            return $majorComparison;
        }

        $minorComparison = ($this->minor ?? 0) <=> ($other->minor ?? 0);
        if ($minorComparison !== 0) {
            return $minorComparison;
        }

        return $this->compareStatuses($other);
    }

    public function equals(self $other): bool
    {
        return $this->compareTo($other) === 0;
    }

    public function isPrerelease(): bool
    {
        return $this->status !== null;
    }

    private function compareGroupVersions(self $other): int
    {
        if ($this->groupVersion === null && $other->groupVersion === null) {
            return 0;
        }

        if ($this->groupVersion === null) {
            return -1;
        }

        if ($other->groupVersion === null) {
            return 1;
        }

        return $this->groupVersion->getTimestamp() <=> $other->groupVersion->getTimestamp();
    }

    /**
     * A null status sorts after any real status: a stable release is
     * "newer" than any of its own prereleases (1.0-beta < 1.0).
     */
    private function compareStatuses(self $other): int
    {
        if ($this->status === $other->status) {
            return 0;
        }

        if ($this->status === null) {
            return 1;
        }

        if ($other->status === null) {
            return -1;
        }

        return strcmp($this->status, $other->status) <=> 0;
    }

    public function __toString(): string
    {
        $value = $this->groupVersion?->format('Y-m-d') ?? '';

        if ($this->major !== null) {
            $numeric = (string) $this->major;

            if ($this->minor !== null) {
                $numeric .= '.'.$this->minor;
            }

            $value .= $value !== '' ? '.'.$numeric : $numeric;
        }

        if ($this->status !== null) {
            $value .= '-'.$this->status;
        }

        return $value;
    }
}
