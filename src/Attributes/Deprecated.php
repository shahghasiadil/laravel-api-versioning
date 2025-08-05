<?php

namespace ShahGhasiAdil\LaravelApiVersioning\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Deprecated
{
    public function __construct(
        public readonly ?string $message = null,
        public readonly ?string $sunsetDate = null,
        public readonly ?string $replacedBy = null
    ) {}
}
