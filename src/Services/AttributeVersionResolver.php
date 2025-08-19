<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Services;

use Illuminate\Routing\Route;
use ReflectionClass;
use ReflectionMethod;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\ApiVersion;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\ApiVersionNeutral;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\Deprecated;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\MapToApiVersion;
use ShahGhasiAdil\LaravelApiVersioning\ValueObjects\VersionInfo;

class AttributeVersionResolver
{
	public function __construct(
		private readonly VersionManager $versionManager
	) {}

	public function resolveVersionForRoute(Route $route, string $requestedVersion): ?VersionInfo
	{
		$controller = $route->getController();
		$action = $route->getActionMethod();

		if ($controller === null) {
			return null;
		}

		$controllerClass = new ReflectionClass($controller);
		$actionMethod = $controllerClass->getMethod($action);

		// Check if method is version neutral
		if ($this->hasAttribute($actionMethod, ApiVersionNeutral::class) ||
			$this->hasAttribute($controllerClass, ApiVersionNeutral::class)) {
			return $this->createVersionInfo($requestedVersion, true);
		}

		// Get method-level version mappings
		$methodVersions = $this->getVersionsFromAttributes($actionMethod, MapToApiVersion::class);
		if ($methodVersions !== [] && in_array($requestedVersion, $methodVersions, true)) {
			return $this->createVersionInfo($requestedVersion, false, $actionMethod, $controllerClass);
		}

		// Get method-level API versions
		$methodApiVersions = $this->getVersionsFromAttributes($actionMethod, ApiVersion::class);
		if ($methodApiVersions !== [] && in_array($requestedVersion, $methodApiVersions, true)) {
			return $this->createVersionInfo($requestedVersion, false, $actionMethod, $controllerClass);
		}

		// Get controller-level versions
		$controllerVersions = $this->getVersionsFromAttributes($controllerClass, ApiVersion::class);
		if ($controllerVersions !== [] && in_array($requestedVersion, $controllerVersions, true)) {
			return $this->createVersionInfo($requestedVersion, false, $actionMethod, $controllerClass);
		}

		return null;
	}

	private function createVersionInfo(
		string $version,
		bool $isNeutral,
		?ReflectionMethod $method = null,
		?ReflectionClass $class = null
	): VersionInfo {
		$deprecated = null;

		if ($method !== null) {
			$deprecated = $this->getDeprecationInfo($method) ?? $this->getDeprecationInfo($class);
		} elseif ($class !== null) {
			$deprecated = $this->getDeprecationInfo($class);
		}

		return new VersionInfo(
			version: $version,
			isNeutral: $isNeutral,
			isDeprecated: $deprecated !== null,
			deprecationMessage: $deprecated?->message,
			sunsetDate: $deprecated?->sunsetDate,
			replacedBy: $deprecated?->replacedBy
		);
	}

	private function hasAttribute(ReflectionClass|ReflectionMethod $reflection, string $attributeClass): bool
	{
		return $reflection->getAttributes($attributeClass) !== [];
	}

	/**
	 * @return string[]
	 */
	private function getVersionsFromAttributes(ReflectionClass|ReflectionMethod $reflection, string $attributeClass): array
	{
		$versions = [];
		$attributes = $reflection->getAttributes($attributeClass);

		foreach ($attributes as $attribute) {
			$instance = $attribute->newInstance();
			if (method_exists($instance, 'getVersions')) {
				$versions = array_merge($versions, $instance->getVersions());
			}
		}

		return array_unique($versions);
	}

	private function getDeprecationInfo(ReflectionClass|ReflectionMethod|null $reflection): ?Deprecated
	{
		if ($reflection === null) {
			return null;
		}

		$attributes = $reflection->getAttributes(Deprecated::class);

		return count($attributes) > 0 ? $attributes[0]->newInstance() : null;
	}

	/**
	 * @return string[]
	 */
	public function getAllVersionsForRoute(Route $route): array
	{
		$controller = $route->getController();
		$action = $route->getActionMethod();

		if ($controller === null) {
			return [];
		}

		$controllerClass = new ReflectionClass($controller);
		$actionMethod = $controllerClass->getMethod($action);

		// If version neutral, return all supported versions
		if ($this->hasAttribute($actionMethod, ApiVersionNeutral::class) ||
			$this->hasAttribute($controllerClass, ApiVersionNeutral::class)) {
			return $this->versionManager->getSupportedVersions();
		}

		$versions = [];

		// Method-level versions
		$versions = array_merge($versions, $this->getVersionsFromAttributes($actionMethod, MapToApiVersion::class));
		$versions = array_merge($versions, $this->getVersionsFromAttributes($actionMethod, ApiVersion::class));

		// Controller-level versions
		if ($versions === []) {
			$versions = $this->getVersionsFromAttributes($controllerClass, ApiVersion::class);
		}

		return array_unique($versions);
	}
}
