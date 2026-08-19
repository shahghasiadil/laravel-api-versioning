<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use ShahGhasiAdil\LaravelApiVersioning\ValueObjects\VersionInfo;

/**
 * Dispatched by the versioning middleware whenever it successfully resolves
 * an API version for a request, including version-neutral routes. Listen
 * for this to track which versions clients actually use, independent of
 * whether that version happens to be deprecated.
 */
class ApiVersionResolved
{
    use Dispatchable;

    public function __construct(
        public readonly Request $request,
        public readonly VersionInfo $versionInfo,
    ) {}
}
