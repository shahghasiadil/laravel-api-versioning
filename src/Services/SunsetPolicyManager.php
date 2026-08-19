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
        /** @var array<string, mixed> $policies */
        $policies = config('api-versioning.sunset_policies', []);

        /** @var mixed $policy */
        $policy = $policies[$version] ?? null;

        if (is_array($policy)) {
            return $this->buildFromConfig($policy);
        }

        if ($attributeSunsetDate !== null) {
            $date = $this->parseDate($attributeSunsetDate);

            return $date !== null ? new SunsetPolicy($date) : null;
        }

        return null;
    }

    /**
     * @param  array<array-key, mixed>  $policy
     */
    private function buildFromConfig(array $policy): SunsetPolicy
    {
        /** @var mixed $dateRaw */
        $dateRaw = $policy['date'] ?? null;
        $date = is_string($dateRaw) ? $this->parseDate($dateRaw) : null;

        $links = [];

        /** @var mixed $linkRaw */
        $linkRaw = $policy['link'] ?? null;
        if (is_string($linkRaw) && $linkRaw !== '') {
            /** @var mixed $typeRaw */
            $typeRaw = $policy['link_type'] ?? null;
            /** @var mixed $titleRaw */
            $titleRaw = $policy['link_title'] ?? null;

            $links[] = [
                'url' => $linkRaw,
                'type' => is_string($typeRaw) ? $typeRaw : null,
                'title' => is_string($titleRaw) ? $titleRaw : null,
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
