<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Attributes\Contracts;

interface HasVersions
{
    /**
     * @return string[]
     */
    public function getVersions(): array;
}
