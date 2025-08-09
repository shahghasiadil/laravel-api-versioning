<?php

namespace ShahGhasiAdil\LaravelApiVersioning\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use ShahGhasiAdil\LaravelApiVersioning\Traits\HasApiVersionAttributes;

abstract class VersionedJsonResource extends JsonResource
{
    use HasApiVersionAttributes;

    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $version = $this->getCurrentApiVersion();

        if (!$version) {
            return $this->callVersionMethod('default', $request);
        }

        return $this->callVersionMethod($version, $request);
    }

    /**
     * Call the appropriate version method based on configuration
     */
    protected function callVersionMethod(string $version, Request $request): array
    {
        $config = config('api-versioning', []);
        $versionMapping = $config['version_method_mapping'] ?? [];
        $inheritance = $config['version_inheritance'] ?? [];
        $defaultMethod = $config['default_method'] ?? 'toArrayDefault';

        // Handle default version request
        if ($version === 'default') {
            return $this->callMethodIfExists($defaultMethod, $request);
        }

        // Try the direct version mapping first
        if (isset($versionMapping[$version])) {
            $method = $versionMapping[$version];
            if ($this->methodExists($method)) {
                return $this->$method($request);
            }
        }

        // Try inheritance chain
        $currentVersion = $version;
        while (isset($inheritance[$currentVersion])) {
            $parentVersion = $inheritance[$currentVersion];
            if (isset($versionMapping[$parentVersion])) {
                $method = $versionMapping[$parentVersion];
                if ($this->methodExists($method)) {
                    return $this->$method($request);
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
     * Call method if it exists, otherwise return empty array
     */
    private function callMethodIfExists(string $method, Request $request): array
    {
        if ($this->methodExists($method)) {
            return $this->$method($request);
        }

        // Last resort - return basic resource array
        return $this->resource->toArray();
    }

    /**
     * Add version-specific metadata
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'version' => $this->getCurrentApiVersion(),
                'deprecated' => $this->isVersionDeprecated(),
            ],
        ];
    }

    /**
     * Override these methods in your resource classes as needed
     * The configuration will determine which ones are called
     */
    protected function toArrayV1(Request $request): array
    {
        return $this->toArrayDefault($request);
    }

    protected function toArrayV11(Request $request): array
    {
        return $this->toArrayV1($request);
    }

    protected function toArrayV2(Request $request): array
    {
        return $this->toArrayDefault($request);
    }

    protected function toArrayV21(Request $request): array
    {
        return $this->toArrayV2($request);
    }

    /**
     * This method must be implemented by child classes
     */
    abstract protected function toArrayDefault(Request $request): array;
}
