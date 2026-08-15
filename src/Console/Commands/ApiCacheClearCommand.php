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
        $usedTagging = $cache->supportsTagging();

        $cache->flush();

        if ($usedTagging) {
            $this->components->info('API versioning cache cleared successfully (tag-based flush).');
        } else {
            $this->components->info('API versioning cache cleared successfully (indexed-key flush; the active cache driver does not support tags).');
        }

        return self::SUCCESS;
    }
}
