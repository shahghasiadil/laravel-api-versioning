<?php

namespace ShahGhasiAdil\LaravelApiVersioning\ValueObjects;

class VersionInfo
{
    /**
     * @param  string[]|null  $routeVersions
     */
    public function __construct(
        public readonly string $version,
        public readonly bool $isNeutral = false,
        public readonly bool $isDeprecated = false,
        public readonly ?string $deprecationMessage = null,
        public readonly ?string $sunsetDate = null,
        public readonly ?string $replacedBy = null,
        public readonly ?array $routeVersions = null,
    ) {}

    /**
     * @return array{version: string, is_neutral: bool, is_deprecated: bool, deprecation_message: string|null, sunset_date: string|null, replaced_by: string|null, route_versions: string[]|null}
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'is_neutral' => $this->isNeutral,
            'is_deprecated' => $this->isDeprecated,
            'deprecation_message' => $this->deprecationMessage,
            'sunset_date' => $this->sunsetDate,
            'replaced_by' => $this->replacedBy,
            'route_versions' => $this->routeVersions,
        ];
    }

    /**
     * Reconstructs an instance from {@see toArray()}'s output, so callers
     * (namely {@see \ShahGhasiAdil\LaravelApiVersioning\Services\AttributeVersionResolver})
     * can cache the plain array instead of the object itself -- a cache
     * store never needs to unserialize this class directly.
     *
     * @param  array{version: string, is_neutral: bool, is_deprecated: bool, deprecation_message: string|null, sunset_date: string|null, replaced_by: string|null, route_versions: string[]|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            version: $data['version'],
            isNeutral: $data['is_neutral'],
            isDeprecated: $data['is_deprecated'],
            deprecationMessage: $data['deprecation_message'],
            sunsetDate: $data['sunset_date'],
            replacedBy: $data['replaced_by'],
            routeVersions: $data['route_versions'],
        );
    }
}
