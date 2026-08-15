<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\OpenApi;

use ShahGhasiAdil\LaravelApiVersioning\ValueObjects\ApiVersionDescription;

/**
 * Produces a documentation-generator-friendly model of every API version
 * this application serves: which routes implement it, whether it's
 * deprecated, and its sunset policy if any.
 *
 * This is the aspnet-api-versioning `IApiVersionDescriptionProvider`
 * equivalent -- the foundation a Scramble/L5-Swagger adapter (or a hand
 * rolled OpenAPI document builder) would generate one document per version
 * from, instead of re-deriving this information itself.
 */
interface ApiVersionDescriptionProvider
{
    /**
     * @return ApiVersionDescription[]
     */
    public function describe(): array;
}
