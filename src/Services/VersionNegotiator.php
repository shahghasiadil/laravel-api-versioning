<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Services;

class VersionNegotiator
{
    public function __construct(
        private readonly VersionManager $versionManager,
        private readonly VersionComparator $comparator,
        private readonly array $config
    ) {}

    /**
     * Negotiate the best version based on requested version and available versions
     *
     * @param string $requestedVersion
     * @param array<string> $availableVersions
     * @return string|null
     */
    public function negotiate(string $requestedVersion, array $availableVersions): ?string
    {
        $strategy = $this->config['negotiation']['strategy'] ?? 'strict';

        return match ($strategy) {
            'best_match' => $this->findBestMatch($requestedVersion, $availableVersions),
            'latest' => $this->findLatest($availableVersions),
            default => $this->strictMatch($requestedVersion, $availableVersions),
        };
    }

    /**
     * Strict matching - exact version must exist
     */
    private function strictMatch(string $requestedVersion, array $availableVersions): ?string
    {
        return in_array($requestedVersion, $availableVersions, true) ? $requestedVersion : null;
    }

    /**
     * Find the latest/highest version from available versions
     */
    private function findLatest(array $availableVersions): ?string
    {
        return $this->comparator->getHighest($availableVersions);
    }

    /**
     * Find the best matching version
     * Strategy: Find the closest version that is compatible
     */
    private function findBestMatch(string $requestedVersion, array $availableVersions): ?string
    {
        // If exact match exists, use it
        if (in_array($requestedVersion, $availableVersions, true)) {
            return $requestedVersion;
        }

        $preferHigher = $this->config['negotiation']['prefer_higher'] ?? true;

        if ($preferHigher) {
            // Find the lowest version that is higher than requested
            return $this->findLowestHigher($requestedVersion, $availableVersions)
                ?? $this->findHighestLower($requestedVersion, $availableVersions)
                ?? $this->findLatest($availableVersions);
        }

        // Find the highest version that is lower than requested
        return $this->findHighestLower($requestedVersion, $availableVersions)
            ?? $this->findLowestHigher($requestedVersion, $availableVersions)
            ?? $this->findLatest($availableVersions);
    }

    /**
     * Find the lowest version that is higher than the requested version
     */
    private function findLowestHigher(string $requestedVersion, array $availableVersions): ?string
    {
        $higherVersions = array_filter(
            $availableVersions,
            fn ($v) => $this->comparator->isGreaterThan($v, $requestedVersion)
        );

        return $higherVersions !== [] ? $this->comparator->getLowest($higherVersions) : null;
    }

    /**
     * Find the highest version that is lower than the requested version
     */
    private function findHighestLower(string $requestedVersion, array $availableVersions): ?string
    {
        $lowerVersions = array_filter(
            $availableVersions,
            fn ($v) => $this->comparator->isLessThan($v, $requestedVersion)
        );

        return $lowerVersions !== [] ? $this->comparator->getHighest($lowerVersions) : null;
    }

    /**
     * Check if negotiation resulted in a different version
     */
    public function wasNegotiated(string $requested, ?string $negotiated): bool
    {
        return $negotiated !== null && $requested !== $negotiated;
    }

    /**
     * Get negotiation details for response headers
     *
     * @return array<string, string>
     */
    public function getNegotiationHeaders(string $requested, string $served): array
    {
        if ($requested === $served) {
            return [];
        }

        return [
            'X-API-Version-Requested' => $requested,
            'X-API-Version-Served' => $served,
            'X-API-Version-Negotiated' => 'true',
        ];
    }
}
