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

    /**
     * Check if current API version is greater than the given version
     */
    protected function isVersionGreaterThan(string $version): bool
    {
        $currentVersion = $this->getCurrentApiVersion();
        if ($currentVersion === null) {
            return false;
        }

        return app(\ShahGhasiAdil\LaravelApiVersioning\Services\VersionComparator::class)
            ->isGreaterThan($currentVersion, $version);
    }

    /**
     * Check if current API version is greater than or equal to the given version
     */
    protected function isVersionGreaterThanOrEqual(string $version): bool
    {
        $currentVersion = $this->getCurrentApiVersion();
        if ($currentVersion === null) {
            return false;
        }

        return app(\ShahGhasiAdil\LaravelApiVersioning\Services\VersionComparator::class)
            ->isGreaterThanOrEqual($currentVersion, $version);
    }

    /**
     * Check if current API version is less than the given version
     */
    protected function isVersionLessThan(string $version): bool
    {
        $currentVersion = $this->getCurrentApiVersion();
        if ($currentVersion === null) {
            return false;
        }

        return app(\ShahGhasiAdil\LaravelApiVersioning\Services\VersionComparator::class)
            ->isLessThan($currentVersion, $version);
    }

    /**
     * Check if current API version is less than or equal to the given version
     */
    protected function isVersionLessThanOrEqual(string $version): bool
    {
        $currentVersion = $this->getCurrentApiVersion();
        if ($currentVersion === null) {
            return false;
        }

        return app(\ShahGhasiAdil\LaravelApiVersioning\Services\VersionComparator::class)
            ->isLessThanOrEqual($currentVersion, $version);
    }

    /**
     * Check if current API version is between two versions (inclusive)
     */
    protected function isVersionBetween(string $min, string $max): bool
    {
        $currentVersion = $this->getCurrentApiVersion();
        if ($currentVersion === null) {
            return false;
        }

        return app(\ShahGhasiAdil\LaravelApiVersioning\Services\VersionComparator::class)
            ->isBetween($currentVersion, $min, $max);
    }
}
