<?php

namespace App\Http\Controllers;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Throwable;

class ReadinessController extends Controller
{
    private const REQUIRED_TABLES = ['users', 'products', 'cart_items', 'orders'];

    public function __invoke(Migrator $migrator): JsonResponse
    {
        try {
            $this->checkSchema($migrator);
            $this->checkCache();
            $this->checkSession();
        } catch (Throwable) {
            return response()->json(['status' => 'unavailable'], 503);
        }

        return response()->json(['status' => 'ready']);
    }

    private function checkSchema(Migrator $migrator): void
    {
        if (! $migrator->repositoryExists()) {
            throw new RuntimeException('Migration repository is unavailable.');
        }

        $migrationFiles = array_keys($migrator->getMigrationFiles(database_path('migrations')));
        if (array_diff($migrationFiles, $migrator->getRepository()->getRan())) {
            throw new RuntimeException('Pending migrations exist.');
        }

        $schema = DB::connection()->getSchemaBuilder();
        foreach (self::REQUIRED_TABLES as $table) {
            if (! $schema->hasTable($table)) {
                throw new RuntimeException('Required schema is unavailable.');
            }
        }
    }

    private function checkCache(): void
    {
        $store = (string) config('cache.default');
        $configuration = config("cache.stores.{$store}");

        if (! is_array($configuration)) {
            throw new RuntimeException('Cache store is unavailable.');
        }

        match ((string) ($configuration['driver'] ?? '')) {
            'array', 'null' => null,
            'database' => $this->checkDatabaseCache($configuration),
            'file' => $this->checkFileCache($configuration),
            'redis' => $this->checkRedisCache($configuration),
            default => throw new RuntimeException('Configured cache driver is unsupported.'),
        };
    }

    private function checkSession(): void
    {
        $this->checkDriver(
            (string) config('session.driver'),
            config('session.connection'),
            config('session.table'),
            config('session.files'),
        );
    }

    private function checkDriver(string $driver, mixed $connection, mixed $table, mixed $path): void
    {
        match ($driver) {
            'array', 'cookie', 'null' => null,
            'database' => $this->checkDatabase($connection, $table),
            'file' => $this->checkDirectory($path),
            'redis' => Redis::connection(is_string($connection) ? $connection : null)->ping(),
            default => throw new RuntimeException('Configured dependency driver is unsupported.'),
        };
    }

    private function checkDatabase(mixed $connection, mixed $table): void
    {
        if (! is_string($table) || ! DB::connection(is_string($connection) ? $connection : null)->getSchemaBuilder()->hasTable($table)) {
            throw new RuntimeException('Configured database table is unavailable.');
        }
    }

    /** @param array<string, mixed> $configuration */
    private function checkDatabaseCache(array $configuration): void
    {
        $this->checkDatabase($configuration['connection'] ?? null, $configuration['table'] ?? null);
        $this->checkDatabase($configuration['lock_connection'] ?? null, $configuration['lock_table'] ?? null);
    }

    /** @param array<string, mixed> $configuration */
    private function checkFileCache(array $configuration): void
    {
        $this->checkDirectory($configuration['path'] ?? null);
        $this->checkDirectory($configuration['lock_path'] ?? null);
    }

    /** @param array<string, mixed> $configuration */
    private function checkRedisCache(array $configuration): void
    {
        Redis::connection(is_string($configuration['connection'] ?? null) ? $configuration['connection'] : null)->ping();
        Redis::connection(is_string($configuration['lock_connection'] ?? null) ? $configuration['lock_connection'] : null)->ping();
    }

    private function checkDirectory(mixed $path): void
    {
        if (! is_string($path) || ! is_dir($path) || ! is_writable($path)) {
            throw new RuntimeException('Configured storage directory is unavailable.');
        }
    }
}
