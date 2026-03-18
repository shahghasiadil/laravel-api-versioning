<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Attributes;

use Attribute;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\Contracts\HasVersions;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class ApiVersion implements HasVersions
{
    /**
     * @var string[]
     */
    public readonly array $versions;

    /**
     * @param  string|string[]  $versions
     */
    public function __construct(string|array $versions)
    {
        $this->versions = is_array($versions) ? $versions : [$versions];
    }

    /**
     * @return string[]
     */
    public function getVersions(): array
    {
        return $this->versions;
    }

    public function hasVersion(string $version): bool
    {
        return in_array($version, $this->versions, true);
    }
}
