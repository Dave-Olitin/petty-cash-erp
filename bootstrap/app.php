<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, \Illuminate\Http\Request $request) {
            // A 419 Token Mismatch occurred (typically page expired).
            // Instead of showing the default error page, we return a response that reloads the page.
            if ($request->expectsJson()) {
                return response()->json(['message' => 'CSRF token mismatch. Please refresh.'], 419);
            }
            
            // Redirect back with a flash message if possible, or just back.
            // A simple back() will often just reload the same page with a fresh token.
            return back()->with('error', 'Your session expired. Please try submitting again.');
        });
    })->create();
