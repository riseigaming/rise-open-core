<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
        'passwordConfirm',
        'cf-turnstile-response',
        'analytics_session_id',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $e)
    {
        if ($this->shouldTraceCmsLoginFailure($request)) {
            // Preserve the limiter's status and retry headers; this is not a server failure.
            if ($e instanceof ThrottleRequestsException) {
                $traceCode = $this->resolveLoginTraceCode($request);

                return response()->json([
                    'status' => 429,
                    'message' => trans('auth.throttle', [
                        'seconds' => (int) ($e->getHeaders()['Retry-After'] ?? 60),
                    ]),
                    'traceCode' => $traceCode,
                ], 429, $e->getHeaders())->header('X-Auth-Trace-Code', $traceCode);
            }

            $traceCode = $this->resolveLoginTraceCode($request);
            $statusCode = $e instanceof TokenMismatchException ? 419 : 500;

            Log::warning('CMS login request failed before controller response', [
                'trace_code' => $traceCode,
                'status_code' => $statusCode,
                'exception' => get_class($e),
                'exception_message_hash' => hash('sha256', $e->getMessage()),
                'request_path' => $request->path(),
                'login_identity_hash' => hash('sha256', mb_strtolower(trim((string) $request->input('email')))),
                'auth_surface' => (string) $request->input('auth_surface', ''),
            ]);

            return response()->json([
                'status' => $statusCode,
                'message' => trans('alerts.general_error'),
                'traceCode' => $traceCode,
            ], $statusCode)->header('X-Auth-Trace-Code', $traceCode);
        }

        return parent::render($request, $e);
    }

    private function shouldTraceCmsLoginFailure(Request $request): bool
    {
        return $request->ajax() && $request->is('cms/login');
    }

    private function resolveLoginTraceCode(Request $request): string
    {
        $incomingTraceCode = strtoupper(trim((string) ($request->input('client_trace_id', '') ?: $request->header('X-Auth-Trace-Code', ''))));

        if ($incomingTraceCode !== '' && preg_match('/^[A-Z0-9-]{6,64}$/', $incomingTraceCode)) {
            return $incomingTraceCode;
        }

        return 'LGN-' . strtoupper(substr(sha1($request->path() . microtime(true)), 0, 12));
    }
}
