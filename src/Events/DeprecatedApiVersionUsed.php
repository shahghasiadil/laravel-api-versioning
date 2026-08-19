<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use ShahGhasiAdil\LaravelApiVersioning\ValueObjects\VersionInfo;

/**
 * Dispatched by the versioning middleware whenever a resolved version is
 * deprecated (via #[Deprecated], or per-version deprecation on
 * #[ApiVersion]/#[MapToApiVersion]). Listen for this to measure real usage
 * of a version before its sunset date arrives -- log it, record a metric,
 * or notify the caller through an out-of-band channel.
 */
class DeprecatedApiVersionUsed
{
    use Dispatchable;

    public function __construct(
        public readonly Request $request,
        public readonly VersionInfo $versionInfo,
    ) {}
}
