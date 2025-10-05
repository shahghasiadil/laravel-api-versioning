<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Console\Commands;

use Illuminate\Console\Command;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionConfigService;

class ApiVersionConfigCommand extends Command
{
    protected $signature = 'api:version-config
                           {--show : Show current version configuration}
                           {--add-version= : Add a new version mapping}
                           {--method= : Method name for the version}';

    protected $description = 'Manage API version configuration';

    public function __construct(
        private readonly VersionConfigService $configService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ((bool) $this->option('show')) {
            $this->showConfiguration();

            return self::SUCCESS;
        }

        /** @var string|null $addVersion */
        $addVersion = $this->option('add-version');
        if (is_string($addVersion) && $addVersion !== '') {
            $this->addVersionMapping($addVersion);

            return self::SUCCESS;
        }

        $this->error('Please specify an action. Use --help for available options.');

        return self::FAILURE;
    }

    private function showConfiguration(): void
    {
        $this->info('📋 Current API Version Configuration');
        $this->newLine();

        // Show supported versions
        $versions = $this->configService->getSupportedVersions();
        $this->info('✅ Supported Versions: '.implode(', ', $versions));
        $this->newLine();

        // Show version mappings
        $mappings = $this->configService->getVersionMappings();
        if ($mappings === []) {
            $this->warn('No version method mappings configured.');

            return;
        }

        $this->table(
            ['Version', 'Method', 'Inheritance'],
            collect($mappings)->map(function (string $method, string $version): array {
                $inheritance = implode(' → ', $this->configService->getInheritanceChain($version));

                return [$version, $method, $inheritance !== '' ? $inheritance : 'None'];
            })->toArray()
        );
    }

    private function addVersionMapping(string $version): void
    {
        /** @var string|null $methodOption */
        $methodOption = $this->option('method');
        if (! is_string($methodOption)) {
            /** @var string|null $method */
            $method = $this->ask('Method name for version '.$version);
        } else {
            $method = $methodOption;
        }

        if (! is_string($method) || $method === '') {
            $this->error('Method name is required.');

            return;
        }

        $this->info('To add version mapping, update your config/api-versioning.php:');
        $this->line("'version_method_mapping' => [");
        $this->line('    // ... existing mappings');
        $this->line("    '{$version}' => '{$method}',");
        $this->line('],');

        $this->newLine();
        $this->info('💡 Don\'t forget to implement the method in your resource classes!');
    }
}
