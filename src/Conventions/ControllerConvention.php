<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Conventions;

/**
 * Fluent builder for a single controller class's conventions, obtained via
 * {@see ConventionBuilder::controller()}.
 */
final class ControllerConvention
{
    /**
     * @param  class-string  $class
     */
    public function __construct(
        private readonly ConventionRegistry $registry,
        private readonly string $class,
    ) {}

    /**
     * Equivalent to #[ApiVersion($versions)] on this controller.
     *
     * @param  string|string[]  $versions
     */
    public function hasApiVersion(string|array $versions): self
    {
        $this->registry->addControllerVersions($this->class, is_array($versions) ? $versions : [$versions]);

        return $this;
    }

    /**
     * Mark one or more versions this controller declares as deprecated.
     * Calls {@see hasApiVersion()} for you, so this alone is enough to
     * both declare and deprecate a version.
     *
     * @param  string|string[]  $versions
     */
    public function hasDeprecatedApiVersion(string|array $versions, ?string $sunset = null, ?string $replacedBy = null): self
    {
        $versions = is_array($versions) ? $versions : [$versions];
        $this->registry->addControllerVersions($this->class, $versions);
        $this->registry->markControllerVersionsDeprecated($this->class, $versions, $sunset, $replacedBy);

        return $this;
    }

    /**
     * Equivalent to #[ApiVersionNeutral] on this controller.
     */
    public function isApiVersionNeutral(): self
    {
        $this->registry->markControllerNeutral($this->class);

        return $this;
    }

    /**
     * Configure conventions for a single action (method) on this
     * controller, e.g. `$c->controller(Foo::class)->action('legacyIndex')->mapToApiVersion('1.0')`.
     */
    public function action(string $method): ActionConvention
    {
        return new ActionConvention($this->registry, $this->class, $method);
    }
}
