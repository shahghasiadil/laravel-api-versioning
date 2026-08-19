<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Attributes;

use Attribute;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\Contracts\HasVersionDeprecation;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\Contracts\HasVersions;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class MapToApiVersion implements HasVersionDeprecation, HasVersions
{
    /**
     * @var string[]
     */
    public readonly array $versions;

    /**
     * @param  string|string[]  $versions
     * @param  bool  $deprecated  Whether the versions declared here (and only these) are deprecated.
     * @param  string|null  $sunset  Optional sunset date for the versions declared here.
     * @param  string|null  $replacedBy  Optional replacement version for the versions declared here.
     */
    public function __construct(
        string|array $versions,
        public readonly bool $deprecated = false,
        public readonly ?string $sunset = null,
        public readonly ?string $replacedBy = null,
    ) {
        $this->versions = is_array($versions) ? $versions : [$versions];
    }

    /**
     * @return string[]
     */
    public function getVersions(): array
    {
        return $this->versions;
    }

    public function isDeprecated(): bool
    {
        return $this->deprecated;
    }

    public function getSunsetDate(): ?string
    {
        return $this->sunset;
    }

    public function getReplacedBy(): ?string
    {
        return $this->replacedBy;
    }
}
