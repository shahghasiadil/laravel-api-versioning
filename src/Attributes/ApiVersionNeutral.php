<?php

namespace ShahGhasiAdil\LaravelApiVersioning\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class ApiVersionNeutral
{
    // This attribute marks controllers/methods as version-neutral
    // They will respond to all API versions
}
