<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class MapToApiVersion
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
}
