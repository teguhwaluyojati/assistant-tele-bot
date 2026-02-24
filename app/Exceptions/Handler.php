<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use App\Services\TelegramNotifier;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            if (!$this->shouldNotify($e)) {
                return;
            }

            try {
                $notifier = app(TelegramNotifier::class);
                $request = request();
                $user = $request->user();

                $notifier->notifyError('Backend Error', [
                    'type' => class_basename($e),
                    'message' => $e->getMessage(),
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'user' => $user?->email ?? 'guest',
                    'ip' => $request->ip(),
                ]);
            } catch (Throwable $inner) {
                // Avoid recursive failures.
            }
        });
    }

    private function shouldNotify(Throwable $e): bool
    {
        if (app()->runningInConsole()) {
            return false;
        }

        if ($e instanceof ValidationException) {
            return false;
        }

        if ($e instanceof AuthenticationException) {
            return false;
        }

        if ($e instanceof HttpExceptionInterface) {
            return $e->getStatusCode() >= 500;
        }

        return true;
    }
}
