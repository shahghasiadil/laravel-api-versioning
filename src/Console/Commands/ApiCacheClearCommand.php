<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelApiVersioning\Console\Commands;

use Illuminate\Console\Command;
use ShahGhasiAdil\LaravelApiVersioning\Services\AttributeCacheService;

class ApiCacheClearCommand extends Command
{
    protected $signature = 'api:cache:clear';

    protected $description = 'Clear the API versioning attribute cache';

    public function handle(AttributeCacheService $cache): int
    {
        $cache->flush();

        $this->components->info('API versioning cache cleared successfully.');

        return self::SUCCESS;
    }
}
