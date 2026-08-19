<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Conventions;

/**
 * Fluent builder for a single controller action's conventions, obtained via
 * {@see ControllerConvention::action()}.
 */
final class ActionConvention
{
    /**
     * @param  class-string  $class
     */
    public function __construct(
        private readonly ConventionRegistry $registry,
        private readonly string $class,
        private readonly string $method,
    ) {}

    /**
     * Equivalent to #[MapToApiVersion('1.1')] on this action.
     */
    public function mapToApiVersion(string $version): self
    {
        $this->registry->addActionVersion($this->class, $this->method, $version);

        return $this;
    }

    /**
     * Mark a version this action maps to as deprecated. Call
     * {@see mapToApiVersion()} first (or pass a version this method
     * already maps to).
     */
    public function hasDeprecatedApiVersion(string $version, ?string $sunset = null, ?string $replacedBy = null): self
    {
        $this->registry->addActionVersion($this->class, $this->method, $version);
        $this->registry->markActionVersionDeprecated($this->class, $this->method, $version, $sunset, $replacedBy);

        return $this;
    }

    /**
     * Equivalent to #[ApiVersionNeutral] on this action.
     */
    public function isApiVersionNeutral(): self
    {
        $this->registry->markActionNeutral($this->class, $this->method);

        return $this;
    }
}
