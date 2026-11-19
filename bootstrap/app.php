<?php

use App\Http\Middleware\EnsureActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias(['active' => EnsureActive::class]);
        $middleware->redirectUsersTo('/dashboard');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontFlash(['current_password', 'password', 'password_confirmation', 'otp']);
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            if ($response->getStatusCode() !== 419 || $request->expectsJson()
                || ! $request->routeIs('login.store', 'password.update', 'password.email', 'password.reset.store', 'logout')) {
                return $response;
            }

            // Reject the stale submission, then load a new form. Never replay or flash passwords.
            $user = $request->user();
            $destination = $user?->active && $user?->team_id
                ? ($request->routeIs('password.update') ? 'password.change' : 'dashboard')
                : 'login';
            if (! $user && $request->routeIs('password.email', 'password.reset.store')) {
                $destination = $request->session()->has('reset_challenge') && $request->routeIs('password.reset.store') ? 'password.reset' : 'password.request';
            }

            return redirect()->route($destination, [], 303)
                ->with('status', 'Your form expired. Please enter your details again.')
                ->header('Cache-Control', 'private, no-store');
        });
    })->create();
