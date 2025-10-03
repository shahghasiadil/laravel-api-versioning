<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use ShahGhasiAdil\LaravelApiVersioning\Traits\HasApiVersionAttributes;

abstract class VersionedFormRequest extends FormRequest
{
    use HasApiVersionAttributes;

    /**
     * Get the validation rules that apply to the request
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $version = $this->getCurrentApiVersion();

        if ($version === null) {
            return $this->callVersionMethod('default');
        }

        return $this->callVersionMethod($version);
    }

    /**
     * Call the appropriate version method based on configuration
     *
     * @return array<string, mixed>
     */
    protected function callVersionMethod(string $version): array
    {
        $config = config('api-versioning', []);
        $versionMapping = $config['version_method_mapping'] ?? [];
        $inheritance = $config['version_inheritance'] ?? [];

        // Transform version mapping to rules mapping
        // e.g., 'toArrayV1' -> 'rulesV1'
        $rulesMapping = [];
        foreach ($versionMapping as $ver => $method) {
            $rulesMapping[$ver] = str_replace('toArray', 'rules', $method);
        }

        // Handle default version request
        if ($version === 'default') {
            return $this->callMethodIfExists('rulesDefault');
        }

        // Try the direct version mapping first
        if (isset($rulesMapping[$version])) {
            $method = $rulesMapping[$version];
            if ($this->methodExists($method)) {
                return $this->callMethodIfExists($method);
            }
        }

        // Try inheritance chain
        $currentVersion = $version;
        while (isset($inheritance[$currentVersion])) {
            $parentVersion = $inheritance[$currentVersion];
            if (isset($rulesMapping[$parentVersion])) {
                $method = $rulesMapping[$parentVersion];
                if ($this->methodExists($method)) {
                    return $this->callMethodIfExists($method);
                }
            }
            $currentVersion = $parentVersion;
        }

        // Fall back to default method
        return $this->callMethodIfExists('rulesDefault');
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
     *
     * @return array<string, mixed>
     */
    private function callMethodIfExists(string $method): array
    {
        if ($this->methodExists($method)) {
            $result = \call_user_func([$this, $method]);

            return is_array($result) ? $result : [];
        }

        return [];
    }

    /**
     * Get custom messages for validator errors
     * Can be overridden for version-specific messages
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $version = $this->getCurrentApiVersion();

        if ($version === null) {
            return $this->messagesDefault();
        }

        $method = 'messages'.str_replace('.', '', ucfirst($version));

        if ($this->methodExists($method)) {
            return $this->callMethodIfExists($method);
        }

        return $this->messagesDefault();
    }

    /**
     * Get custom attributes for validator errors
     * Can be overridden for version-specific attributes
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $version = $this->getCurrentApiVersion();

        if ($version === null) {
            return $this->attributesDefault();
        }

        $method = 'attributes'.str_replace('.', '', ucfirst($version));

        if ($this->methodExists($method)) {
            return $this->callMethodIfExists($method);
        }

        return $this->attributesDefault();
    }

    /**
     * Override these methods in your request classes as needed
     *
     * @return array<string, mixed>
     */
    protected function rulesV1(): array
    {
        return $this->rulesDefault();
    }

    /**
     * @return array<string, mixed>
     */
    protected function rulesV11(): array
    {
        return $this->rulesV1();
    }

    /**
     * @return array<string, mixed>
     */
    protected function rulesV2(): array
    {
        return $this->rulesDefault();
    }

    /**
     * @return array<string, mixed>
     */
    protected function rulesV21(): array
    {
        return $this->rulesV2();
    }

    /**
     * This method must be implemented by child classes
     *
     * @return array<string, mixed>
     */
    abstract protected function rulesDefault(): array;

    /**
     * Default messages - can be overridden
     *
     * @return array<string, string>
     */
    protected function messagesDefault(): array
    {
        return [];
    }

    /**
     * Default attributes - can be overridden
     *
     * @return array<string, string>
     */
    protected function attributesDefault(): array
    {
        return [];
    }
}
