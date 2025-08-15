<?php

namespace ShahGhasiAdil\LaravelApiVersioning\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class ApiVersion
{
    public array $versions;

    public function __construct(string|array $versions)
    {
        $this->versions = is_array($versions) ? $versions : [$versions];
    }

    public function getVersions(): array
    {
        return $this->versions;
    }

    public function hasVersion(string $version): bool
    {
        return in_array($version, $this->versions);
    }
}
