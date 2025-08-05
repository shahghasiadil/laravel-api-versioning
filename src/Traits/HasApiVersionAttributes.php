<?php

namespace ShahGhasiAdil\LaravelApiVersioning\Traits;

use ShahGhasiAdil\LaravelApiVersioning\ValueObjects\VersionInfo;

trait HasApiVersionAttributes
{
    protected function getVersionInfo(): ?VersionInfo
    {
        return request()->attributes->get('api_version_info');
    }

    protected function getCurrentApiVersion(): ?string
    {
        return request()->attributes->get('api_version');
    }

    protected function isVersionDeprecated(): bool
    {
        $versionInfo = $this->getVersionInfo();
        return $versionInfo?->isDeprecated ?? false;
    }

    protected function isVersionNeutral(): bool
    {
        $versionInfo = $this->getVersionInfo();
        return $versionInfo?->isNeutral ?? false;
    }

    protected function getDeprecationMessage(): ?string
    {
        $versionInfo = $this->getVersionInfo();
        return $versionInfo?->deprecationMessage;
    }
}
