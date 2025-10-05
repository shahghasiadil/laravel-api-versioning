<?php

namespace ShahGhasiAdil\LaravelApiVersioning\ValueObjects;

class VersionInfo
{
    public function __construct(
        public readonly string $version,
        public readonly bool $isNeutral = false,
        public readonly bool $isDeprecated = false,
        public readonly ?string $deprecationMessage = null,
        public readonly ?string $sunsetDate = null,
        public readonly ?string $replacedBy = null
    ) {}

    /**
     * @return array{version: string, is_neutral: bool, is_deprecated: bool, deprecation_message: string|null, sunset_date: string|null, replaced_by: string|null}
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
        ];
    }
}
