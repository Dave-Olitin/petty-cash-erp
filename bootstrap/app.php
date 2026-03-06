<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Database\QueryException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'push/subscribe',
            'push/unsubscribe',
            'push/*'
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        // ── Fix #1: Handle MySQL deadlocks & lock timeouts with a friendly message ──
        // TransactionObserver uses lockForUpdate() heavily; concurrent requests can
        // deadlock. Without this, users see a generic 500. With it, they get a clear
        // "please retry" prompt.
        $exceptions->render(function (QueryException $e, \Illuminate\Http\Request $request) {
            $deadlockCodes = [1205, 1213]; // Lock wait timeout & Deadlock found
            if (in_array($e->getCode(), $deadlockCodes)) {
                $message = 'The system is busy processing another request. Please try again in a moment.';
                if ($request->expectsJson()) {
                    return response()->json(['message' => $message], 409);
                }
                return back()->withErrors(['amount' => $message])->withInput();
            }
        });

        $exceptions->render(function (LockTimeoutException $e, \Illuminate\Http\Request $request) {
            $message = 'Could not acquire a database lock. Please try submitting again.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 409);
            }
            return back()->withErrors(['amount' => $message])->withInput();
        });

        // ── Existing: CSRF token expiry handler ──
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'CSRF token mismatch. Please refresh.'], 419);
            }
            return back()->with('error', 'Your session expired. Please try submitting again.');
        });

    })->create();
