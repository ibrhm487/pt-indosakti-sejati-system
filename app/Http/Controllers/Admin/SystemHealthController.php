<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\LogReader;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SystemHealthController extends Controller
{
    public function index(LogReader $reader): Response
    {
        return Inertia::render('Admin/System/Health', [
            'app' => [
                'name' => config('app.name'),
                'environment' => app()->environment(),
                'debug' => (bool) config('app.debug'),
                'url' => config('app.url'),
                'timezone' => config('app.timezone'),
                'laravel' => app()->version(),
                'php' => PHP_VERSION,
                'os' => php_uname('s').' '.php_uname('r'),
                'server' => $_SERVER['SERVER_SOFTWARE'] ?? php_uname('m'),
            ],
            'runtime' => [
                'memory_limit' => ini_get('memory_limit'),
                'memory_usage' => memory_get_usage(true),
                'memory_peak' => memory_get_peak_usage(true),
                'max_execution_time' => (int) ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'cache' => config('cache.default'),
                'queue' => config('queue.default'),
                'session' => config('session.driver'),
            ],
            'database' => $this->databaseInfo(),
            'storage' => $this->storageInfo($reader),
            'migrations' => $this->migrationInfo(),
            'counts' => [
                'users' => $this->safeCount('users'),
                'roles' => $this->safeCount('roles'),
                'permissions' => $this->safeCount('permissions'),
                'failed_jobs' => $this->safeCount('failed_jobs'),
            ],
            'log' => [
                'exists' => $reader->exists(),
                'size' => $reader->size(),
                'modified' => $reader->lastModified(),
                'last_error' => $reader->lastError(),
            ],
        ]);
    }

    /**
     * @return array{connection: string, driver: string|null, database: string|null, version: string|null, error: string|null}
     */
    private function databaseInfo(): array
    {
        $connection = (string) config('database.default');
        $version = null;
        $error = null;

        try {
            $result = DB::selectOne('select version() as version');
            $version = $result->version ?? null;
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }

        return [
            'connection' => $connection,
            'driver' => config("database.connections.{$connection}.driver"),
            'database' => config("database.connections.{$connection}.database"),
            'version' => $version,
            'error' => $error,
        ];
    }

    /**
     * @return array{storage_writable: bool, cache_writable: bool, disk_total: float|null, disk_free: float|null, log_size: int|null, log_modified: string|null}
     */
    private function storageInfo(LogReader $reader): array
    {
        $total = @disk_total_space(storage_path());
        $free = @disk_free_space(storage_path());

        return [
            'storage_writable' => is_writable(storage_path()),
            'cache_writable' => is_writable(base_path('bootstrap/cache')),
            'disk_total' => $total !== false ? (float) $total : null,
            'disk_free' => $free !== false ? (float) $free : null,
            'log_size' => $reader->exists() ? $reader->size() : null,
            'log_modified' => $reader->lastModified(),
        ];
    }

    /**
     * @return array{ran: int|null, total: int|null, pending: int|null, pending_list: array<int, string>}
     */
    private function migrationInfo(): array
    {
        try {
            $migrator = app('migrator');
            $files = $migrator->getMigrationFiles([database_path('migrations')]);
            $ran = $migrator->getRepository()->getRan();
            $pending = array_values(array_diff(array_keys($files), $ran));

            return [
                'ran' => count($ran),
                'total' => count($files),
                'pending' => count($pending),
                'pending_list' => array_slice($pending, 0, 10),
            ];
        } catch (Throwable) {
            return [
                'ran' => null,
                'total' => null,
                'pending' => null,
                'pending_list' => [],
            ];
        }
    }

    private function safeCount(string $table): ?int
    {
        try {
            return DB::table($table)->count();
        } catch (Throwable) {
            return null;
        }
    }
}
