<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\ValueObjects;

use DateTimeImmutable;
use DateTimeZone;

/**
 * An RFC 8594 sunset policy: the date an API version will stop responding,
 * and optionally one or more RFC 8288 web links pointing to more
 * information about the sunset (a migration guide, a changelog, etc.).
 */
final class SunsetPolicy
{
    /**
     * @param  list<array{url: string, type: string|null, title: string|null}>  $links
     */
    public function __construct(
        public readonly ?DateTimeImmutable $date = null,
        public readonly array $links = [],
    ) {}

    public function hasDate(): bool
    {
        return $this->date !== null;
    }

    public function hasLinks(): bool
    {
        return $this->links !== [];
    }

    /**
     * The date formatted as an RFC 7231 IMF-fixdate, as required for the
     * `Sunset` HTTP header (e.g. "Wed, 30 Jun 2026 23:59:59 GMT").
     */
    public function formattedDate(): ?string
    {
        return $this->date?->setTimezone(new DateTimeZone('GMT'))->format('D, d M Y H:i:s \G\M\T');
    }

    /**
     * The links formatted as RFC 8288 `Link` header values
     * (e.g. `<https://example.com>; rel="sunset"; type="text/html"`).
     *
     * @return list<string>
     */
    public function formattedLinks(): array
    {
        return array_map(function (array $link): string {
            $value = '<'.$link['url'].'>; rel="sunset"';

            if ($link['type'] !== null) {
                $value .= '; type="'.$link['type'].'"';
            }

            if ($link['title'] !== null) {
                $value .= '; title="'.$link['title'].'"';
            }

            return $value;
        }, $this->links);
    }
}
