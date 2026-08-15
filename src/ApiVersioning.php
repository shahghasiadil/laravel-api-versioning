<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning;

use Closure;
use ShahGhasiAdil\LaravelApiVersioning\Conventions\ConventionBuilder;
use ShahGhasiAdil\LaravelApiVersioning\Conventions\ConventionRegistry;

/**
 * Entry point for the fluent conventions API: register versions for
 * controllers, actions, or URI patterns you don't own (vendor/generated
 * controllers, closure routes) without touching them.
 *
 * ```php
 * // AppServiceProvider::boot()
 * ApiVersioning::conventions(function (ConventionBuilder $convention): void {
 *     $convention->controller(PassportTokenController::class)
 *         ->hasApiVersion('1.0')
 *         ->hasDeprecatedApiVersion('0.9');
 *
 *     $convention->route('api/webhooks/*')->isApiVersionNeutral();
 * });
 * ```
 *
 * Conventions merge with #[ApiVersion]/#[MapToApiVersion]/#[ApiVersionNeutral]
 * attributes; attributes always win when a class/method carries any.
 */
final class ApiVersioning
{
    /**
     * @param  Closure(ConventionBuilder): void  $callback
     */
    public static function conventions(Closure $callback): void
    {
        /** @var ConventionRegistry $registry */
        $registry = app(ConventionRegistry::class);

        $callback(new ConventionBuilder($registry));
    }
}
