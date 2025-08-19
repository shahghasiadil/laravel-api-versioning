<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Traits;

use ShahGhasiAdil\LaravelApiVersioning\ValueObjects\VersionInfo;

trait HasApiVersionAttributes
{
    protected function getVersionInfo(): ?VersionInfo
    {
        $versionInfo = request()->attributes->get('api_version_info');

        return $versionInfo instanceof VersionInfo ? $versionInfo : null;
    }

    protected function getCurrentApiVersion(): ?string
    {
        $version = request()->attributes->get('api_version');

        return is_string($version) ? $version : null;
    }

    protected function isVersionDeprecated(): bool
    {
        $versionInfo = $this->getVersionInfo();

        return $versionInfo !== null ? $versionInfo->isDeprecated : false;
    }

    protected function isVersionNeutral(): bool
    {
        $versionInfo = $this->getVersionInfo();

        return $versionInfo !== null ? $versionInfo->isNeutral : false;
    }

    protected function getDeprecationMessage(): ?string
    {
        $versionInfo = $this->getVersionInfo();

        return $versionInfo?->deprecationMessage;
    }

    protected function getSunsetDate(): ?string
    {
        $versionInfo = $this->getVersionInfo();

        return $versionInfo?->sunsetDate;
    }

    protected function getReplacedByVersion(): ?string
    {
        $versionInfo = $this->getVersionInfo();

        return $versionInfo?->replacedBy;
    }
}
