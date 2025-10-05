<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use ShahGhasiAdil\LaravelApiVersioning\Traits\HasApiVersionAttributes;

abstract class VersionedResourceCollection extends ResourceCollection
{
    use HasApiVersionAttributes;

    /**
     * The resource that this collection wraps
     *
     * @var string
     */
    public $collects;

    /**
     * Transform the resource collection into an array
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $version = $this->getCurrentApiVersion();

        if ($version === null) {
            return $this->callVersionMethod('default', $request);
        }

        return $this->callVersionMethod($version, $request);
    }

    /**
     * Call the appropriate version method based on configuration
     *
     * @return array<string, mixed>
     */
    protected function callVersionMethod(string $version, Request $request): array
    {
        $config = config('api-versioning', []);
        $versionMapping = $config['version_method_mapping'] ?? [];
        $inheritance = $config['version_inheritance'] ?? [];
        $defaultMethod = 'toArrayDefault';

        // Handle default version request
        if ($version === 'default') {
            return $this->callMethodIfExists($defaultMethod, $request);
        }

        // Try the direct version mapping first
        if (isset($versionMapping[$version])) {
            $method = $versionMapping[$version];
            if ($this->methodExists($method)) {
                return $this->callMethodIfExists($method, $request);
            }
        }

        // Try inheritance chain
        $currentVersion = $version;
        while (isset($inheritance[$currentVersion])) {
            $parentVersion = $inheritance[$currentVersion];
            if (isset($versionMapping[$parentVersion])) {
                $method = $versionMapping[$parentVersion];
                if ($this->methodExists($method)) {
                    return $this->callMethodIfExists($method, $request);
                }
            }
            $currentVersion = $parentVersion;
        }

        // Fall back to default method
        return $this->callMethodIfExists($defaultMethod, $request);
    }

    /**
     * Check if method exists in the current class
     */
    private function methodExists(string $method): bool
    {
        return method_exists($this, $method);
    }

    /**
     * Call method if it exists, otherwise return collection data
     *
     * @return array<string, mixed>
     */
    private function callMethodIfExists(string $method, Request $request): array
    {
        if ($this->methodExists($method)) {
            $result = \call_user_func([$this, $method], $request);

            return is_array($result) ? $result : [];
        }

        // Default: return collection as data array
        return [
            'data' => $this->collection,
        ];
    }

    /**
     * Add version-specific metadata
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'meta' => array_merge(
                $this->getMeta($request),
                [
                    'version' => $this->getCurrentApiVersion(),
                    'deprecated' => $this->isVersionDeprecated(),
                ]
            ),
        ];
    }

    /**
     * Get additional metadata for the collection
     * Override this method in child classes to add custom metadata
     *
     * @return array<string, mixed>
     */
    protected function getMeta(Request $request): array
    {
        return [];
    }

    /**
     * Override these methods in your resource collection classes as needed
     *
     * @return array<string, mixed>
     */
    protected function toArrayV1(Request $request): array
    {
        return $this->toArrayDefault($request);
    }

    /**
     * @return array<string, mixed>
     */
    protected function toArrayV11(Request $request): array
    {
        return $this->toArrayV1($request);
    }

    /**
     * @return array<string, mixed>
     */
    protected function toArrayV2(Request $request): array
    {
        return $this->toArrayDefault($request);
    }

    /**
     * @return array<string, mixed>
     */
    protected function toArrayV21(Request $request): array
    {
        return $this->toArrayV2($request);
    }

    /**
     * This method must be implemented by child classes
     *
     * @return array<string, mixed>
     */
    abstract protected function toArrayDefault(Request $request): array;
}
