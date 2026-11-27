<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PublicFrontendMaintenance
{
    public function handle(Request $request, Closure $next)
    {
        if ($this->shouldBypass($request)) {
            return $next($request);
        }

        $maintenanceEnabled = (bool) config('cms_public.maintenance.enabled', true);
        if ($maintenanceEnabled) {
            $readiness = $this->manualMaintenanceReadiness();
            $reason = 'manual_flag';

            $this->logMaintenanceResponse($reason, $readiness);

            return response()->view('public-maintenance', $this->maintenanceViewData($reason, $readiness), 503);
        }

        $readiness = $this->publicDatabaseReadiness();
        $reason = (string) ($readiness['reason'] ?? 'unknown');

        if ($readiness['ready'] === true) {
            return $this->withAuthSensitiveHtmlCacheHeaders($request, $next($request));
        }

        $this->logMaintenanceResponse($reason, $readiness);

        return response()->view('public-maintenance', $this->maintenanceViewData($reason, $readiness), 503);
    }

    private function shouldBypass(Request $request): bool
    {
        $path = trim($request->path(), '/');

        if ($path === 'admin' || Str::startsWith($path, 'admin/')) {
            return true;
        }

        if ($path === 'cms/admin' || Str::startsWith($path, 'cms/admin/')) {
            return true;
        }

        if ($path === 'health' || $path === 'status' || Str::endsWith($path, ['/health', '/status'])) {
            return true;
        }

        return false;
    }

    private function withAuthSensitiveHtmlCacheHeaders(Request $request, Response $response): Response
    {
        if (!$request->isMethod('GET') && !$request->isMethod('HEAD')) {
            return $response;
        }

        if ($request->ajax() || $request->expectsJson()) {
            return $response;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');
        if (!Str::contains(Str::lower($contentType), 'text/html')) {
            return $response;
        }

        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    private function publicDatabaseReadiness(): array
    {
        try {
            DB::select('select 1 as ok');

            foreach (config('cms_public.readiness.required_tables', []) as $table) {
                if (! is_string($table) || $table === '' || ! Schema::hasTable($table)) {
                    return [
                        'ready' => false,
                        'reason' => 'schema_missing',
                        'details' => 'The database is reachable, but required public CMS tables are missing.',
                        'error_code' => 'CMS_PUBLIC_SCHEMA_MISSING',
                        'exception_class' => null,
                        'exception_message' => null,
                    ];
                }
            }

            foreach (config('cms_public.readiness.required_records', []) as $table) {
                if (! is_string($table) || $table === '') {
                    continue;
                }

                if ((int) DB::table($table)->count() < 1) {
                    return [
                        'ready' => false,
                        'reason' => 'content_missing',
                        'details' => 'The database is reachable, but required public CMS records are missing.',
                        'error_code' => 'CMS_PUBLIC_CONTENT_MISSING',
                        'exception_class' => null,
                        'exception_message' => null,
                    ];
                }
            }

            return [
                'ready' => true,
                'reason' => 'ready',
                'details' => null,
                'error_code' => null,
                'exception_class' => null,
                'exception_message' => null,
            ];
        } catch (Throwable $exception) {
            return [
                'ready' => false,
                'reason' => 'db_unavailable',
                'details' => 'The public CMS could not connect to its database.',
                'error_code' => 'CMS_PUBLIC_DB_UNAVAILABLE',
                'exception_class' => get_class($exception),
                'exception_message' => $this->sanitizeExceptionMessage($exception->getMessage()),
            ];
        }
    }

    private function manualMaintenanceReadiness(): array
    {
        return [
            'ready' => false,
            'reason' => 'manual_flag',
            'details' => null,
            'error_code' => null,
            'exception_class' => null,
            'exception_message' => null,
        ];
    }

    private function maintenanceViewData(string $reason, array $readiness): array
    {
        $isLocal = app()->environment(['local', 'development']);
        $isLocalDbFailure = $isLocal && $reason === 'db_unavailable';

        return [
            'message' => $isLocalDbFailure
                ? 'Local DB readiness failed'
                : (string) config('cms_public.maintenance.message', 'Under Maintenance'),
            'details' => $isLocalDbFailure
                ? 'This is not Laravel maintenance mode. The public CMS database readiness check could not reach the configured database.'
                : $readiness['details'],
            'errorCode' => $readiness['error_code'],
            'maintenanceReason' => $reason,
            'localDiagnostics' => $isLocalDbFailure ? $this->localDiagnostics($readiness) : [],
        ];
    }

    private function localDiagnostics(array $readiness): array
    {
        $connection = (array) config('database.connections.' . config('database.default'), []);

        return [
            'maintenance_reason' => 'db_unavailable',
            'laravel_down_file' => file_exists(storage_path('framework/down')) ? 'present' : 'absent',
            'configured_host' => (string) ($connection['host'] ?? ''),
            'configured_port' => (string) ($connection['port'] ?? ''),
            'configured_database' => (string) ($connection['database'] ?? ''),
            'configured_username' => (string) ($connection['username'] ?? ''),
            'exception' => trim((string) ($readiness['exception_class'] ?? '') . ': ' . (string) ($readiness['exception_message'] ?? ''), ': '),
            'next_check' => 'php artisan app:install:check',
        ];
    }

    private function logMaintenanceResponse(string $reason, array $readiness): void
    {
        Log::notice('Public CMS maintenance guard returned 503.', [
            'maintenance_reason' => $reason,
            'error_code' => $readiness['error_code'] ?? null,
            'env' => app()->environment(),
            'db_host' => config('database.connections.' . config('database.default') . '.host'),
            'db_port' => config('database.connections.' . config('database.default') . '.port'),
            'db_database' => config('database.connections.' . config('database.default') . '.database'),
            'exception_class' => $readiness['exception_class'] ?? null,
        ]);
    }

    private function sanitizeExceptionMessage(string $message): string
    {
        $password = (string) config('database.connections.' . config('database.default') . '.password', '');
        if ($password !== '') {
            $message = str_replace($password, '[redacted]', $message);
        }

        return $message;
    }
}
