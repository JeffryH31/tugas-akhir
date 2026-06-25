<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function ($response, $exception, $request) {
            $status = $exception instanceof HttpExceptionInterface
                ? $exception->getStatusCode()
                : ($response->getStatusCode() ?? 500);

            // 403: redirect back with flash error (shown as snackbar) instead of a full error page.
            if ($status === 403 && $request->header('X-Inertia')) {
                return redirect()->back()->with('error', 'You don\'t have permission to perform this action.');
            }

            if (! $request->header('X-Inertia')) {
                return $response;
            }

            if (in_array($status, [404, 500, 503])) {
                return Inertia::render('Error', [
                    'status' => $status,
                ])->toResponse($request)->setStatusCode($status);
            }

            return $response;
        });
    })->create();
