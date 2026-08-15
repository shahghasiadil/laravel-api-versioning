<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Services;

use DateTimeImmutable;
use Exception;
use ShahGhasiAdil\LaravelApiVersioning\ValueObjects\SunsetPolicy;

/**
 * Resolves the RFC 8594 sunset policy for an API version.
 *
 * A policy declared in config('api-versioning.sunset_policies') takes
 * precedence, since it can carry a link the attribute-based
 * `sunsetDate` alone cannot. Falling back to the sunset date already
 * resolved from #[Deprecated]/#[ApiVersion] attributes means every
 * deprecated version with a sunset date gets a policy for free, with no
 * config needed.
 */
class SunsetPolicyManager
{
    public function getPolicy(string $version, ?string $attributeSunsetDate = null): ?SunsetPolicy
    {
        /** @var array<string, array<string, mixed>> $policies */
        $policies = config('api-versioning.sunset_policies', []);

        if (isset($policies[$version]) && is_array($policies[$version])) {
            return $this->buildFromConfig($policies[$version]);
        }

        if ($attributeSunsetDate !== null) {
            $date = $this->parseDate($attributeSunsetDate);

            return $date !== null ? new SunsetPolicy($date) : null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $policy
     */
    private function buildFromConfig(array $policy): SunsetPolicy
    {
        $date = isset($policy['date']) ? $this->parseDate((string) $policy['date']) : null;

        $links = [];
        if (isset($policy['link']) && $policy['link'] !== '') {
            $links[] = [
                'url' => (string) $policy['link'],
                'type' => isset($policy['link_type']) ? (string) $policy['link_type'] : null,
                'title' => isset($policy['link_title']) ? (string) $policy['link_title'] : null,
            ];
        }

        return new SunsetPolicy($date, $links);
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }
    }
}
