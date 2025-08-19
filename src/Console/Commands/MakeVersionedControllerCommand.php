<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Filesystem\Filesystem;

class MakeVersionedControllerCommand extends GeneratorCommand
{
    protected $signature = 'make:versioned-controller
                           {name : The name of the controller}
                           {--version= : The API version (e.g., 1.0, 2.0)}
                           {--deprecated : Mark the controller as deprecated}
                           {--sunset= : Sunset date for deprecated controller}
                           {--replaced-by= : Version that replaces this controller}';

    protected $description = 'Create a new versioned API controller with attributes';

    protected $type = 'Controller';

    public function __construct(Filesystem $files)
    {
        parent::__construct($files);
    }

    protected function getStub(): string
    {
        return __DIR__.'/stubs/versioned-controller.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Http\Controllers\Api';
    }

    protected function buildClass($name): string
    {
        $stub = $this->files->get($this->getStub());

        $version = $this->option('version') ?? '1.0';
        $isDeprecated = (bool) $this->option('deprecated');
        $sunsetDate = $this->option('sunset');
        $replacedBy = $this->option('replaced-by');

        $replacements = [
            '{{ version }}' => $version,
            '{{ versionAttribute }}' => "#[ApiVersion('{$version}')]",
            '{{ deprecatedAttribute }}' => $isDeprecated ? $this->buildDeprecatedAttribute($sunsetDate, $replacedBy) : '',
            '{{ namespace }}' => $this->getNamespace($name),
            '{{ class }}' => $this->getClassName($name),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    private function buildDeprecatedAttribute(?string $sunsetDate, ?string $replacedBy): string
    {
        $attributes = [];

        if (is_string($sunsetDate) && !empty($sunsetDate)) {
            $attributes[] = "sunsetDate: '{$sunsetDate}'";
        }

        if (is_string($replacedBy) && !empty($replacedBy)) {
            $attributes[] = "replacedBy: '{$replacedBy}'";
        }

        $attributeParams = empty($attributes) ? '' : implode(', ', $attributes);

        return "#[Deprecated({$attributeParams})]";
    }

    private function getClassName(string $name): string
    {
        return class_basename($name);
    }
}
