<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Attributes;

use Attribute;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\Contracts\HasVersionDeprecation;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\Contracts\HasVersions;

/**
 * Declares that a version exists and is implemented elsewhere (another
 * service, another package, a gateway route) rather than on this
 * controller/method. Unlike #[ApiVersion], a request for an advertised
 * version is never resolved by the endpoint carrying this attribute — the
 * version only appears in version-discovery data (getAllVersionsForRoute(),
 * the api-supported-versions/X-API-Route-Versions headers, and the
 * `api:versions` command) so clients and API explorers know it exists.
 *
 * Implementing HasVersions here is safe: AttributeVersionResolver never
 * collects "implemented" versions via a HasVersions instanceof filter (which
 * would wrongly sweep this class in too) -- it fetches #[ApiVersion] and
 * #[MapToApiVersion] explicitly by class instead.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class AdvertiseApiVersions implements HasVersionDeprecation, HasVersions
{
    /**
     * @var string[]
     */
    public readonly array $versions;

    /**
     * @param  string|string[]  $versions
     * @param  bool  $deprecated  Whether the advertised versions declared here are deprecated.
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
